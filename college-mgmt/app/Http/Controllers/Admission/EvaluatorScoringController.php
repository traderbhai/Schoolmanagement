<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionAssessmentPanelAssignment;
use App\Services\AdmissionEvaluatorScoringService;
use Illuminate\Http\Request;

class EvaluatorScoringController extends Controller
{
    public function index(Request $request, AdmissionEvaluatorScoringService $service)
    {
        return view('admission.v0036.evaluator-scoring', [
            'assignments' => $service->visibleAssignments($request->user())->paginate(20)->withQueryString(),
        ]);
    }

    public function save(Request $request, AdmissionAssessmentPanelAssignment $assignment, AdmissionEvaluatorScoringService $service)
    {
        $data = $request->validate([
            'criteria' => ['required', 'array'],
            'criteria.*.score' => ['nullable', 'numeric', 'min:0'],
            'criteria.*.comment' => ['nullable', 'string'],
            'recommendation' => ['nullable', 'string', 'max:80'],
            'finalize' => ['nullable', 'boolean'],
        ]);

        $request->boolean('finalize')
            ? $service->submitFinal($assignment, $request->user(), $data['criteria'], $data['recommendation'] ?? null)
            : $service->saveDraft($assignment, $request->user(), $data['criteria'], $data['recommendation'] ?? null);

        return back()->with('success', $request->boolean('finalize') ? 'Score finalized.' : 'Draft score saved.');
    }

    public function lifecycle(Request $request, AdmissionAssessmentPanelAssignment $assignment, AdmissionEvaluatorScoringService $service)
    {
        $data = $request->validate([
            'lifecycle_status' => ['required', 'in:invited,confirmed,checked_in,waiting,in_progress,completed,no_show,rescheduled,cancelled'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $service->markLifecycle($assignment, $data['lifecycle_status'], $request->user(), $data['reason'] ?? null);

        return back()->with('success', 'Assessment lifecycle updated.');
    }
}
