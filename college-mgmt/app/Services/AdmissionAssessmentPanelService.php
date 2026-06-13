<?php

namespace App\Services;

use App\Models\AdmissionAssessmentPanel;
use App\Models\AdmissionAssessmentPanelAssignment;
use App\Models\Applicant;
use App\Models\ApplicantScore;
use App\Models\SelectionSession;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AdmissionAssessmentPanelService
{
    public function createPanel(array $data, ?User $actor = null): AdmissionAssessmentPanel
    {
        return AdmissionAssessmentPanel::create($data + ['created_by' => $actor?->id]);
    }

    public function addEvaluator(AdmissionAssessmentPanel $panel, User $evaluator, string $role = 'evaluator', bool $isChair = false): void
    {
        $panel->members()->updateOrCreate(
            ['user_id' => $evaluator->id],
            ['role' => $role, 'is_chair' => $isChair]
        );
    }

    public function assignApplicant(AdmissionAssessmentPanel $panel, Applicant $applicant, ?User $evaluator = null): AdmissionAssessmentPanelAssignment
    {
        $assigned = $panel->assignments()->count();
        if ($assigned >= $panel->capacity && !$panel->assignments()->where('applicant_id', $applicant->id)->exists()) {
            throw ValidationException::withMessages(['panel' => 'Panel capacity is already full.']);
        }

        return $panel->assignments()->updateOrCreate(
            ['applicant_id' => $applicant->id],
            [
                'selection_session_id' => $panel->selection_session_id,
                'evaluator_user_id' => $evaluator?->id,
                'attendance_status' => 'pending',
                'score_status' => 'pending',
            ]
        );
    }

    public function pendingScores(?SelectionSession $session = null, ?User $evaluator = null): Collection
    {
        return AdmissionAssessmentPanelAssignment::with(['panel', 'applicant.user', 'evaluator'])
            ->whereIn('score_status', ['pending', 'draft'])
            ->when($session, fn ($q) => $q->where('selection_session_id', $session->id))
            ->when($evaluator, fn ($q) => $q->where('evaluator_user_id', $evaluator->id))
            ->orderByDesc('updated_at')
            ->get();
    }

    public function finalizeScore(ApplicantScore $score, User $actor, ?string $recommendation = null): ApplicantScore
    {
        $score->update([
            'is_final' => true,
            'score_status' => 'finalized',
            'locked_at' => now(),
            'locked_by' => $actor->id,
            'recommendation' => $recommendation,
        ]);

        AdmissionAssessmentPanelAssignment::where('selection_session_id', $score->selection_session_id)
            ->where('applicant_id', $score->applicant_id)
            ->update([
                'score_status' => 'finalized',
                'recommendation' => $recommendation,
                'score_locked_at' => now(),
                'finalized_at' => now(),
            ]);

        return $score->refresh();
    }

    public function overrideScore(ApplicantScore $score, User $actor, string $reason, ?string $recommendation = null): ApplicantScore
    {
        $score->update([
            'score_status' => 'overridden',
            'override_reason' => $reason,
            'locked_at' => now(),
            'locked_by' => $actor->id,
            'recommendation' => $recommendation,
            'is_final' => true,
        ]);

        AdmissionAssessmentPanelAssignment::where('selection_session_id', $score->selection_session_id)
            ->where('applicant_id', $score->applicant_id)
            ->update([
                'score_status' => 'overridden',
                'recommendation' => $recommendation,
                'manager_override_reason' => $reason,
                'overridden_by' => $actor->id,
                'score_locked_at' => now(),
                'finalized_at' => now(),
            ]);

        return $score->refresh();
    }

    public function summaryForSession(SelectionSession $session): array
    {
        $panels = AdmissionAssessmentPanel::with(['members.user', 'assignments'])
            ->where('selection_session_id', $session->id)
            ->get();

        return [
            'panels' => $panels,
            'panel_count' => $panels->count(),
            'candidate_count' => $panels->sum(fn ($panel) => $panel->assignments->count()),
            'pending_scores' => $panels->flatMap->assignments->whereIn('score_status', ['pending', 'draft'])->count(),
            'finalized_scores' => $panels->flatMap->assignments->whereIn('score_status', ['finalized', 'overridden'])->count(),
        ];
    }
}
