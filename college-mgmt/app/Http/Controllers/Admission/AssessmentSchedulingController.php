<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionAssessmentPanel;
use App\Models\Applicant;
use App\Models\SelectionSession;
use App\Services\AdmissionAssessmentResourceService;
use App\Services\AdmissionAssessmentSlotService;
use App\Services\AdmissionAssessmentSubmissionService;
use App\Services\AdmissionGdGroupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssessmentSchedulingController extends Controller
{
    public function index()
    {
        return view('admission.v0038.assessment-scheduling', [
            'panels' => AdmissionAssessmentPanel::with('members.user')->latest()->limit(15)->get(),
            'sessions' => SelectionSession::latest()->limit(15)->get(),
            'slots' => DB::table('admission_assessment_slots')->latest()->paginate(15),
            'resources' => DB::table('admission_assessment_resources')->where('is_active', true)->get(),
            'assignments' => DB::table('admission_assessment_slot_assignments')->latest()->limit(20)->get(),
            'invitations' => DB::table('admission_evaluator_invitations')->latest()->limit(20)->get(),
            'conflicts' => app(AdmissionAssessmentResourceService::class)->conflicts(),
            'gdGroups' => DB::table('admission_gd_groups')->latest()->limit(20)->get(),
            'submissions' => DB::table('admission_assessment_submissions')->latest()->limit(20)->get(),
        ]);
    }

    public function storeSlot(Request $request, AdmissionAssessmentSlotService $service)
    {
        $data = $request->validate([
            'selection_session_id' => ['nullable', 'exists:selection_sessions,id'],
            'panel_id' => ['nullable', 'exists:admission_assessment_panels,id'],
            'resource_id' => ['nullable', 'exists:admission_assessment_resources,id'],
            'slot_code' => ['required', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date'],
            'capacity' => ['required', 'integer', 'min:1'],
        ]);
        $service->create($data);
        return back()->with('success', 'Assessment slot created.');
    }

    public function assignSlot(Request $request, AdmissionAssessmentSlotService $service)
    {
        $data = $request->validate(['slot_id' => ['required', 'integer'], 'applicant_id' => ['required', 'exists:applicants,id']]);
        $service->assignApplicant((int) $data['slot_id'], Applicant::findOrFail($data['applicant_id']), $request->user());
        return back()->with('success', 'Candidate assigned to slot.');
    }

    public function evaluatorResponse(Request $request, AdmissionAssessmentSlotService $service)
    {
        $data = $request->validate(['invitation_id' => ['required', 'integer'], 'status' => ['required', 'in:accepted,declined,pending'], 'response_note' => ['nullable', 'string']]);
        $service->evaluatorResponse((int) $data['invitation_id'], $data['status'], $data['response_note'] ?? null);
        return back()->with('success', 'Evaluator response updated.');
    }

    public function buildGd(Request $request, AdmissionGdGroupService $service)
    {
        $data = $request->validate(['panel_id' => ['required', 'exists:admission_assessment_panels,id'], 'slot_id' => ['nullable', 'integer'], 'capacity' => ['required', 'integer', 'min:2']]);
        $groups = $service->build((int) $data['panel_id'], $data['slot_id'] ?? null, (int) $data['capacity'], $request->user()?->id);
        return back()->with('success', $groups->count() . ' GD group(s) built.');
    }

    public function submission(Request $request, AdmissionAssessmentSubmissionService $service)
    {
        $data = $request->validate([
            'applicant_id' => ['required', 'exists:applicants,id'],
            'panel_id' => ['nullable', 'exists:admission_assessment_panels,id'],
            'slot_id' => ['nullable', 'integer'],
            'submission_type' => ['required', 'string'],
            'artifact_url' => ['nullable', 'string'],
            'status' => ['required', 'string'],
            'originality_flag' => ['nullable', 'boolean'],
        ]);
        $service->markReceived(Applicant::findOrFail($data['applicant_id']), $data, $request->user());
        return back()->with('success', 'Assessment submission updated.');
    }
}
