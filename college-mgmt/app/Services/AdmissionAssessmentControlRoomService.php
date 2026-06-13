<?php

namespace App\Services;

use App\Models\AdmissionAssessmentPanel;
use App\Models\AdmissionAssessmentPanelAssignment;
use App\Models\AdmissionAssessmentRubric;
use App\Models\SelectionSession;
use App\Models\User;

class AdmissionAssessmentControlRoomService
{
    public function __construct(private DepartmentHierarchyService $hierarchy) {}

    public function dashboard(User $viewer, array $filters = []): array
    {
        $sessionQuery = SelectionSession::with(['step', 'program', 'batch', 'sessionApplicants'])
            ->whereDate('scheduled_date', '>=', today()->subDay())
            ->when($filters['program_id'] ?? null, fn ($q, $id) => $q->where('program_id', $id))
            ->orderBy('scheduled_date')
            ->orderBy('start_time');

        $panels = AdmissionAssessmentPanel::with(['session.program', 'rubric', 'members.user', 'assignments.applicant.user', 'assignments.evaluator'])
            ->when($filters['program_id'] ?? null, fn ($q, $id) => $q->where('program_id', $id))
            ->whereDate('scheduled_at', '>=', today()->subDay())
            ->orderBy('scheduled_at')
            ->get();

        $sessions = $sessionQuery->limit(25)->get();
        $assignments = $panels->flatMap->assignments;
        $lifecycleCounts = $assignments->countBy(fn ($assignment) => $assignment->lifecycle_status ?: 'invited');
        $pendingScores = AdmissionAssessmentPanelAssignment::with(['panel', 'applicant.user', 'evaluator'])
            ->whereIn('score_status', ['pending', 'draft'])
            ->orderByDesc('updated_at')
            ->limit(25)
            ->get();

        $readiness = $panels->map(fn ($panel) => [
            'panel' => $panel,
            'has_evaluator' => $panel->members->isNotEmpty(),
            'capacity_filled' => $panel->assignments->count() . '/' . $panel->capacity,
            'has_venue' => filled($panel->venue) || filled($panel->online_link),
            'has_rubric' => (bool) $panel->rubric_id,
            'scores_pending' => $panel->assignments->whereIn('score_status', ['pending', 'draft'])->count(),
            'ready' => $panel->members->isNotEmpty() && (filled($panel->venue) || filled($panel->online_link)) && (bool) $panel->rubric_id,
        ]);

        return [
            'sessions' => $sessions,
            'panels' => $panels,
            'readiness' => $readiness,
            'pendingScores' => $pendingScores,
            'varianceQueue' => AdmissionAssessmentPanelAssignment::with(['panel', 'applicant.user', 'evaluator'])
                ->where('variance_flag', true)
                ->latest('updated_at')
                ->limit(20)
                ->get(),
            'rubrics' => AdmissionAssessmentRubric::withCount('criteria')->where('is_active', true)->orderBy('assessment_type')->get(),
            'stats' => [
                'sessions_today' => $sessions->where('scheduled_date', today())->count(),
                'panels_ready' => $readiness->where('ready', true)->count(),
                'candidates' => $assignments->count(),
                'pending_scores' => $pendingScores->count(),
                'no_show' => $lifecycleCounts->get('no_show', 0),
                'rescheduled' => $lifecycleCounts->get('rescheduled', 0),
            ],
            'lifecycleCounts' => $lifecycleCounts,
        ];
    }
}
