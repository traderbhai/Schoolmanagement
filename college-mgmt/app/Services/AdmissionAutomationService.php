<?php

namespace App\Services;

use App\Models\AdmissionAutomation;
use App\Models\AdmissionAutomationExecution;
use App\Models\AdmissionCommunicationTemplate;
use App\Models\AdmissionDataQualityFlag;
use App\Models\AdmissionReminderSchedule;
use App\Models\Lead;
use App\Models\Applicant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class AdmissionAutomationService
{
    public function __construct(
        private AdmissionCommunicationService $communication,
        private AdmissionLeadScoringService $scoring,
    ) {}

    public function run(string $trigger, Lead|Applicant $subject, ?User $actor = null): Collection
    {
        return AdmissionAutomation::where('trigger', $trigger)
            ->where('is_active', true)
            ->orderBy('priority')
            ->get()
            ->filter(fn (AdmissionAutomation $automation) => $this->matches($automation, $subject))
            ->map(fn (AdmissionAutomation $automation) => $this->execute($automation, $subject, $actor))
            ->values();
    }

    public function execute(AdmissionAutomation $automation, Lead|Applicant $subject, ?User $actor = null): AdmissionAutomationExecution
    {
        $key = implode(':', [$automation->id, get_class($subject), $subject->id, $automation->updated_at?->timestamp]);
        $existing = AdmissionAutomationExecution::where('idempotency_key', $key)->first();
        if ($existing) {
            return $existing;
        }

        $actionsTaken = [];
        try {
            foreach ($automation->actions ?? [] as $action) {
                $actionsTaken[] = $this->performAction($action, $subject, $actor);
            }

            return AdmissionAutomationExecution::create([
                'automation_id' => $automation->id,
                'subject_type' => get_class($subject),
                'subject_id' => $subject->id,
                'idempotency_key' => $key,
                'status' => 'completed',
                'actions_taken' => $actionsTaken,
            ]);
        } catch (\Throwable $e) {
            return AdmissionAutomationExecution::create([
                'automation_id' => $automation->id,
                'subject_type' => get_class($subject),
                'subject_id' => $subject->id,
                'idempotency_key' => $key,
                'status' => 'failed',
                'actions_taken' => $actionsTaken,
                'failure_reason' => $e->getMessage(),
            ]);
        }
    }

    private function matches(AdmissionAutomation $automation, Model $subject): bool
    {
        foreach (($automation->conditions ?? []) as $field => $expected) {
            if ((string) data_get($subject, $field) !== (string) $expected) {
                return false;
            }
        }

        return true;
    }

    private function performAction(array $action, Lead|Applicant $subject, ?User $actor): array
    {
        return match ($action['type'] ?? '') {
            'tag' => $this->tag($subject, (int) ($action['tag_id'] ?? 0)),
            'priority' => tap(['type' => 'priority', 'value' => $action['value'] ?? null], fn () => $subject->update(['priority' => $action['value'] ?? $subject->priority])),
            'next_action' => tap(['type' => 'next_action', 'value' => $action['value'] ?? null], fn () => $subject->update(['next_action' => $action['value'] ?? null, 'last_activity_at' => now()])),
            'score_lead' => $subject instanceof Lead ? ['type' => 'score_lead', 'score_id' => $this->scoring->score($subject, $actor)->id] : ['type' => 'score_lead', 'skipped' => true],
            'send_communication' => $this->sendCommunication($subject, (int) ($action['template_id'] ?? 0), $actor),
            'create_reminder' => $this->createReminder($subject, $action, $actor),
            'parent_followup' => $this->parentFollowup($subject, $actor),
            'data_quality_flag' => $this->dataQualityFlag($subject, $action, $actor),
            'coaching_review' => $this->coachingReview($subject, $action, $actor),
            'pipeline_stage' => tap(['type' => 'pipeline_stage', 'value' => $action['value'] ?? null], fn () => $subject->update(['status' => $action['value'] ?? $subject->status])),
            'assessment_reminder' => $this->createReminder($subject, $action + ['reason' => 'assessment_reminder'], $actor),
            default => ['type' => $action['type'] ?? 'unknown', 'skipped' => true],
        };
    }

    private function tag(Lead|Applicant $subject, int $tagId): array
    {
        if ($tagId > 0) {
            $subject->tags()->syncWithoutDetaching([$tagId => ['tagged_by' => auth()->id()]]);
        }

        return ['type' => 'tag', 'tag_id' => $tagId];
    }

    private function sendCommunication(Lead|Applicant $subject, int $templateId, ?User $actor): array
    {
        $template = AdmissionCommunicationTemplate::findOrFail($templateId);
        $log = app(AdmissionSafeCommunicationService::class)->queue($subject, $template, $actor, ['source' => 'automation']);
        if (isset($log->blocked_by_rule)) {
            return ['type' => 'send_communication', 'blocked_id' => $log->id, 'blocked_reason' => $log->reason];
        }

        return ['type' => 'send_communication', 'log_id' => $log->id];
    }

    private function createReminder(Lead|Applicant $subject, array $action, ?User $actor): array
    {
        $reminder = AdmissionReminderSchedule::create([
            'subject_type' => get_class($subject),
            'subject_id' => $subject->id,
            'owner_user_id' => $actor?->id,
            'assigned_to' => $actor?->id,
            'reason' => $action['reason'] ?? 'automation_followup',
            'channel' => $action['channel'] ?? 'email',
            'status' => 'scheduled',
            'priority' => $action['priority'] ?? 'normal',
            'due_at' => now()->addHours((int) ($action['due_hours'] ?? 24)),
            'notes' => $action['notes'] ?? 'Created by automation.',
        ]);

        return ['type' => 'create_reminder', 'reminder_id' => $reminder->id];
    }

    private function parentFollowup(Lead|Applicant $subject, ?User $actor): array
    {
        $journey = app(AdmissionParentJourneyService::class)->ensure($subject, $actor);
        $reminder = app(AdmissionParentJourneyService::class)->createReminder($journey, $actor);

        return ['type' => 'parent_followup', 'journey_id' => $journey->id, 'reminder_id' => $reminder->id];
    }

    private function dataQualityFlag(Lead|Applicant $subject, array $action, ?User $actor): array
    {
        $flag = AdmissionDataQualityFlag::create([
            'subject_type' => get_class($subject),
            'subject_id' => $subject->id,
            'flag_type' => $action['flag_type'] ?? 'automation_review',
            'severity' => $action['severity'] ?? 'warning',
            'message' => $action['message'] ?? 'Automation requested staff review.',
            'status' => 'open',
            'metadata' => ['actor_id' => $actor?->id],
        ]);

        return ['type' => 'data_quality_flag', 'flag_id' => $flag->id];
    }

    private function coachingReview(Lead|Applicant $subject, array $action, ?User $actor): array
    {
        $owner = $subject instanceof Lead ? $subject->assignedTo : $subject->assignedCounsellor;
        if (! $owner || ! $actor) {
            return ['type' => 'coaching_review', 'skipped' => true];
        }
        $note = app(AdmissionCounsellorPerformanceService::class)->addCoachingNote($owner, $actor, [
            'score_band' => $action['score_band'] ?? 'needs_coaching',
            'action_plan' => $action['action_plan'] ?? 'Review automation-triggered applicant/lead follow-up quality.',
        ]);

        return ['type' => 'coaching_review', 'note_id' => $note->id];
    }
}
