<?php

namespace App\Services;

use App\Models\AdmissionAutomation;
use App\Models\AdmissionAutomationConflictLog;
use App\Models\AdmissionAutomationSimulation;
use App\Models\Applicant;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Collection;

class AdmissionAutomationSimulationService
{
    public function simulate(AdmissionAutomation $automation, ?User $actor = null): AdmissionAutomationSimulation
    {
        $subjects = $this->subjectsFor($automation->trigger)->filter(fn ($subject) => $this->matches($automation, $subject))->take(25);
        $preview = $subjects->map(fn ($subject) => [
            'subject_type' => class_basename($subject),
            'subject_id' => $subject->id,
            'actions' => $automation->actions,
        ])->values();

        return AdmissionAutomationSimulation::create([
            'automation_id' => $automation->id,
            'trigger' => $automation->trigger,
            'window_start' => now()->subDays(30),
            'window_end' => now(),
            'matched_count' => $subjects->count(),
            'preview_actions' => $preview,
            'created_by' => $actor?->id,
        ]);
    }

    public function detectConflicts(AdmissionAutomation $automation, object $subject): ?AdmissionAutomationConflictLog
    {
        $fieldActions = collect($automation->actions ?? [])->filter(fn ($action) => in_array($action['type'] ?? '', ['priority', 'next_action'], true));
        if ($fieldActions->count() <= 1) {
            return null;
        }

        return AdmissionAutomationConflictLog::create([
            'automation_id' => $automation->id,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->id,
            'conflict_key' => 'multiple_field_updates',
            'severity' => 'medium',
            'status' => 'open',
            'message' => 'Automation contains multiple field-changing actions; review priority and exclusivity.',
            'metadata' => ['actions' => $fieldActions->values()->all()],
        ]);
    }

    private function subjectsFor(string $trigger): Collection
    {
        return str_contains($trigger, 'applicant') ? Applicant::latest()->limit(100)->get() : Lead::latest()->limit(100)->get();
    }

    private function matches(AdmissionAutomation $automation, object $subject): bool
    {
        foreach (($automation->conditions ?? []) as $field => $expected) {
            if ((string) data_get($subject, $field) !== (string) $expected) {
                return false;
            }
        }
        return true;
    }
}
