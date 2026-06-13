<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionAssessmentPanel;
use App\Models\Program;
use App\Models\SelectionSession;
use App\Models\User;
use App\Services\AdmissionAssessmentPanelService;
use Illuminate\Http\Request;

class AssessmentPanelController extends Controller
{
    public function index()
    {
        return view('admission.v0031.assessment-panels', [
            'panels' => AdmissionAssessmentPanel::with(['program', 'batch', 'session', 'members.user', 'assignments.applicant.user'])->latest('scheduled_at')->get(),
            'sessions' => SelectionSession::with(['program', 'batch'])->latest('scheduled_date')->limit(50)->get(),
            'programs' => Program::orderBy('name')->get(),
            'evaluators' => User::role(['admission_head', 'admission_manager', 'admission_counsellor', 'admission_officer', 'admin'])->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, AdmissionAssessmentPanelService $service)
    {
        $panel = $service->createPanel($request->validate([
            'name' => ['required', 'string', 'max:120'],
            'panel_type' => ['required', 'string', 'max:80'],
            'program_id' => ['nullable', 'exists:programs,id'],
            'batch_id' => ['nullable', 'exists:batches,id'],
            'selection_session_id' => ['nullable', 'exists:selection_sessions,id'],
            'capacity' => ['required', 'integer', 'min:1'],
            'venue' => ['nullable', 'string', 'max:160'],
            'online_link' => ['nullable', 'string', 'max:255'],
            'scheduled_at' => ['nullable', 'date'],
        ]), $request->user());

        foreach ((array) $request->input('evaluator_ids', []) as $evaluatorId) {
            if ($evaluator = User::find($evaluatorId)) {
                $service->addEvaluator($panel, $evaluator);
            }
        }

        return back()->with('success', 'Assessment panel created.');
    }
}
