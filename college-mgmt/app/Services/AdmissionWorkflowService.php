<?php

namespace App\Services;

use App\Models\AdmissionWorkflowConfig;
use App\Models\Applicant;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AdmissionWorkflowService
{
    public function activeConfigs(string $type): Collection
    {
        return AdmissionWorkflowConfig::where('type', $type)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();
    }

    public function slaDueAt(Lead|Applicant $subject, ?string $priority = null, ?Carbon $from = null): Carbon
    {
        $priority = $priority ?: ($subject->priority ?? 'normal');
        $profile = AdmissionWorkflowConfig::where('type', 'sla_profile')
            ->where('is_active', true)
            ->get()
            ->first(fn (AdmissionWorkflowConfig $row) => $this->matchesSlaProfile($row, $subject, $priority));

        $hours = (int) data_get($profile?->config, 'hours', match ($priority) {
            'urgent' => 4,
            'high' => 12,
            'low' => 72,
            default => 24,
        });

        return ($from ?: now())->copy()->addHours(max(1, $hours));
    }

    public function pauseSla(Model $subject, User $actor, ?Carbon $until, ?string $reason): void
    {
        $subject->update([
            'sla_paused_until' => $until,
            'sla_pause_reason' => $reason,
            'last_activity_at' => now(),
        ]);
    }

    public function resumeSla(Model $subject, User $actor): void
    {
        $subject->update([
            'sla_paused_until' => null,
            'sla_pause_reason' => null,
            'last_activity_at' => now(),
        ]);
    }

    private function matchesSlaProfile(AdmissionWorkflowConfig $profile, Lead|Applicant $subject, string $priority): bool
    {
        $config = $profile->config ?? [];

        foreach (['priority', 'source', 'program_id', 'status'] as $field) {
            $expected = data_get($config, $field);
            if ($expected !== null && (string) $expected !== (string) ($field === 'priority' ? $priority : $subject->{$field})) {
                return false;
            }
        }

        return true;
    }
}
