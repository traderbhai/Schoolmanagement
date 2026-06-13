<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionAssessmentPanel;
use App\Models\Applicant;
use App\Models\ApplicantScore;
use App\Services\AdmissionAssessmentPanelService;
use Illuminate\Http\Request;

class AssessmentOperationController extends Controller
{
    public function index(AdmissionAssessmentPanelService $service)
    {
        return view('admission.v0031.assessment-operations', [
            'pendingScores' => $service->pendingScores(),
            'panels' => AdmissionAssessmentPanel::with(['session', 'assignments.applicant.user', 'members.user'])->latest('scheduled_at')->get(),
        ]);
    }

    public function assign(Request $request, AdmissionAssessmentPanelService $service)
    {
        $data = $request->validate([
            'panel_id' => ['required', 'exists:admission_assessment_panels,id'],
            'applicant_id' => ['required', 'exists:applicants,id'],
            'evaluator_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $service->assignApplicant(
            AdmissionAssessmentPanel::findOrFail($data['panel_id']),
            Applicant::findOrFail($data['applicant_id']),
            isset($data['evaluator_user_id']) ? \App\Models\User::find($data['evaluator_user_id']) : null,
        );

        return back()->with('success', 'Candidate assigned to assessment panel.');
    }

    public function finalize(Request $request, ApplicantScore $score, AdmissionAssessmentPanelService $service)
    {
        $service->finalizeScore($score, $request->user(), $request->input('recommendation'));

        return back()->with('success', 'Score finalized and locked.');
    }

    public function override(Request $request, ApplicantScore $score, AdmissionAssessmentPanelService $service)
    {
        $data = $request->validate([
            'override_reason' => ['required', 'string', 'min:5'],
            'recommendation' => ['nullable', 'string', 'max:80'],
        ]);
        $service->overrideScore($score, $request->user(), $data['override_reason'], $data['recommendation'] ?? null);

        return back()->with('success', 'Score override recorded.');
    }
}
