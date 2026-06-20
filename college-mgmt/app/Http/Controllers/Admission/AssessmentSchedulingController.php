<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionAssessmentPanel;
use App\Models\Applicant;
use App\Models\SelectionSession;
use App\Models\User;
use App\Services\AdmissionAssessmentResourceService;
use App\Services\AdmissionAssessmentSlotService;
use App\Services\AdmissionAssessmentSubmissionService;
use App\Services\AdmissionGdGroupService;
use App\Services\AdmissionSensitiveAuditService;
use App\Services\DepartmentHierarchyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssessmentSchedulingController extends Controller
{
    public function __construct(private DepartmentHierarchyService $hierarchy) {}

    public function index(Request $request)
    {
        $canManageAssessmentScheduling = $this->canManageAssessmentScheduling($request);
        $assignments = DB::table('admission_assessment_slot_assignments')->latest()->limit(20)->get();
        $invitations = DB::table('admission_evaluator_invitations')->latest()->limit(20)->get();
        $conflicts = app(AdmissionAssessmentResourceService::class)->conflicts();
        $applicants = Applicant::with('user')
            ->when(! $this->hierarchy->canSeeAll($request->user(), 'ADM'), function ($query) use ($request) {
                $this->hierarchy->applyApplicantVisibility($query, $request->user(), 'ADM');
            })
            ->latest()
            ->limit(100)
            ->get();
        $resources = DB::table('admission_assessment_resources')->where('is_active', true)->get();
        $panels = AdmissionAssessmentPanel::with('members.user')->latest()->limit(15)->get();
        $evaluators = User::role(['evaluator', 'teacher', 'admission_head', 'admission_manager'])->orderBy('name')->limit(100)->get();
        $invitedUsers = User::whereIn('id', $invitations->pluck('user_id')->filter()->unique())->get();

        return view('admission.v0038.assessment-scheduling', [
            'canManageAssessmentScheduling' => $canManageAssessmentScheduling,
            'panels' => $panels,
            'sessions' => SelectionSession::latest()->limit(15)->get(),
            'slots' => DB::table('admission_assessment_slots')->latest()->paginate(15),
            'resources' => $resources,
            'applicants' => $applicants,
            'evaluators' => $evaluators,
            'assignments' => $assignments,
            'invitations' => $invitations,
            'conflicts' => $conflicts,
            'gdGroups' => DB::table('admission_gd_groups')->latest()->limit(20)->get(),
            'submissions' => DB::table('admission_assessment_submissions')->latest()->limit(20)->get(),
            'applicantNames' => $applicants->mapWithKeys(fn (Applicant $applicant) => [
                $applicant->id => trim(($applicant->application_number ?: 'Application') . ' - ' . ($applicant->user?->name ?: 'Applicant')),
            ]),
            'panelNames' => $panels->pluck('name', 'id'),
            'resourceNames' => $resources->pluck('name', 'id'),
            'userNames' => $evaluators->merge($invitedUsers)->pluck('name', 'id'),
        ]);
    }

    public function storeSlot(Request $request, AdmissionAssessmentSlotService $service)
    {
        $this->authorizeAssessmentSchedulingWrite($request);
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
        $this->authorizeAssessmentSchedulingWrite($request);
        $data = $request->validate(['slot_id' => ['required', 'integer'], 'applicant_id' => ['required', 'exists:applicants,id']]);
        $applicant = Applicant::findOrFail($data['applicant_id']);
        $this->guardApplicantScope($request, $applicant);
        $service->assignApplicant((int) $data['slot_id'], $applicant, $request->user());
        return back()->with('success', 'Candidate assigned to slot.');
    }

    public function bulkAssignSlot(Request $request, AdmissionAssessmentSlotService $service)
    {
        $this->authorizeAssessmentSchedulingWrite($request);
        $data = $request->validate([
            'slot_id' => ['required', 'integer'],
            'applicant_ids' => ['required', 'array', 'min:1'],
            'applicant_ids.*' => ['exists:applicants,id'],
        ]);
        $applicants = Applicant::whereIn('id', $data['applicant_ids'])->get();
        $applicants->each(fn (Applicant $applicant) => $this->guardApplicantScope($request, $applicant));
        $result = $service->bulkAssign((int) $data['slot_id'], $applicants, $request->user());

        return back()->with('success', "{$result['assigned']} candidate(s) assigned; ".count($result['blocked']).' blocked.');
    }

    public function evaluatorResponse(Request $request, AdmissionAssessmentSlotService $service)
    {
        $this->authorizeAssessmentSchedulingWrite($request);
        $data = $request->validate(['invitation_id' => ['required', 'integer'], 'status' => ['required', 'in:accepted,declined,pending'], 'response_note' => ['nullable', 'string']]);
        $service->evaluatorResponse((int) $data['invitation_id'], $data['status'], $data['response_note'] ?? null);
        return back()->with('success', 'Evaluator response updated.');
    }

    public function replaceEvaluator(Request $request, AdmissionAssessmentSlotService $service)
    {
        $this->authorizeAssessmentSchedulingWrite($request);
        $data = $request->validate(['invitation_id' => ['required', 'integer'], 'replacement_user_id' => ['required', 'exists:users,id']]);
        $service->replaceEvaluator((int) $data['invitation_id'], (int) $data['replacement_user_id'], $request->user());
        return back()->with('success', 'Replacement evaluator invited.');
    }

    public function reviewReschedule(Request $request, AdmissionAssessmentSlotService $service)
    {
        $this->authorizeAssessmentSchedulingWrite($request);
        $data = $request->validate(['request_id' => ['required', 'integer'], 'status' => ['required', 'in:approved,rejected']]);
        $service->reviewReschedule((int) $data['request_id'], $data['status'], $request->user());
        return back()->with('success', 'Reschedule request reviewed.');
    }

    public function checkIn(Request $request, AdmissionAssessmentSlotService $service)
    {
        $this->authorizeAssessmentSchedulingWrite($request);
        $data = $request->validate(['assignment_id' => ['required', 'integer'], 'status' => ['required', 'string']]);
        $service->checkIn((int) $data['assignment_id'], $data['status'], $request->user());
        return back()->with('success', 'Candidate lifecycle updated.');
    }

    public function buildGd(Request $request, AdmissionGdGroupService $service)
    {
        $this->authorizeAssessmentSchedulingWrite($request);
        $data = $request->validate(['panel_id' => ['required', 'exists:admission_assessment_panels,id'], 'slot_id' => ['nullable', 'integer'], 'capacity' => ['required', 'integer', 'min:2']]);
        $groups = $service->build((int) $data['panel_id'], $data['slot_id'] ?? null, (int) $data['capacity'], $request->user()?->id);
        return back()->with('success', $groups->count() . ' GD group(s) built.');
    }

    public function submission(Request $request, AdmissionAssessmentSubmissionService $service, AdmissionSensitiveAuditService $audit)
    {
        $this->authorizeAssessmentSchedulingWrite($request);
        $data = $request->validate([
            'applicant_id' => ['required', 'exists:applicants,id'],
            'panel_id' => ['nullable', 'exists:admission_assessment_panels,id'],
            'slot_id' => ['nullable', 'integer'],
            'submission_type' => ['required', 'string'],
            'artifact_url' => ['nullable', 'string'],
            'status' => ['required', 'string'],
            'originality_flag' => ['nullable', 'boolean'],
        ]);
        $applicant = Applicant::findOrFail($data['applicant_id']);
        $this->guardApplicantScope($request, $applicant);
        $service->markReceived($applicant, $data, $request->user());
        $audit->record('assessment_submission_updated', $applicant, $request->user(), 'Assessment submission status changed.', [], $data);
        return back()->with('success', 'Assessment submission updated.');
    }

    private function canManageAssessmentScheduling(Request $request): bool
    {
        return $this->hierarchy->canApproveAdmission($request->user());
    }

    private function authorizeAssessmentSchedulingWrite(Request $request): void
    {
        abort_unless($this->canManageAssessmentScheduling($request), 403);
    }

    private function guardApplicantScope(Request $request, Applicant $applicant): void
    {
        abort_unless($this->hierarchy->canViewAssignedUser($request->user(), 'ADM', $applicant->assigned_to, false), 403);
    }
}
