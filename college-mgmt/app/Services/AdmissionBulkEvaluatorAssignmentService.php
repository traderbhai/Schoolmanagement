<?php

namespace App\Services;

use App\Models\AdmissionAssessmentPanel;
use App\Models\AdmissionEvaluatorAssignmentBatch;
use App\Models\Applicant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AdmissionBulkEvaluatorAssignmentService
{
    public function __construct(private AdmissionAssessmentSchedulingService $scheduling) {}

    public function assign(AdmissionAssessmentPanel $panel, iterable $applicantIds, string $strategy, ?User $fixedEvaluator, User $actor): AdmissionEvaluatorAssignmentBatch
    {
        $panel->loadMissing('members.user');
        $evaluators = $panel->members->pluck('user')->filter()->values();
        if ($strategy === 'fixed' && $fixedEvaluator) {
            $evaluators = collect([$fixedEvaluator]);
        }
        if ($evaluators->isEmpty()) {
            throw ValidationException::withMessages(['evaluator' => 'No evaluator is available for this panel.']);
        }

        $ids = collect($applicantIds)->filter()->unique()->values();
        $remaining = max(0, $panel->capacity - $panel->assignments()->count());
        if ($ids->count() > $remaining) {
            throw ValidationException::withMessages(['capacity' => 'Selected candidates exceed remaining panel capacity.']);
        }

        $assigned = 0;
        foreach ($ids as $index => $applicantId) {
            $evaluator = $this->pickEvaluator($evaluators, $strategy, $index);
            $panel->assignments()->updateOrCreate(
                ['applicant_id' => $applicantId],
                ['selection_session_id' => $panel->selection_session_id, 'evaluator_user_id' => $evaluator?->id, 'attendance_status' => 'pending', 'lifecycle_status' => 'invited', 'score_status' => 'pending']
            );
            $assigned++;
        }

        $conflicts = $this->scheduling->detectConflictsForPanel($panel)->count();

        return AdmissionEvaluatorAssignmentBatch::create([
            'panel_id' => $panel->id,
            'strategy' => $strategy,
            'fixed_evaluator_id' => $fixedEvaluator?->id,
            'candidate_count' => $ids->count(),
            'assigned_count' => $assigned,
            'conflict_count' => $conflicts,
            'created_by' => $actor->id,
            'metadata' => ['applicant_ids' => $ids->all()],
        ]);
    }

    public function candidatesFor(AdmissionAssessmentPanel $panel): Collection
    {
        return Applicant::with('user')->where('program_id', $panel->program_id)->limit(50)->get();
    }

    private function pickEvaluator(Collection $evaluators, string $strategy, int $index): ?User
    {
        if ($strategy === 'least_pending') {
            return $evaluators->sortBy(fn (User $user) => $user->id)->first();
        }
        return $evaluators->values()->get($index % max(1, $evaluators->count()));
    }
}
