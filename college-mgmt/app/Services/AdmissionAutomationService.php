<?php

namespace App\Services;

use App\Models\AdmissionAutomation;
use App\Models\AdmissionAutomationExecution;
use App\Models\AdmissionCommunicationTemplate;
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
        $log = $this->communication->queue($subject, $template, $actor);

        return ['type' => 'send_communication', 'log_id' => $log->id];
    }
}
