<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionAssessmentPanel;
use App\Models\User;
use App\Services\AdmissionBulkEvaluatorAssignmentService;
use Illuminate\Http\Request;

class AssessmentBulkAssignmentController extends Controller
{
    public function index(AdmissionBulkEvaluatorAssignmentService $service)
    {
        $panel = AdmissionAssessmentPanel::with('members.user')->latest()->first();

        return view('admission.v0037.assessment-bulk-assignment', [
            'panels' => AdmissionAssessmentPanel::with('members.user')->latest()->limit(30)->get(),
            'selectedPanel' => $panel,
            'candidates' => $panel ? $service->candidatesFor($panel) : collect(),
            'evaluators' => $panel?->members?->pluck('user')->filter() ?? collect(),
        ]);
    }

    public function store(Request $request, AdmissionBulkEvaluatorAssignmentService $service)
    {
        $data = $request->validate([
            'panel_id' => ['required', 'exists:admission_assessment_panels,id'],
            'applicant_ids' => ['required', 'array'],
            'strategy' => ['required', 'in:fixed,round_robin,least_pending'],
            'fixed_evaluator_id' => ['nullable', 'exists:users,id'],
        ]);

        $batch = $service->assign(
            AdmissionAssessmentPanel::findOrFail($data['panel_id']),
            $data['applicant_ids'],
            $data['strategy'],
            isset($data['fixed_evaluator_id']) ? User::find($data['fixed_evaluator_id']) : null,
            $request->user()
        );

        return back()->with('success', "Bulk assignment completed: {$batch->assigned_count} candidate(s), {$batch->conflict_count} conflict(s).");
    }
}
