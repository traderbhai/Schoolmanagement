<?php

namespace App\Services;

use App\Models\AdmissionCommunicationTemplate;
use App\Models\AdmissionReminderSchedule;
use App\Models\Applicant;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AdmissionReminderService
{
    public function __construct(
        private AdmissionCommunicationService $communication,
        private DepartmentHierarchyService $hierarchy,
    ) {}

    public function schedule(Model $subject, array $data, ?User $actor = null): AdmissionReminderSchedule
    {
        return AdmissionReminderSchedule::create([
            'subject_type' => get_class($subject),
            'subject_id' => $subject->getKey(),
            'cadence_rule_id' => $data['cadence_rule_id'] ?? null,
            'template_id' => $data['template_id'] ?? null,
            'owner_user_id' => $data['owner_user_id'] ?? ($subject->owner_user_id ?? null),
            'assigned_to' => $data['assigned_to'] ?? ($subject->assigned_to ?? $actor?->id),
            'target' => $data['target'] ?? ($subject instanceof Applicant ? 'applicant' : 'lead'),
            'reason' => $data['reason'] ?? 'follow_up',
            'channel' => $data['channel'] ?? 'email',
            'status' => $data['status'] ?? 'scheduled',
            'priority' => $data['priority'] ?? ($subject->priority ?? 'normal'),
            'due_at' => $data['due_at'] ?? now()->addDay(),
            'repeat_rule' => $data['repeat_rule'] ?? null,
            'notes' => $data['notes'] ?? null,
            'metadata' => ($data['metadata'] ?? []) + ['created_by' => $actor?->id],
        ]);
    }

    public function sendNow(AdmissionReminderSchedule $reminder, ?User $actor = null): AdmissionReminderSchedule
    {
        $subject = $reminder->subject;
        $template = $reminder->template ?: AdmissionCommunicationTemplate::where('channel', $reminder->channel)->where('is_active', true)->first();

        if (($subject instanceof Lead || $subject instanceof Applicant) && $template) {
            $log = app(AdmissionSafeCommunicationService::class)->queue($subject, $template, $actor, [
                'next_action' => $reminder->notes ?: ($subject->next_action ?? ''),
                'deadline' => optional($reminder->due_at)->format('d M Y'),
            ]);

            $metadata = $reminder->metadata ?? [];
            if (isset($log->blocked_by_rule)) {
                $metadata['blocked_communication_id'] = $log->id;
                $metadata['blocked_reason'] = $log->reason;
            } else {
                $metadata['communication_log_id'] = $log->id;
            }
            $reminder->metadata = $metadata;
        }

        $reminder->update([
            'status' => 'queued',
            'sent_at' => now(),
            'attempt_count' => $reminder->attempt_count + 1,
        ]);

        return $reminder->refresh();
    }

    public function bulkSchedule(iterable $subjects, array $data, ?User $actor = null): Collection
    {
        return collect($subjects)->map(fn (Model $subject) => $this->schedule($subject, $data, $actor));
    }

    public function dueFor(User $viewer, array $filters = []): Collection
    {
        return $this->queryFor($viewer, $filters)
            ->limit($filters['limit'] ?? 100)
            ->get();
    }

    public function queryFor(User $viewer, array $filters = []): Builder
    {
        $query = AdmissionReminderSchedule::with(['subject', 'assignee', 'template'])
            ->whereIn('status', ['scheduled', 'queued', 'paused', 'escalated'])
            ->when($filters['reason'] ?? null, fn ($q, $reason) => $q->where('reason', $reason))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['date'] ?? null, fn ($q, $date) => $q->whereDate('due_at', $date))
            ->orderByRaw("case priority when 'urgent' then 1 when 'high' then 2 when 'normal' then 3 else 4 end")
            ->orderBy('due_at');

        if (!$viewer->hasRole('admin') && !$this->hierarchy->canSeeAll($viewer, 'ADM')) {
            $visibleIds = $this->hierarchy->visibleUserIds($viewer, 'ADM')->push($viewer->id)->unique();
            $query->where(function ($q) use ($visibleIds) {
                $q->whereIn('assigned_to', $visibleIds)->orWhereIn('owner_user_id', $visibleIds);
            });
        }

        return $query;
    }

    public function canAccess(AdmissionReminderSchedule $reminder, User $viewer): bool
    {
        if ($viewer->hasRole('admin') || $this->hierarchy->canSeeAll($viewer, 'ADM')) {
            return true;
        }

        $visibleIds = $this->hierarchy->visibleUserIds($viewer, 'ADM')->push($viewer->id)->unique();

        return $visibleIds->contains($reminder->assigned_to)
            || $visibleIds->contains($reminder->owner_user_id);
    }

    public function complete(AdmissionReminderSchedule $reminder, ?User $actor = null): AdmissionReminderSchedule
    {
        $reminder->update([
            'status' => 'completed',
            'completed_at' => now(),
            'metadata' => array_merge($reminder->metadata ?? [], ['completed_by' => $actor?->id]),
        ]);

        return $reminder->refresh();
    }

    public function pause(AdmissionReminderSchedule $reminder, string $reason, ?User $actor = null): AdmissionReminderSchedule
    {
        $reminder->update([
            'status' => 'paused',
            'paused_at' => now(),
            'metadata' => array_merge($reminder->metadata ?? [], ['pause_reason' => $reason, 'paused_by' => $actor?->id]),
        ]);

        return $reminder->refresh();
    }

    public function resume(AdmissionReminderSchedule $reminder, ?User $actor = null): AdmissionReminderSchedule
    {
        $reminder->update([
            'status' => 'scheduled',
            'paused_at' => null,
            'metadata' => array_merge($reminder->metadata ?? [], ['resumed_by' => $actor?->id]),
        ]);

        return $reminder->refresh();
    }

    public function escalate(AdmissionReminderSchedule $reminder, User $manager, ?User $actor = null): AdmissionReminderSchedule
    {
        $reminder->update([
            'status' => 'escalated',
            'escalated_at' => now(),
            'escalated_to' => $manager->id,
            'metadata' => array_merge($reminder->metadata ?? [], ['escalated_by' => $actor?->id]),
        ]);

        return $reminder->refresh();
    }
}
