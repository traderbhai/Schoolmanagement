<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\ApplicantScore;
use App\Models\SelectionSession;
use App\Services\DepartmentHierarchyService;
use Illuminate\Http\Request;

class ScoringController extends Controller
{
    public function __construct(private DepartmentHierarchyService $hierarchy) {}

    public function sessionScoreSheet(SelectionSession $session)
    {
        $session->load(['step.scoringParameters', 'program', 'batch']);

        $presentApplicants = $session->sessionApplicants()
            ->where('attendance_status', 'present')
            ->whereHas('applicant', function ($query) {
                $this->hierarchy->applyApplicantVisibility($query, request()->user(), 'ADM');
            })
            ->with('applicant.user')
            ->get();

        $absentApplicants = $session->sessionApplicants()
            ->whereIn('attendance_status', ['absent', 'excused'])
            ->whereHas('applicant', function ($query) {
                $this->hierarchy->applyApplicantVisibility($query, request()->user(), 'ADM');
            })
            ->with('applicant.user')
            ->get();

        // Load existing scores keyed by applicant_id
        $existingScores = ApplicantScore::where('selection_session_id', $session->id)
            ->whereHas('applicant', function ($query) {
                $this->hierarchy->applyApplicantVisibility($query, request()->user(), 'ADM');
            })
            ->get()
            ->keyBy('applicant_id');

        return view('admission.scoring.session-scores', compact(
            'session', 'presentApplicants', 'absentApplicants', 'existingScores'
        ));
    }

    public function saveScores(Request $request, SelectionSession $session)
    {
        $session->load('step.scoringParameters');
        $parameters = $session->step->scoringParameters;

        $isFinal = $request->boolean('mark_final') && $this->hierarchy->canApproveAdmission($request->user());

        $scores = $request->input('scores', []);
        $visibleApplicantIds = Applicant::query()
            ->whereIn('id', array_keys($scores))
            ->tap(fn ($query) => $this->hierarchy->applyApplicantVisibility($query, $request->user(), 'ADM'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $presentApplicantIds = $session->sessionApplicants()
            ->where('attendance_status', 'present')
            ->pluck('applicant_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $existingScores = ApplicantScore::where('selection_session_id', $session->id)
            ->whereIn('applicant_id', array_keys($scores))
            ->get()
            ->keyBy(fn (ApplicantScore $score) => (int) $score->applicant_id);

        foreach (array_keys($scores) as $applicantId) {
            if (! in_array((int) $applicantId, $visibleApplicantIds, true)) {
                abort(403);
            }

            if (! in_array((int) $applicantId, $presentApplicantIds, true)) {
                return back()->withErrors([
                    'scores' => 'Scores can be saved only for applicants marked present in this session.',
                ]);
            }

            $existing = $existingScores->get((int) $applicantId);
            if ($existing && ($existing->is_final || in_array($existing->score_status, ['finalized', 'overridden'], true) || $existing->locked_at)) {
                return back()->withErrors([
                    'scores' => 'Finalized or overridden assessment scores are locked. Use the audited override workflow for corrections.',
                ]);
            }
        }

        foreach ($scores as $applicantId => $applicantScores) {
            $parameterScores = [];
            $totalScore = 0;
            $maxPossible = 0;

            foreach ($parameters as $param) {
                $score = isset($applicantScores['param_' . $param->id])
                    ? (float) $applicantScores['param_' . $param->id]
                    : 0;

                // Clamp to max
                $score = min($score, $param->max_score);
                $score = max($score, 0);

                $parameterScores[$param->id] = $score;
                $totalScore += $score;
                $maxPossible += $param->max_score;
            }

            $percentage = $maxPossible > 0 ? round(($totalScore / $maxPossible) * 100, 2) : 0;

            ApplicantScore::updateOrCreate(
                [
                    'applicant_id'           => $applicantId,
                    'selection_session_id'   => $session->id,
                ],
                [
                    'selection_process_step_id' => $session->selection_process_step_id,
                    'scored_by'                 => auth()->id(),
                    'parameter_scores'          => $parameterScores,
                    'total_score'               => $totalScore,
                    'max_possible_score'        => $maxPossible,
                    'percentage'               => $percentage,
                    'remarks'                   => $applicantScores['remarks'] ?? null,
                    'is_final'                  => $isFinal,
                    'score_status'              => $isFinal ? 'finalized' : 'draft',
                    'locked_at'                 => $isFinal ? now() : null,
                    'locked_by'                 => $isFinal ? $request->user()->id : null,
                ]
            );
        }

        return back()->with('success', 'Scores saved successfully.' . ($isFinal ? ' Marked as final.' : ''));
    }

    public function applicantScorecard(Applicant $applicant)
    {
        abort_unless(
            $this->hierarchy->canViewAssignedUser(request()->user(), 'ADM', $applicant->assigned_to, false),
            403
        );

        $applicant->load(['program', 'batch', 'user']);

        $scores = ApplicantScore::where('applicant_id', $applicant->id)
            ->with(['session', 'step.scoringParameters', 'scoredBy'])
            ->get();

        $meritListEntry = $applicant->meritListEntry()->first();

        // Calculate weighted score preview
        $totalWeighted = 0;
        $stepsData = [];
        foreach ($scores as $score) {
            $step = $score->step;
            if ($step) {
                $weightedContribution = $score->percentage * ($step->weightage ?? 0) / 100;
                $totalWeighted += $weightedContribution;
                $stepsData[] = [
                    'score' => $score,
                    'step'  => $step,
                    'weighted_contribution' => $weightedContribution,
                ];
            }
        }

        return view('admission.scoring.applicant-scorecard', compact(
            'applicant', 'scores', 'stepsData', 'totalWeighted', 'meritListEntry'
        ));
    }
}
