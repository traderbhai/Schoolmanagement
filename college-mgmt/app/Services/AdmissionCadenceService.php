<?php

namespace App\Services;

use App\Models\AdmissionCadenceRule;
use App\Models\AdmissionReminderSchedule;
use App\Models\Applicant;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class AdmissionCadenceService
{
    public function __construct(private AdmissionReminderService $reminders) {}

    public function activeRules(?string $targetType = null): Collection
    {
        return AdmissionCadenceRule::with('template')
            ->where('is_active', true)
            ->when($targetType, fn ($q, $type) => $q->where('target_type', $type))
            ->orderBy('name')
            ->get();
    }

    public function applyRule(Model $subject, AdmissionCadenceRule $rule, ?User $actor = null): AdmissionReminderSchedule
    {
        $repeat = $rule->repeat_rule ?? [];
        $delayHours = (int) ($repeat['initial_delay_hours'] ?? 24);

        return $this->reminders->schedule($subject, [
            'cadence_rule_id' => $rule->id,
            'template_id' => $rule->template_id,
            'target' => $subject instanceof Applicant ? 'applicant' : 'lead',
            'reason' => $rule->reason,
            'channel' => $rule->channel,
            'due_at' => now()->addHours($delayHours),
            'repeat_rule' => $repeat,
            'notes' => $repeat['message'] ?? 'Cadence reminder',
        ], $actor);
    }

    public function nextRepeat(AdmissionReminderSchedule $reminder, ?User $actor = null): ?AdmissionReminderSchedule
    {
        $rule = $reminder->cadenceRule;
        if (!$rule || $reminder->attempt_count >= $rule->max_attempts || !$reminder->subject) {
            return null;
        }

        $intervalHours = (int) (($reminder->repeat_rule['interval_hours'] ?? null) ?: 24);

        return $this->reminders->schedule($reminder->subject, [
            'cadence_rule_id' => $rule->id,
            'template_id' => $reminder->template_id,
            'target' => $reminder->target,
            'reason' => $reminder->reason,
            'channel' => $reminder->channel,
            'priority' => $reminder->priority,
            'due_at' => now()->addHours($intervalHours),
            'repeat_rule' => $reminder->repeat_rule,
            'notes' => $reminder->notes,
            'metadata' => ['previous_reminder_id' => $reminder->id],
        ], $actor);
    }
}
