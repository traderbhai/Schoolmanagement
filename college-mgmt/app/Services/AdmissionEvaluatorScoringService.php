<?php

namespace App\Services;

use App\Models\AdmissionAssessmentLifecycleEvent;
use App\Models\AdmissionAssessmentPanelAssignment;
use App\Models\AdmissionEvaluatorScore;
use App\Models\ApplicantScore;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class AdmissionEvaluatorScoringService
{
    public function __construct(
        private AdmissionRubricService $rubrics,
        private AdmissionAccessPolicyService $accessPolicy,
    ) {}

    public function visibleAssignments(User $viewer): Builder
    {
        $query = AdmissionAssessmentPanelAssignment::with(['panel.rubric.criteria', 'panel.session', 'applicant.user', 'evaluator'])
            ->latest();

        if (!$this->accessPolicy->canSeeAll($viewer)) {
            $query->where('evaluator_user_id', $viewer->id);
        }

        return $query;
    }

    public function saveDraft(AdmissionAssessmentPanelAssignment $assignment, User $evaluator, array $criteria, ?string $recommendation = null): ApplicantScore
    {
        return $this->score($assignment, $evaluator, $criteria, $recommendation, false);
    }

    public function submitFinal(AdmissionAssessmentPanelAssignment $assignment, User $evaluator, array $criteria, ?string $recommendation = null): ApplicantScore
    {
        if (in_array($assignment->score_status, ['finalized', 'overridden'], true) && !$this->accessPolicy->canApproveAdmission($evaluator)) {
            throw ValidationException::withMessages(['score' => 'Finalized score is locked.']);
        }

        return $this->score($assignment, $evaluator, $criteria, $recommendation, true);
    }

    public function markLifecycle(AdmissionAssessmentPanelAssignment $assignment, string $status, User $actor, ?string $reason = null): AdmissionAssessmentPanelAssignment
    {
        $allowed = ['invited', 'confirmed', 'checked_in', 'waiting', 'in_progress', 'completed', 'no_show', 'rescheduled', 'cancelled'];
        if (!in_array($status, $allowed, true)) {
            throw ValidationException::withMessages(['lifecycle_status' => 'Invalid lifecycle status.']);
        }

        $from = $assignment->lifecycle_status;
        $assignment->update([
            'lifecycle_status' => $status,
            'attendance_status' => in_array($status, ['checked_in', 'waiting', 'in_progress', 'completed'], true) ? 'present' : ($status === 'no_show' ? 'absent' : $assignment->attendance_status),
            'metadata' => array_merge($assignment->metadata ?? [], ['last_lifecycle_reason' => $reason]),
        ]);

        if ($assignment->selection_session_id && $assignment->applicant_id) {
            \App\Models\SessionApplicant::where('selection_session_id', $assignment->selection_session_id)
                ->where('applicant_id', $assignment->applicant_id)
                ->update([
                    'lifecycle_status' => $status,
                    'attendance_status' => $assignment->fresh()->attendance_status,
                    'checked_in_at' => $status === 'checked_in' ? now() : null,
                    'completed_at' => $status === 'completed' ? now() : null,
                ]);
        }

        AdmissionAssessmentLifecycleEvent::create([
            'selection_session_id' => $assignment->selection_session_id,
            'panel_id' => $assignment->panel_id,
            'assignment_id' => $assignment->id,
            'applicant_id' => $assignment->applicant_id,
            'from_status' => $from,
            'to_status' => $status,
            'reason' => $reason,
            'actor_user_id' => $actor->id,
        ]);

        return $assignment->refresh();
    }

    private function score(AdmissionAssessmentPanelAssignment $assignment, User $evaluator, array $criteria, ?string $recommendation, bool $final): ApplicantScore
    {
        $assignment->loadMissing(['panel.rubric.criteria', 'panel.session']);
        $rubric = $assignment->panel?->rubric ?: $this->rubrics->activeForType($assignment->panel?->panel_type ?? 'personal_interview', $assignment->panel?->program_id);

        if (!$rubric) {
            throw ValidationException::withMessages(['rubric' => 'No active rubric is available for this panel.']);
        }

        if (!$this->accessPolicy->canSeeAll($evaluator) && (int) $assignment->evaluator_user_id !== (int) $evaluator->id) {
            abort(403);
        }

        $result = $this->rubrics->calculate($rubric, $criteria);
        foreach ($rubric->criteria as $criterion) {
            $detail = $result['details'][$criterion->id];
            AdmissionEvaluatorScore::updateOrCreate(
                ['assignment_id' => $assignment->id, 'criterion_id' => $criterion->id, 'evaluator_user_id' => $evaluator->id],
                [
                    'rubric_id' => $rubric->id,
                    'score' => $detail['score'],
                    'max_score' => $detail['max_score'],
                    'weighted_score' => $detail['weighted_score'],
                    'comment' => $detail['comment'],
                    'status' => $final ? 'finalized' : 'draft',
                    'submitted_at' => $final ? now() : null,
                    'locked_at' => $final ? now() : null,
                    'metadata' => ['recommendation' => $recommendation],
                ]
            );
        }

        $score = ApplicantScore::updateOrCreate(
            [
                'applicant_id' => $assignment->applicant_id,
                'selection_session_id' => $assignment->selection_session_id,
                'scored_by' => $evaluator->id,
            ],
            [
                'selection_process_step_id' => $assignment->panel?->session?->selection_process_step_id,
                'parameter_scores' => $result['details'],
                'total_score' => $result['total_score'],
                'max_possible_score' => $result['max_possible_score'],
                'percentage' => $result['percentage'],
                'remarks' => collect($result['details'])->pluck('comment')->filter()->implode(' | '),
                'is_final' => $final,
                'score_status' => $final ? 'finalized' : 'draft',
                'locked_at' => $final ? now() : null,
                'locked_by' => $final ? $evaluator->id : null,
                'recommendation' => $recommendation,
            ]
        );

        $percentages = ApplicantScore::where('applicant_id', $assignment->applicant_id)
            ->where('selection_session_id', $assignment->selection_session_id)
            ->pluck('percentage');
        $variance = $this->rubrics->variance($percentages);

        $assignment->update([
            'score_status' => $final ? 'finalized' : 'draft',
            'recommendation' => $recommendation,
            'aggregate_score' => $variance['average'],
            'variance_score' => $variance['spread'],
            'variance_flag' => $variance['flag'],
            'score_locked_at' => $final ? now() : null,
            'finalized_at' => $final ? now() : null,
        ]);

        return $score;
    }
}
