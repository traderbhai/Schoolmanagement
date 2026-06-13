<?php

namespace App\Services;

use App\Models\AdmissionAssessmentPanel;
use App\Models\AdmissionAssessmentScheduleConflict;
use App\Models\AdmissionEvaluatorAvailability;
use App\Models\User;
use Illuminate\Support\Collection;

class AdmissionAssessmentSchedulingService
{
    public function recordAvailability(User $user, array $data): AdmissionEvaluatorAvailability
    {
        return AdmissionEvaluatorAvailability::create([
            'user_id' => $user->id,
            'available_from' => $data['available_from'],
            'available_until' => $data['available_until'],
            'availability_type' => $data['availability_type'] ?? 'available',
            'location_mode' => $data['location_mode'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    public function detectConflictsForPanel(AdmissionAssessmentPanel $panel): Collection
    {
        $panel->loadMissing(['members.user', 'assignments']);
        $start = $panel->scheduled_at;
        $end = $panel->scheduled_at?->copy()->addMinutes((int) data_get($panel->metadata, 'duration_minutes', 90));

        $found = collect();

        if (! $panel->rubric_id) {
            $found->push($this->upsertConflict($panel, null, 'missing_rubric', 'high', 'Panel has no scoring rubric attached.'));
        }

        if (! $panel->venue && ! $panel->online_link) {
            $found->push($this->upsertConflict($panel, null, 'missing_location', 'medium', 'Panel needs either a venue or an online link.'));
        }

        if ($panel->assignments()->count() > $panel->capacity) {
            $found->push($this->upsertConflict($panel, null, 'capacity_overbooked', 'high', 'Assigned candidates exceed panel capacity.'));
        }

        if (! $start || ! $end) {
            return $found;
        }

        foreach ($panel->members as $member) {
            $user = $member->user;
            if (! $user) {
                continue;
            }

            $available = AdmissionEvaluatorAvailability::query()
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->where('availability_type', 'available')
                ->where('available_from', '<=', $start)
                ->where('available_until', '>=', $end)
                ->exists();

            if (! $available) {
                $found->push($this->upsertConflict($panel, $user, 'evaluator_unavailable', 'high', $user->name . ' is not available for this assessment slot.'));
            }

            $overlap = AdmissionAssessmentPanel::query()
                ->whereKeyNot($panel->id)
                ->whereHas('members', fn ($q) => $q->where('user_id', $user->id))
                ->whereBetween('scheduled_at', [$start->copy()->subMinutes(89), $end])
                ->exists();

            if ($overlap) {
                $found->push($this->upsertConflict($panel, $user, 'evaluator_double_booked', 'high', $user->name . ' is assigned to another panel in an overlapping slot.'));
            }
        }

        return $found;
    }

    public function dashboard(): array
    {
        $panels = AdmissionAssessmentPanel::with(['members.user', 'scheduleConflicts'])
            ->latest('scheduled_at')
            ->limit(20)
            ->get();

        $panels->each(fn ($panel) => $this->detectConflictsForPanel($panel));

        return [
            'panels' => AdmissionAssessmentPanel::with(['members.user', 'scheduleConflicts'])
                ->latest('scheduled_at')
                ->limit(20)
                ->get(),
            'openConflicts' => AdmissionAssessmentScheduleConflict::with(['panel', 'user'])
                ->where('status', 'open')
                ->latest('detected_at')
                ->paginate(20)
                ->withQueryString(),
            'availability' => AdmissionEvaluatorAvailability::with('user')
                ->where('is_active', true)
                ->orderBy('available_from')
                ->limit(30)
                ->get(),
            'stats' => [
                'open_conflicts' => AdmissionAssessmentScheduleConflict::where('status', 'open')->count(),
                'high_risk' => AdmissionAssessmentScheduleConflict::where('status', 'open')->where('severity', 'high')->count(),
                'available_evaluators' => AdmissionEvaluatorAvailability::where('is_active', true)->where('availability_type', 'available')->distinct('user_id')->count('user_id'),
                'panels_reviewed' => $panels->count(),
            ],
        ];
    }

    private function upsertConflict(AdmissionAssessmentPanel $panel, ?User $user, string $type, string $severity, string $message): AdmissionAssessmentScheduleConflict
    {
        return AdmissionAssessmentScheduleConflict::updateOrCreate(
            ['panel_id' => $panel->id, 'user_id' => $user?->id, 'conflict_type' => $type, 'status' => 'open'],
            ['severity' => $severity, 'message' => $message, 'detected_at' => now(), 'metadata' => ['v' => '0.037']]
        );
    }
}
