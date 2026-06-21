<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Models\AcademicPmcWorkItem;
use App\Models\AcademicPmcApproval;
use App\Models\AcademicPmcCourseAllocationException;
use App\Models\AcademicPmcCourseDeliveryCheckpoint;
use App\Models\AcademicPmcDataReconciliationCheck;
use App\Models\AcademicPmcDataReconciliationRun;
use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcCourseGroupAdjustment;
use App\Models\AcademicPmcFacultyAssignmentAcknowledgement;
use App\Models\AcademicPmcOperatingRecord;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\AcademicPmcFacultyAvailabilityRequest;
use App\Models\AcademicPmcFacultyLoadReview;
use App\Models\AcademicPmcPlanningCycle;
use App\Models\AcademicPmcReadinessItem;
use App\Models\AcademicPmcRemedialAction;
use App\Models\AcademicPmcReviewGovernanceRecord;
use App\Models\AcademicPmcRoomReadinessReview;
use App\Models\AcademicPmcStudentIntervention;
use App\Models\AcademicPmcStudentBasketAcknowledgement;
use App\Models\AcademicPmcStudentSuccessPlan;
use App\Models\AcademicPmcTimetableChangeRequest;
use App\Models\AcademicPmcTimetableConstraint;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\AcademicPmcTimetableNotification;
use App\Models\AcademicPmcTimetableResolutionAction;
use App\Models\Department;
use App\Models\DepartmentActivityLog;
use App\Models\TimetableVersion;
use App\Services\AcademicAccessPolicyService;
use App\Services\AcademicPmcAccessPolicyService;
use App\Services\AcademicPmcOperatingService;
use App\Services\AcademicPmcTimetableV041Service;
use App\Services\AcademicPmcV003Service;
use App\Services\AcademicPmcV004Service;
use Illuminate\Http\Request;
use Throwable;

class PmcOperatingController extends Controller
{
    public function __construct(
        private AcademicAccessPolicyService $policy,
        private AcademicPmcOperatingService $pmc,
        private AcademicPmcV003Service $pmcV003,
        private AcademicPmcV004Service $pmcV004,
        private AcademicPmcAccessPolicyService $pmcPolicy,
        private AcademicPmcTimetableV041Service $pmcTimetableV041
    ) {}

    public function index(Request $request)
    {
        $this->authorizePmc($request);

        return view('academics.pmc.dashboard', $this->pmc->dashboard($request->user()));
    }

    public function curriculumReadiness(Request $request)
    {
        return $this->section($request, 'curriculum-readiness');
    }

    public function programs(Request $request)
    {
        return $this->section($request, 'programs');
    }

    public function facultyAllocation(Request $request)
    {
        return $this->section($request, 'faculty-allocation');
    }

    public function timetableReadiness(Request $request)
    {
        return $this->section($request, 'timetable-readiness');
    }

    public function studentMonitoring(Request $request)
    {
        return $this->section($request, 'student-monitoring');
    }

    public function reports(Request $request)
    {
        $this->authorizePmc($request);

        $legacyReports = collect($this->pmc->reports($request->user()))
            ->map(fn ($report, $key) => $report + ['key' => is_string($key) ? $key : str($report['label'])->slug('_')->toString()])
            ->values();

        return view('academics.pmc.v003.reports', [
            'reports' => $legacyReports->merge($this->pmcV003->reports()),
        ]);
    }

    public function command(Request $request)
    {
        $this->authorizePmc($request);

        return view('academics.pmc.v004.command', $this->pmcV004->command($request->user()));
    }

    public function workbench(Request $request)
    {
        $this->authorizePmc($request);

        return view('academics.pmc.v003.workbench', $this->pmcV003->workbench($request->query('type', 'all')));
    }

    public function curriculumGovernance(Request $request)
    {
        $this->authorizePmc($request);

        return view('academics.pmc.v003.curriculum', $this->pmcV003->curriculum());
    }

    public function refreshCurriculumValidations(Request $request)
    {
        $this->pmcPolicy->authorizeWrite($request->user());
        $result = $this->pmcV003->refreshCurriculumValidations($request->user());

        return back()->with('success', "Curriculum validations refreshed for {$result['plans']} plan(s), {$result['validations']} check(s).");
    }

    public function facultyWorkload(Request $request)
    {
        $this->authorizePmc($request);

        return view('academics.pmc.v003.faculty', $this->pmcV003->faculty());
    }

    public function timetableControl(Request $request)
    {
        $this->authorizePmc($request);

        return view('academics.pmc.v003.timetable', $this->pmcV003->timetable());
    }

    public function studentSuccess(Request $request)
    {
        $this->authorizePmc($request);

        return view('academics.pmc.v003.student-success', $this->pmcV003->studentSuccess());
    }

    public function reviews(Request $request)
    {
        $this->authorizePmc($request);

        return view('academics.pmc.v003.reviews', $this->pmcV003->reviews());
    }

    public function storeWorkItem(Request $request)
    {
        $this->authorizePmc($request);
        $data = $request->validate([
            'work_type' => 'required|string|max:100',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'owner_user_id' => 'nullable|exists:users,id',
            'priority' => 'nullable|string|max:40',
            'status' => 'nullable|string|max:60',
            'severity' => 'nullable|string|max:40',
            'due_at' => 'nullable|date',
        ]);
        $this->pmcV003->createWorkItem($request->user(), $data);

        return back()->with('success', 'PMC work item created.');
    }

    public function updateWorkItem(Request $request, AcademicPmcWorkItem $item)
    {
        $this->authorizePmc($request);
        $data = $request->validate([
            'owner_user_id' => 'nullable|exists:users,id',
            'priority' => 'required|string|max:40',
            'status' => 'required|string|max:60',
            'severity' => 'required|string|max:40',
            'due_at' => 'nullable|date',
        ]);
        $this->pmcV003->updateWorkItem($item, $data);

        return back()->with('success', 'PMC work item updated.');
    }

    public function storeReview(Request $request)
    {
        $this->authorizePmc($request);
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'review_type' => 'required|string|max:80',
            'scheduled_for' => 'nullable|date',
            'agenda' => 'nullable|string|max:3000',
        ]);
        $this->pmcV003->createReview($request->user(), $data);

        return back()->with('success', 'PMC review created.');
    }

    public function storeSavedView(Request $request)
    {
        $this->authorizePmc($request);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'surface' => 'required|string|max:80',
            'filters' => 'nullable|array',
            'is_default' => 'nullable|boolean',
        ]);
        $this->pmcV003->saveView($request->user(), $data);

        return back()->with('success', 'PMC saved view stored.');
    }

    public function export(Request $request, string $report)
    {
        $this->authorizePmc($request);

        return $this->pmcV004->export($report, $request->user(), $request->query());
    }

    public function v004Surface(Request $request, string $surface)
    {
        $this->authorizePmc($request);

        return view('academics.pmc.v004.surface', $this->pmcV004->surface($request->user(), $surface, $request->query()));
    }

    public function v004Reviews(Request $request)
    {
        $this->authorizePmc($request);

        return view('academics.pmc.v004.reviews', $this->pmcV004->reviews($request->user()));
    }

    public function approvePmcMinutes(Request $request, AcademicPmcReviewGovernanceRecord $minutes)
    {
        $this->pmcPolicy->authorizeWrite($request->user());
        $data = $request->validate(['approval_note' => 'nullable|string|max:2000']);
        $this->pmcV004->approveMinutes($request->user(), $minutes, $data['approval_note'] ?? null);

        return back()->with('success', 'PMC minutes approved and follow-up action created.');
    }

    public function storePmcActionDependency(Request $request, AcademicPmcWorkItem $item)
    {
        $this->pmcPolicy->authorizeWrite($request->user());
        $data = $request->validate([
            'depends_on_work_item_id' => 'required|exists:academic_pmc_work_items,id',
            'dependency_type' => 'nullable|string|max:80',
            'reason' => 'nullable|string|max:2000',
        ]);
        $dependsOn = AcademicPmcWorkItem::findOrFail($data['depends_on_work_item_id']);
        $this->pmcV004->addActionDependency($request->user(), $item, $dependsOn, $data);

        return back()->with('success', 'PMC action dependency added.');
    }

    public function storePmcActionEvidence(Request $request, AcademicPmcWorkItem $item)
    {
        $this->pmcPolicy->authorizeWrite($request->user());
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'evidence_type' => 'nullable|string|max:100',
            'evidence_note' => 'nullable|string|max:3000',
            'file_path' => 'nullable|string|max:500',
        ]);
        $this->pmcV004->addActionEvidence($request->user(), $item, $data);

        return back()->with('success', 'PMC action evidence added.');
    }

    public function verifyPmcActionClosure(Request $request, AcademicPmcWorkItem $item)
    {
        $this->pmcPolicy->authorizeWrite($request->user());
        $data = $request->validate([
            'status' => 'nullable|in:verified,closed,done',
            'verification_note' => 'nullable|string|max:2000',
        ]);
        $this->pmcV004->verifyActionClosure($request->user(), $item, $data);

        return back()->with('success', 'PMC action closure verified.');
    }

    public function v004Approvals(Request $request)
    {
        $this->authorizePmc($request);

        return view('academics.pmc.v004.approvals', $this->pmcV004->approvals($request->query()));
    }

    public function v004Analytics(Request $request)
    {
        $this->authorizePmc($request);

        return view('academics.pmc.v004.analytics', $this->pmcV004->analytics($request->user(), $request->query()));
    }

    public function v004Automation(Request $request)
    {
        $this->authorizePmc($request);

        return view('academics.pmc.v004.automation', $this->pmcV004->automation());
    }

    public function v004PolicyAudit(Request $request)
    {
        $this->authorizePmc($request);

        return view('academics.pmc.v004.policy-audit', $this->pmcV004->policyAudit());
    }

    public function storeV004Record(Request $request)
    {
        $this->pmcPolicy->authorizeWrite($request->user());
        $data = $request->validate([
            'record_type' => 'required|string|max:100',
            'category' => 'nullable|string|max:100',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:3000',
            'program_id' => 'nullable|exists:programs,id',
            'batch_id' => 'nullable|exists:batches,id',
            'term_id' => 'nullable|exists:terms,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'student_id' => 'nullable|exists:students,id',
            'teacher_id' => 'nullable|exists:teachers,id',
            'owner_user_id' => 'nullable|exists:users,id',
            'status' => 'nullable|string|max:80',
            'priority' => 'nullable|string|max:40',
            'risk_band' => 'nullable|string|max:40',
            'score' => 'nullable|integer|min:0|max:100',
            'due_at' => 'nullable|date',
        ]);
        $record = $this->pmcV004->createRecord($request->user(), $data);

        return back()->with('success', 'PMC v0.04 record created: ' . $record->title);
    }

    public function updateV004Record(Request $request, AcademicPmcOperatingRecord $record)
    {
        $this->pmcPolicy->authorizeWrite($request->user(), $record);
        $data = $request->validate([
            'status' => 'required|string|max:80',
            'priority' => 'nullable|string|max:40',
            'risk_band' => 'nullable|string|max:40',
            'score' => 'nullable|integer|min:0|max:100',
            'due_at' => 'nullable|date',
        ]);
        $this->pmcV004->updateRecord($request->user(), $record, $data);

        return back()->with('success', 'PMC record updated.');
    }

    public function createWorkItemFromRecord(Request $request, AcademicPmcOperatingRecord $record)
    {
        $this->pmcPolicy->authorizeWrite($request->user(), $record);
        $this->pmcV004->createWorkItemFromRecord($request->user(), $record);

        return back()->with('success', 'PMC blocker converted to work item.');
    }

    public function storePlanningCycle(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'cycle_type' => 'required|in:annual_plan,semester_readiness,program_term_plan,academic_calendar,elective_plan,assessment_calendar,resource_readiness',
            'academic_year' => 'nullable|string|max:40',
            'program_id' => 'nullable|exists:programs,id',
            'batch_id' => 'nullable|exists:batches,id',
            'term_id' => 'nullable|exists:terms,id',
            'owner_user_id' => 'nullable|exists:users,id',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
        ]);
        $this->pmcPolicy->authorizeWriteScope($request->user(), $data);
        $cycle = $this->pmcV004->createPlanningCycle($request->user(), $data);

        return back()->with('success', 'PMC planning cycle created with readiness checklist: ' . $cycle->title);
    }

    public function updatePlanningCycleStatus(Request $request, AcademicPmcPlanningCycle $cycle)
    {
        $this->pmcPolicy->authorizeWriteScope($request->user(), [
            'program_id' => $cycle->program_id,
            'batch_id' => $cycle->batch_id,
            'term_id' => $cycle->term_id,
        ]);
        $data = $request->validate([
            'status' => 'required|in:draft,branch_review,pmc_review,dean_review,approved,published,revised,closed,rejected,returned,revision_requested',
            'decision_note' => 'nullable|string|max:2000',
        ]);
        $this->pmcV004->updatePlanningCycleStatus($request->user(), $cycle, $data['status'], $data['decision_note'] ?? null);

        return back()->with('success', 'PMC planning cycle moved to ' . $data['status'] . '.');
    }

    public function updateReadinessItem(Request $request, AcademicPmcReadinessItem $item)
    {
        $cycle = $item->planningCycle;
        $this->pmcPolicy->authorizeWriteScope($request->user(), [
            'program_id' => $cycle?->program_id,
            'batch_id' => $cycle?->batch_id,
            'term_id' => $cycle?->term_id,
        ]);
        $data = $request->validate([
            'status' => 'required|in:open,in_progress,blocked,done,closed,cancelled',
            'completion_percent' => 'required|integer|min:0|max:100',
            'is_blocker' => 'nullable|boolean',
            'evidence_note' => 'nullable|string|max:2000',
        ]);
        $data['evidence'] = ['note' => $data['evidence_note'] ?? null, 'updated_by' => $request->user()->id];
        unset($data['evidence_note']);
        $this->pmcV004->updateReadinessItem($request->user(), $item, $data);

        return back()->with('success', 'Readiness item updated.');
    }

    public function createWorkItemFromReadiness(Request $request, AcademicPmcReadinessItem $item)
    {
        $cycle = $item->planningCycle;
        $this->pmcPolicy->authorizeWriteScope($request->user(), [
            'program_id' => $cycle?->program_id,
            'batch_id' => $cycle?->batch_id,
            'term_id' => $cycle?->term_id,
        ]);
        $this->pmcV004->createWorkItemFromReadiness($request->user(), $item);

        return back()->with('success', 'Readiness blocker converted to PMC work item.');
    }

    public function decideApproval(Request $request, AcademicPmcApproval $approval)
    {
        $this->pmcPolicy->authorizeWrite($request->user());
        $data = $request->validate([
            'status' => 'required|in:approved,rejected,returned,evidence_requested,escalated',
            'decision_reason' => 'nullable|string|max:1000',
        ]);
        $this->pmcV004->decideApproval($request->user(), $approval, $data['status'], $data['decision_reason'] ?? null);

        return back()->with('success', 'PMC approval decision recorded.');
    }

    public function refreshAutomation(Request $request)
    {
        $this->pmcPolicy->authorizeWrite($request->user());
        $result = $this->pmcV004->refreshSignals($request->user());

        return back()->with('success', "PMC automation refreshed {$result['rules']} rules; {$result['created']} new executions.");
    }

    public function refreshStudentSuccessSignals(Request $request)
    {
        $this->pmcPolicy->authorizeWrite($request->user());
        $result = $this->pmcV004->refreshStudentSuccessSignals($request->user());

        return back()->with('success', "Student success refreshed for {$result['students']} student(s); {$result['critical']} critical risk.");
    }

    public function storeStudentIntervention(Request $request, AcademicPmcStudentSuccessPlan $plan)
    {
        $this->pmcPolicy->authorizeWriteScope($request->user(), [
            'program_id' => $plan->program_id,
            'batch_id' => $plan->batch_id,
        ]);
        $data = $request->validate([
            'intervention_type' => 'required|in:mentor_meeting,parent_call,remedial_class,attendance_warning,counselling_referral,faculty_follow_up,program_director_review',
            'owner_user_id' => 'nullable|exists:users,id',
            'priority' => 'nullable|in:low,normal,high,critical',
            'reason' => 'nullable|string|max:2000',
            'action_plan' => 'nullable|string|max:3000',
            'due_at' => 'nullable|date',
        ]);
        $intervention = $this->pmcV004->createStudentIntervention($request->user(), $plan, $data);

        return back()->with('success', 'Student intervention created #' . $intervention->id);
    }

    public function updateStudentIntervention(Request $request, AcademicPmcStudentIntervention $intervention)
    {
        $this->pmcPolicy->authorizeWriteScope($request->user(), [
            'program_id' => $intervention->program_id,
            'batch_id' => $intervention->batch_id,
        ]);
        $data = $request->validate([
            'status' => 'required|in:open,assigned,mentor_contacted,parent_contacted,remedial_assigned,under_review,resolved,closed,escalated,cancelled',
            'evidence_note' => 'nullable|string|max:2000',
        ]);
        $data['evidence'] = ['note' => $data['evidence_note'] ?? null, 'updated_by' => $request->user()->id];
        unset($data['evidence_note']);
        $this->pmcV004->updateStudentIntervention($request->user(), $intervention, $data);

        return back()->with('success', 'Student intervention updated.');
    }

    public function storeParentEscalation(Request $request, AcademicPmcStudentSuccessPlan $plan)
    {
        $this->pmcPolicy->authorizeWriteScope($request->user(), [
            'program_id' => $plan->program_id,
            'batch_id' => $plan->batch_id,
        ]);
        $data = $request->validate([
            'intervention_id' => 'nullable|exists:academic_pmc_student_interventions,id',
            'owner_user_id' => 'nullable|exists:users,id',
            'guardian_name' => 'nullable|string|max:255',
            'guardian_phone' => 'nullable|string|max:50',
            'reason' => 'nullable|string|max:160',
            'scheduled_at' => 'nullable|date',
        ]);
        $escalation = $this->pmcV004->createParentEscalation($request->user(), $plan, $data);

        return back()->with('success', 'Parent escalation scheduled #' . $escalation->id);
    }

    public function refreshCourseDeliveryCheckpoints(Request $request)
    {
        $this->pmcPolicy->authorizeWrite($request->user());
        $result = $this->pmcV004->refreshCourseDeliveryCheckpoints($request->user());

        return back()->with('success', "Course delivery refreshed for {$result['subjects']} subject(s); {$result['critical']} critical risk.");
    }

    public function storeRemedialAction(Request $request, AcademicPmcCourseDeliveryCheckpoint $checkpoint)
    {
        $this->pmcPolicy->authorizeWriteScope($request->user(), [
            'program_id' => $checkpoint->program_id,
            'batch_id' => $checkpoint->batch_id,
            'term_id' => $checkpoint->term_id,
            'subject_id' => $checkpoint->subject_id,
        ]);
        $data = $request->validate([
            'action_type' => 'required|in:makeup_session,attendance_intervention,marks_followup,feedback_improvement,faculty_followup,remedial_class,delivery_review',
            'owner_user_id' => 'nullable|exists:users,id',
            'priority' => 'nullable|in:low,normal,high,critical',
            'reason' => 'nullable|string|max:2000',
            'action_plan' => 'nullable|string|max:3000',
            'due_at' => 'nullable|date',
        ]);
        $action = $this->pmcV004->createRemedialAction($request->user(), $checkpoint, $data);

        return back()->with('success', 'Course delivery remedial action created #' . $action->id);
    }

    public function updateRemedialAction(Request $request, AcademicPmcRemedialAction $action)
    {
        $checkpoint = $action->checkpoint;
        $this->pmcPolicy->authorizeWriteScope($request->user(), [
            'program_id' => $checkpoint?->program_id,
            'batch_id' => $checkpoint?->batch_id,
            'term_id' => $checkpoint?->term_id,
            'subject_id' => $action->subject_id,
        ]);
        $data = $request->validate([
            'status' => 'required|in:open,assigned,faculty_contacted,makeup_scheduled,marks_collected,under_review,resolved,closed,escalated,cancelled',
            'evidence_note' => 'nullable|string|max:2000',
        ]);
        $data['evidence'] = ['note' => $data['evidence_note'] ?? null, 'updated_by' => $request->user()->id];
        unset($data['evidence_note']);
        $this->pmcV004->updateRemedialAction($request->user(), $action, $data);

        return back()->with('success', 'Course delivery remedial action updated.');
    }

    public function v041Dashboard(Request $request)
    {
        $this->authorizePmc($request);

        return view('academics.pmc.v041.dashboard', $this->pmcTimetableV041->dashboard($request->user()));
    }

    public function v041Surface(Request $request, string $surface)
    {
        $this->authorizePmc($request);

        return view('academics.pmc.v041.surface', $this->pmcTimetableV041->surface($request->user(), $surface, $request->query()));
    }

    public function v041ExportSurface(Request $request, string $surface)
    {
        $this->authorizePmc($request);

        return $this->pmcTimetableV041->exportSurface($request->user(), $surface, $request->query());
    }

    public function v044StudentTimetable(Request $request)
    {
        return view('academics.pmc.v041.scoped-timetable', $this->pmcTimetableV041->studentScopedTimetable($request->user(), $request->query()));
    }

    public function v090StudentCourseBasket(Request $request)
    {
        return view('academics.pmc.v041.student-course-basket', $this->pmcTimetableV041->studentCourseBasketSelfService($request->user(), $request->query()));
    }

    public function v090SubmitStudentBasketAcknowledgement(Request $request)
    {
        $data = $request->validate([
            'student_course_allocation_id' => 'nullable|exists:academic_pmc_student_course_allocations,id',
            'timetable_version_id' => 'nullable|exists:timetable_versions,id',
            'generation_run_id' => 'nullable|exists:academic_pmc_timetable_generation_runs,id',
            'acknowledgement_type' => 'required|in:allocation_review,timetable_acknowledgement,objection,add_drop_request,waitlist_followup',
            'reason' => 'nullable|string|max:255',
            'student_note' => 'nullable|string|max:2000',
        ]);

        $this->pmcTimetableV041->submitStudentBasketAcknowledgement($request->user(), $data);

        return back()->with('success', 'Your course basket response has been submitted to PMC.');
    }

    public function v091StudentElectiveChoices(Request $request)
    {
        return view('academics.pmc.v041.student-elective-choices', $this->pmcTimetableV041->studentElectiveChoicePortal($request->user(), $request->query()));
    }

    public function v091SubmitStudentElectiveChoices(Request $request)
    {
        $data = $request->validate([
            'term_id' => 'required|exists:terms,id',
            'subject_ids' => 'required|array|min:1',
            'subject_ids.*' => 'exists:subjects,id',
        ]);

        $this->pmcTimetableV041->submitStudentElectiveChoices($request->user(), $data);

        return back()->with('success', 'Your ranked elective choices have been submitted.');
    }

    public function v090ReviewStudentBasketAcknowledgement(Request $request, AcademicPmcStudentBasketAcknowledgement $acknowledgement)
    {
        $data = $request->validate([
            'status' => 'required|in:under_review,approved,rejected,resolved,cancelled',
            'pmc_note' => 'nullable|string|max:2000',
        ]);

        $this->pmcTimetableV041->reviewStudentBasketAcknowledgement($request->user(), $acknowledgement, $data['status'], $data['pmc_note'] ?? null);

        return back()->with('success', 'Student course basket request reviewed.');
    }

    public function v044FacultyTimetable(Request $request)
    {
        return view('academics.pmc.v041.scoped-timetable', $this->pmcTimetableV041->facultyScopedTimetable($request->user(), $request->query()));
    }

    public function v044OfficialTimetable(Request $request)
    {
        $this->authorizePmc($request);

        return view('academics.pmc.v041.scoped-timetable', $this->pmcTimetableV041->officialTimetableAudience($request->user(), $request->query()));
    }

    public function v092DataReconciliation(Request $request)
    {
        $this->authorizePmc($request);

        return view('academics.pmc.v041.data-reconciliation', $this->pmcTimetableV041->dataReconciliationSurface($request->user(), $request->query()));
    }

    public function v092RefreshDataReconciliation(Request $request)
    {
        $this->authorizePmc($request);
        $run = AcademicPmcDataReconciliationRun::create([
            'source' => 'manual_ui_refresh',
            'status' => 'running',
            'repair_requested' => false,
            'started_by' => $request->user()->id,
            'started_at' => now(),
            'metadata' => ['route' => 'academics.pmc.data-reconciliation.refresh'],
        ]);

        try {
            $result = $this->pmcTimetableV041->refreshDataReconciliation($request->user());
            $run->update([
                'status' => 'completed',
                'finished_at' => now(),
                'checks_count' => (int) $result['checks'],
                'mismatch_count' => (int) $result['mismatches'],
                'critical_count' => (int) $result['critical'],
            ]);

            return back()->with('success', "Data reconciliation refreshed: {$result['checks']} checks, {$result['mismatches']} mismatches.");
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'failure_reason' => substr($exception->getMessage(), 0, 255),
                'metadata' => ['route' => 'academics.pmc.data-reconciliation.refresh', 'exception' => get_class($exception)],
            ]);

            throw $exception;
        }
    }

    public function v095ExportDataReconciliation(Request $request)
    {
        $this->authorizePmc($request);

        return $this->pmcTimetableV041->exportDataReconciliation($request->user(), $request->query());
    }

    public function v102ExportDataReconciliationRuns(Request $request)
    {
        $this->authorizePmc($request);

        return $this->pmcTimetableV041->exportDataReconciliationRuns($request->user(), $request->query());
    }

    public function v107ExportDataReconciliationAudit(Request $request)
    {
        $this->authorizePmc($request);

        return $this->pmcTimetableV041->exportDataReconciliationAudit($request->user(), $request->query());
    }

    public function v093RepairDataReconciliation(Request $request, AcademicPmcDataReconciliationCheck $check)
    {
        $this->authorizePmc($request);
        $run = AcademicPmcDataReconciliationRun::create([
            'source' => 'manual_ui_repair',
            'status' => 'running',
            'repair_requested' => true,
            'started_by' => $request->user()->id,
            'started_at' => now(),
            'metadata' => [
                'route' => 'academics.pmc.data-reconciliation.repair',
                'check_key' => $check->check_key,
            ],
        ]);

        try {
            $result = $this->pmcTimetableV041->repairDataReconciliation($request->user(), $check);
            $run->update([
                'status' => 'completed',
                'finished_at' => now(),
                'checks_count' => AcademicPmcDataReconciliationCheck::count(),
                'mismatch_count' => AcademicPmcDataReconciliationCheck::sum('mismatch_count'),
                'critical_count' => AcademicPmcDataReconciliationCheck::where('severity', 'critical')->count(),
                'repaired_count' => (int) $result['repaired'],
                'metadata' => [
                    'route' => 'academics.pmc.data-reconciliation.repair',
                    'check_key' => $check->check_key,
                    'message' => $result['message'],
                ],
            ]);

            return back()->with('success', $result['message']);
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'failure_reason' => substr($exception->getMessage(), 0, 255),
                'metadata' => [
                    'route' => 'academics.pmc.data-reconciliation.repair',
                    'check_key' => $check->check_key,
                    'exception' => get_class($exception),
                ],
            ]);

            throw $exception;
        }
    }

    public function v104MarkReconciliationRunFailed(Request $request, AcademicPmcDataReconciliationRun $run)
    {
        $this->authorizePmc($request);
        $data = $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        if ($run->status !== 'running') {
            return back()->with('error', 'Only running reconciliation runs can be marked failed.');
        }

        if ($run->started_at && $run->started_at->greaterThan(now()->subMinutes(30))) {
            return back()->with('error', 'Only stale reconciliation runs older than 30 minutes can be marked failed.');
        }

        $metadata = $run->metadata ?? [];
        $metadata['manual_close'] = [
            'closed_by' => $request->user()->id,
            'closed_at' => now()->toDateTimeString(),
            'reason' => $data['reason'],
        ];

        $run->update([
            'status' => 'failed',
            'finished_at' => now(),
            'failure_reason' => $data['reason'],
            'metadata' => $metadata,
        ]);
        DepartmentActivityLog::create([
            'department_id' => Department::where('code', 'ACAD')->value('id') ?: Department::query()->value('id'),
            'actor_user_id' => $request->user()->id,
            'action' => 'academic_pmc_v105_reconciliation_stale_run_closed',
            'subject_type' => get_class($run),
            'subject_id' => $run->id,
            'description' => 'PMC marked stale reconciliation run as failed.',
            'metadata' => [
                'reason' => $data['reason'],
                'source' => $run->source,
                'started_at' => optional($run->started_at)->toDateTimeString(),
                'version' => 'PMC OS v0.105',
            ],
        ]);

        return back()->with('success', 'Stale reconciliation run marked failed.');
    }

    public function v046FacultyAvailability(Request $request)
    {
        $this->authorizePmc($request);

        return view('academics.pmc.v041.faculty-availability', $this->pmcTimetableV041->facultyAvailabilitySurface($request->user(), $request->query()));
    }

    public function v046MyAvailability(Request $request)
    {
        return view('academics.pmc.v041.faculty-availability', $this->pmcTimetableV041->facultyOwnAvailabilitySurface($request->user()));
    }

    public function v041StoreAllocationBatch(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'program_id' => 'required|exists:programs,id',
            'batch_id' => 'nullable|exists:batches,id',
            'term_id' => 'nullable|exists:terms,id',
            'subject_ids' => 'required|array|min:1',
            'subject_ids.*' => 'exists:subjects,id',
            'max_credits' => 'nullable|integer|min:1|max:80',
        ]);
        $this->pmcPolicy->authorizeWriteScope($request->user(), $data);
        $batch = $this->pmcTimetableV041->bulkAllocateCore($request->user(), $data);

        return back()->with('success', "Course allocation batch created with {$batch->core_allocations} allocations.");
    }

    public function v042AllocateElectives(Request $request)
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'program_id' => 'nullable|exists:programs,id',
            'batch_id' => 'nullable|exists:batches,id',
            'term_id' => 'nullable|exists:terms,id',
            'subject_ids' => 'nullable|array',
            'subject_ids.*' => 'exists:subjects,id',
            'capacity_per_subject' => 'nullable|integer|min:1|max:500',
            'max_electives_per_student' => 'nullable|integer|min:1|max:20',
        ]);
        $this->pmcPolicy->authorizeWriteScope($request->user(), $data);
        $result = $this->pmcTimetableV041->allocateElectives($request->user(), $data);

        return back()->with('success', "Electives allocated: {$result['allocated']} allocated, {$result['waitlisted']} waitlisted.");
    }

    public function v050RequestCourseAllocationException(Request $request)
    {
        $data = $request->validate([
            'student_id' => 'required|exists:students,id',
            'subject_id' => 'required|exists:subjects,id',
            'term_id' => 'nullable|exists:terms,id',
            'exception_type' => 'required|in:add,drop,repeat,backlog,improvement,audit,open_elective',
            'credit_delta' => 'nullable|integer|min:0|max:80',
            'reason' => 'required|string|max:2000',
        ]);
        $exception = $this->pmcTimetableV041->requestCourseAllocationException($request->user(), $data);

        return back()->with('success', 'Course allocation exception requested #' . $exception->id);
    }

    public function v050DecideCourseAllocationException(Request $request, AcademicPmcCourseAllocationException $exception)
    {
        $data = $request->validate([
            'status' => 'required|in:approved,rejected,returned',
            'decision_note' => 'required|string|max:2000',
        ]);
        $this->pmcTimetableV041->decideCourseAllocationException($request->user(), $exception, $data['status'], $data['decision_note']);

        return back()->with('success', 'Course allocation exception ' . $data['status'] . '.');
    }

    public function v041StoreGroup(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'group_type' => 'required|string|max:80',
            'program_id' => 'nullable|exists:programs,id',
            'batch_id' => 'nullable|exists:batches,id',
            'term_id' => 'nullable|exists:terms,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'min_capacity' => 'nullable|integer|min:1|max:500',
            'max_capacity' => 'nullable|integer|min:1|max:500',
            'current_strength' => 'nullable|integer|min:0|max:500',
            'status' => 'nullable|string|max:80',
            'is_locked' => 'nullable|boolean',
        ]);
        $this->pmcPolicy->authorizeWriteScope($request->user(), $data);
        $group = $this->pmcTimetableV041->createGroup($request->user(), $data);

        return back()->with('success', 'Course group created: ' . $group->name);
    }

    public function v051RequestCourseGroupAdjustment(Request $request)
    {
        $data = $request->validate([
            'course_group_id' => 'required|exists:academic_pmc_course_groups,id',
            'target_course_group_id' => 'nullable|exists:academic_pmc_course_groups,id',
            'student_id' => 'nullable|exists:students,id',
            'adjustment_type' => 'required|in:split,merge,rebalance,move_student,lock,unlock',
            'strength_delta' => 'nullable|integer|min:0|max:500',
            'reason' => 'required|string|max:2000',
        ]);
        $adjustment = $this->pmcTimetableV041->requestCourseGroupAdjustment($request->user(), $data);

        return back()->with('success', 'Course group adjustment requested #' . $adjustment->id);
    }

    public function v051DecideCourseGroupAdjustment(Request $request, AcademicPmcCourseGroupAdjustment $adjustment)
    {
        $data = $request->validate([
            'status' => 'required|in:approved,rejected,returned',
            'decision_note' => 'required|string|max:2000',
        ]);
        $this->pmcTimetableV041->decideCourseGroupAdjustment($request->user(), $adjustment, $data['status'], $data['decision_note']);

        return back()->with('success', 'Course group adjustment ' . $data['status'] . '.');
    }

    public function v042AutoBuildGroups(Request $request)
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'group_prefix' => 'nullable|string|max:120',
            'group_type' => 'required|string|max:80',
            'strategy' => 'nullable|string|max:80',
            'program_id' => 'nullable|exists:programs,id',
            'batch_id' => 'nullable|exists:batches,id',
            'term_id' => 'nullable|exists:terms,id',
            'subject_id' => 'required|exists:subjects,id',
            'min_capacity' => 'nullable|integer|min:1|max:500',
            'max_capacity' => 'nullable|integer|min:1|max:500',
        ]);
        $this->pmcPolicy->authorizeWriteScope($request->user(), $data);
        $run = $this->pmcTimetableV041->autoBuildGroups($request->user(), $data);

        return back()->with('success', "Auto group build completed with {$run->groups_created} groups and {$run->warnings_count} warnings.");
    }

    public function v041AssignFaculty(Request $request)
    {
        $data = $request->validate([
            'course_group_id' => 'required|exists:academic_pmc_course_groups,id',
            'teacher_id' => 'required|exists:teachers,id',
            'assignment_role' => 'required|string|max:80',
            'assignment_source' => 'nullable|string|max:80',
            'approval_status' => 'nullable|string|max:80',
            'weekly_hours' => 'nullable|integer|min:0|max:80',
            'is_backup' => 'nullable|boolean',
            'notes' => 'nullable|string|max:2000',
        ]);
        $group = AcademicPmcCourseGroup::findOrFail($data['course_group_id']);
        $this->pmcPolicy->authorizeWriteScope($request->user(), [
            'program_id' => $group->program_id,
            'batch_id' => $group->batch_id,
            'term_id' => $group->term_id,
            'subject_id' => $group->subject_id,
        ]);
        $assignment = $this->pmcTimetableV041->assignFaculty($request->user(), $data);

        return back()->with('success', 'Faculty assigned to section/group #' . $assignment->course_group_id);
    }

    public function v052RequestFacultyAssignmentAcknowledgement(Request $request, AcademicPmcGroupFacultyAssignment $assignment)
    {
        $ack = $this->pmcTimetableV041->requestFacultyAssignmentAcknowledgement($request->user(), $assignment);

        return back()->with('success', 'Faculty acknowledgement requested #' . $ack->id);
    }

    public function v052RespondFacultyAssignmentAcknowledgement(Request $request, AcademicPmcFacultyAssignmentAcknowledgement $acknowledgement)
    {
        $data = $request->validate([
            'response_type' => 'required|in:accept,accept_with_constraints,raise_concern,decline',
            'faculty_note' => 'nullable|string|max:2000',
            'constraints_raised' => 'nullable',
        ]);
        $constraints = is_array($data['constraints_raised'] ?? null) ? $data['constraints_raised'] : array_values(array_filter(array_map('trim', explode(',', (string) ($data['constraints_raised'] ?? '')))));
        $this->pmcTimetableV041->respondFacultyAssignmentAcknowledgement($request->user(), $acknowledgement, $data['response_type'], $data['faculty_note'] ?? null, $constraints);

        return back()->with('success', 'Faculty acknowledgement response recorded.');
    }

    public function v052ReviewFacultyAssignmentAcknowledgement(Request $request, AcademicPmcFacultyAssignmentAcknowledgement $acknowledgement)
    {
        $data = $request->validate([
            'status' => 'required|in:accepted,concern_accepted,revision_required,reassigned',
            'review_note' => 'nullable|string|max:2000',
        ]);
        $this->pmcTimetableV041->reviewFacultyAssignmentAcknowledgement($request->user(), $acknowledgement, $data['status'], $data['review_note'] ?? null);

        return back()->with('success', 'Faculty acknowledgement reviewed.');
    }

    public function v041StoreLockedSlot(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'slot_type' => 'required|string|max:80',
            'program_id' => 'nullable|exists:programs,id',
            'batch_id' => 'nullable|exists:batches,id',
            'term_id' => 'nullable|exists:terms,id',
            'course_group_id' => 'nullable|exists:academic_pmc_course_groups,id',
            'teacher_id' => 'nullable|exists:teachers,id',
            'classroom_id' => 'nullable|exists:classrooms,id',
            'day_of_week' => 'required|integer|min:1|max:7',
            'timetable_slot_id' => 'required|exists:timetable_slots,id',
            'is_hard_lock' => 'nullable|boolean',
            'status' => 'nullable|string|max:80',
            'reason' => 'nullable|string|max:2000',
        ]);
        $this->pmcPolicy->authorizeWriteScope($request->user(), $data);
        $slot = $this->pmcTimetableV041->createLockedSlot($request->user(), $data);

        return back()->with('success', 'Locked timetable slot created: ' . $slot->title);
    }

    public function v041Generate(Request $request)
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'strategy' => 'nullable|string|max:80',
            'program_id' => 'nullable|exists:programs,id',
            'batch_id' => 'nullable|exists:batches,id',
            'term_id' => 'nullable|exists:terms,id',
        ]);
        $this->pmcPolicy->authorizeWriteScope($request->user(), $data);
        $run = $this->pmcTimetableV041->generate($request->user(), $data);

        return back()->with('success', "Timetable generated with {$run->scheduled_count} scheduled and {$run->unscheduled_count} unscheduled classes.");
    }

    public function v042ValidateGeneration(Request $request, AcademicPmcTimetableGenerationRun $run)
    {
        $this->authorizeGenerationRunScope($request, $run);
        $quality = $this->pmcTimetableV041->refreshConstraintsAndQuality($run);

        return back()->with('success', "Validation refreshed: {$quality->hard_conflicts} hard conflicts, {$quality->soft_warnings} soft warnings, quality {$quality->overall_score}.");
    }

    public function v066ApplySolverAlternative(Request $request, AcademicPmcTimetableGenerationItem $item)
    {
        $group = $item->courseGroup;
        $this->pmcPolicy->authorizeWriteScope($request->user(), [
            'program_id' => $group?->program_id,
            'batch_id' => $group?->batch_id,
            'term_id' => $group?->term_id,
            'subject_id' => $group?->subject_id,
        ]);
        $data = $request->validate([
            'alternative_index' => 'required|integer|min:0|max:2',
            'decision_note' => 'nullable|string|max:2000',
            'allow_hard_conflict_override' => 'nullable|boolean',
            'override_reason' => 'nullable|string|max:2000',
        ]);
        $this->pmcTimetableV041->applySolverAlternative($request->user(), $item, (int) $data['alternative_index'], $data['decision_note'] ?? null, (bool) ($data['allow_hard_conflict_override'] ?? false), $data['override_reason'] ?? null);

        return back()->with('success', 'Solver alternative applied and timetable quality refreshed.');
    }

    public function v068MoveGeneratedItem(Request $request, AcademicPmcTimetableGenerationItem $item)
    {
        $group = $item->courseGroup;
        $this->pmcPolicy->authorizeWriteScope($request->user(), [
            'program_id' => $group?->program_id,
            'batch_id' => $group?->batch_id,
            'term_id' => $group?->term_id,
            'subject_id' => $group?->subject_id,
        ]);
        $data = $request->validate([
            'day_of_week' => 'required|integer|min:1|max:7',
            'timetable_slot_id' => 'required|exists:timetable_slots,id',
            'classroom_id' => 'required|exists:classrooms,id',
            'decision_note' => 'nullable|string|max:2000',
            'allow_hard_conflict_override' => 'nullable|boolean',
            'override_reason' => 'nullable|string|max:2000',
        ]);
        $this->pmcTimetableV041->moveGeneratedItem($request->user(), $item, $data, (bool) ($data['allow_hard_conflict_override'] ?? false), $data['override_reason'] ?? null);

        return back()->with('success', 'Manual timetable move applied and quality refreshed.');
    }

    public function v069RefreshGenerationImpact(Request $request, AcademicPmcTimetableGenerationRun $run)
    {
        $this->pmcPolicy->authorizeWriteScope($request->user(), [
            'program_id' => $run->program_id,
            'batch_id' => $run->batch_id,
            'term_id' => $run->term_id,
        ]);
        $records = $this->pmcTimetableV041->refreshGenerationImpactPreview($request->user(), $run);

        return back()->with('success', "Impact preview refreshed with {$records->count()} source-backed impact rows.");
    }

    public function v043PublishRun(Request $request, AcademicPmcTimetableGenerationRun $run)
    {
        $this->authorizeGenerationRunScope($request, $run);
        $data = $request->validate([
            'effective_from' => 'nullable|date',
            'decision_reason' => 'nullable|string|max:2000',
            'override_reason' => 'nullable|string|max:2000',
        ]);
        $version = $this->pmcTimetableV041->publishRun($request->user(), $run, $data);

        return back()->with('success', 'Timetable published as version #' . $version->version_number);
    }

    public function v043FreezeVersion(Request $request, TimetableVersion $version)
    {
        $this->authorizeTimetableVersionScope($request, $version);
        $data = $request->validate(['decision_reason' => 'nullable|string|max:2000']);
        $this->pmcTimetableV041->freezeVersion($request->user(), $version, $data);

        return back()->with('success', 'Timetable version frozen.');
    }

    public function v043UnfreezeVersion(Request $request, TimetableVersion $version)
    {
        $this->authorizeTimetableVersionScope($request, $version);
        $data = $request->validate(['decision_reason' => 'required|string|max:2000']);
        $this->pmcTimetableV041->unfreezeVersion($request->user(), $version, $data);

        return back()->with('success', 'Timetable version reopened for revision.');
    }

    public function v043RollbackVersion(Request $request, TimetableVersion $version)
    {
        $this->authorizeTimetableVersionScope($request, $version);
        $data = $request->validate([
            'decision_reason' => 'required|string|max:2000',
            'effective_from' => 'nullable|date',
        ]);
        $rollback = $this->pmcTimetableV041->rollbackVersion($request->user(), $version, $data);

        return back()->with('success', 'Rollback published as version #' . $rollback->version_number);
    }

    public function v045CreateResolutionAction(Request $request, AcademicPmcTimetableConstraint $constraint)
    {
        $this->authorizeTimetableConstraintScope($request, $constraint);
        $data = $request->validate([
            'action_type' => 'nullable|string|max:100',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
            'owner_user_id' => 'nullable|exists:users,id',
            'priority' => 'nullable|string|max:40',
            'due_at' => 'nullable|date',
        ]);
        $action = $this->pmcTimetableV041->createResolutionAction($request->user(), $constraint, $data);

        return back()->with('success', 'Resolution action created: ' . $action->title);
    }

    public function v045CloseResolutionAction(Request $request, AcademicPmcTimetableResolutionAction $action)
    {
        $this->authorizeResolutionActionScope($request, $action);
        $data = $request->validate([
            'status' => 'nullable|in:resolved,closed,verified,cancelled',
            'resolution_note' => 'required|string|max:2000',
            'evidence' => 'nullable|array',
        ]);
        $this->pmcTimetableV041->closeResolutionAction($request->user(), $action, $data);

        return back()->with('success', 'Resolution action closed.');
    }

    public function v046SubmitFacultyAvailability(Request $request)
    {
        $data = $request->validate([
            'teacher_id' => 'nullable|exists:teachers,id',
            'term_id' => 'nullable|exists:terms,id',
            'request_type' => 'nullable|string|max:100',
            'available_days' => 'nullable',
            'preferred_slots' => 'nullable',
            'unavailable_slots' => 'nullable',
            'max_classes_per_day' => 'nullable|integer|min:1|max:12',
            'max_consecutive_classes' => 'nullable|integer|min:1|max:12',
            'max_weekly_load' => 'nullable|integer|min:1|max:60',
            'reason' => 'nullable|string|max:2000',
        ]);
        $availability = $this->pmcTimetableV041->submitFacultyAvailability($request->user(), $data);

        return back()->with('success', 'Faculty availability request submitted #' . $availability->id);
    }

    public function v046DecideFacultyAvailability(Request $request, AcademicPmcFacultyAvailabilityRequest $availability)
    {
        $data = $request->validate([
            'status' => 'required|in:approved,rejected,returned',
            'decision_note' => 'nullable|string|max:2000',
        ]);
        $this->pmcTimetableV041->decideFacultyAvailability($request->user(), $availability, $data['status'], $data['decision_note'] ?? null);

        return back()->with('success', 'Faculty availability request ' . $data['status'] . '.');
    }

    public function v047RefreshFacultyLoadReviews(Request $request)
    {
        $data = $request->validate([
            'generation_run_id' => 'nullable|exists:academic_pmc_timetable_generation_runs,id',
        ]);
        $result = $this->pmcTimetableV041->refreshFacultyLoadReviews($request->user(), $data);

        return back()->with('success', "Faculty load reviews refreshed for {$result['reviews']} faculty member(s).");
    }

    public function v047DecideFacultyLoadReview(Request $request, AcademicPmcFacultyLoadReview $review)
    {
        $data = $request->validate([
            'status' => 'required|in:approved,approved_overload,rejected,revision_required',
            'decision_note' => 'nullable|string|max:2000',
        ]);
        $this->pmcTimetableV041->decideFacultyLoadReview($request->user(), $review, $data['status'], $data['decision_note'] ?? null);

        return back()->with('success', 'Faculty load review decision recorded.');
    }

    public function v048RefreshRoomReadinessReviews(Request $request)
    {
        $data = $request->validate([
            'generation_run_id' => 'nullable|exists:academic_pmc_timetable_generation_runs,id',
        ]);
        $result = $this->pmcTimetableV041->refreshRoomReadinessReviews($request->user(), $data);

        return back()->with('success', "Room readiness reviews refreshed for {$result['reviews']} room(s); {$result['blocked']} blocker(s).");
    }

    public function v048DecideRoomReadinessReview(Request $request, AcademicPmcRoomReadinessReview $review)
    {
        $data = $request->validate([
            'status' => 'required|in:approved,approved_with_exception,rejected,revision_required',
            'decision_note' => 'nullable|string|max:2000',
        ]);
        $this->pmcTimetableV041->decideRoomReadinessReview($request->user(), $review, $data['status'], $data['decision_note'] ?? null);

        return back()->with('success', 'Room readiness review decision recorded.');
    }

    public function v041RequestChange(Request $request)
    {
        $data = $request->validate([
            'timetable_version_id' => 'nullable|exists:timetable_versions,id',
            'change_type' => 'required|string|max:80',
            'reason' => 'required|string|max:2000',
            'impact_summary' => 'nullable|array',
        ]);
        if (! empty($data['timetable_version_id'])) {
            $this->authorizeTimetableVersionScope($request, TimetableVersion::findOrFail($data['timetable_version_id']));
        } else {
            $this->pmcPolicy->authorizeWrite($request->user());
        }
        $change = $this->pmcTimetableV041->requestChange($request->user(), $data);

        return back()->with('success', 'Timetable change request created #' . $change->id);
    }

    public function v041DecideChange(Request $request, AcademicPmcTimetableChangeRequest $change)
    {
        if ($change->timetable_version_id) {
            $this->authorizeTimetableVersionScope($request, TimetableVersion::findOrFail($change->timetable_version_id));
        } else {
            $this->pmcPolicy->authorizeWrite($request->user());
        }
        $data = $request->validate([
            'status' => 'required|in:approved,rejected,revision_requested,frozen,unfrozen,published',
            'decision_note' => 'nullable|string|max:2000',
        ]);
        $this->pmcTimetableV041->decideChange($request->user(), $change, $data['status'], $data['decision_note'] ?? null);

        return back()->with('success', 'Timetable change decision recorded.');
    }

    public function v041RecommendSubstitution(Request $request)
    {
        $data = $request->validate([
            'course_group_id' => 'nullable|exists:academic_pmc_course_groups,id',
            'original_teacher_id' => 'required|exists:teachers,id',
            'substitution_date' => 'nullable|date',
        ]);
        if (! empty($data['course_group_id'])) {
            $group = AcademicPmcCourseGroup::findOrFail($data['course_group_id']);
            $this->pmcPolicy->authorizeWriteScope($request->user(), [
                'program_id' => $group->program_id,
                'batch_id' => $group->batch_id,
                'term_id' => $group->term_id,
                'subject_id' => $group->subject_id,
            ]);
        } else {
            $this->pmcPolicy->authorizeWrite($request->user());
        }
        $recommendation = $this->pmcTimetableV041->recommendSubstitution($request->user(), $data);

        return back()->with('success', 'Substitution recommendation created with status ' . $recommendation->status);
    }

    public function v041LogNotification(Request $request)
    {
        $this->pmcPolicy->authorizeWrite($request->user());
        $data = $request->validate([
            'notification_type' => 'required|string|max:100',
            'recipient_type' => 'required|string|max:80',
            'recipient_user_id' => 'nullable|exists:users,id',
            'title' => 'required|string|max:255',
            'message' => 'nullable|string|max:2000',
            'source_type' => 'nullable|string|max:100',
            'source_key' => 'nullable|string|max:255',
        ]);
        $notification = $this->pmcTimetableV041->logNotification($request->user(), $data);

        return back()->with('success', 'Timetable notification queued: ' . $notification->title);
    }

    public function v074UpdateNotificationStatus(Request $request, AcademicPmcTimetableNotification $notification)
    {
        $this->pmcPolicy->authorizeWrite($request->user());
        $data = $request->validate([
            'status' => 'required|in:queued,sent,read,failed,cancelled',
            'status_note' => 'nullable|string|max:2000',
        ]);
        $this->pmcTimetableV041->updateNotificationStatus($request->user(), $notification, $data['status'], $data['status_note'] ?? null);

        return back()->with('success', 'Timetable notification marked ' . $data['status'] . '.');
    }

    public function v075RetryNotification(Request $request, AcademicPmcTimetableNotification $notification)
    {
        $this->pmcPolicy->authorizeWrite($request->user());
        $data = $request->validate([
            'retry_note' => 'nullable|string|max:2000',
        ]);
        $this->pmcTimetableV041->retryNotification($request->user(), $notification, $data['retry_note'] ?? null);

        return back()->with('success', 'Timetable notification retry queued.');
    }

    private function section(Request $request, string $section)
    {
        $this->authorizePmc($request);

        return view('academics.pmc.section', [
            'section' => $this->pmc->section($request->user(), $section, $request->query()),
        ]);
    }

    private function authorizeGenerationRunScope(Request $request, AcademicPmcTimetableGenerationRun $run): void
    {
        $this->pmcPolicy->authorizeWriteScope($request->user(), [
            'program_id' => $run->program_id,
            'batch_id' => $run->batch_id,
            'term_id' => $run->term_id,
        ]);
    }

    private function authorizeTimetableVersionScope(Request $request, TimetableVersion $version): void
    {
        $this->pmcPolicy->authorizeWriteScope($request->user(), [
            'program_id' => $version->program_id,
            'batch_id' => $version->batch_id,
            'term_id' => $version->term_id,
        ]);
    }

    private function authorizeTimetableConstraintScope(Request $request, AcademicPmcTimetableConstraint $constraint): void
    {
        $run = $constraint->generation_run_id
            ? AcademicPmcTimetableGenerationRun::find($constraint->generation_run_id)
            : null;

        if ($run) {
            $this->authorizeGenerationRunScope($request, $run);
            return;
        }

        $this->pmcPolicy->authorizeWrite($request->user());
    }

    private function authorizeResolutionActionScope(Request $request, AcademicPmcTimetableResolutionAction $action): void
    {
        $run = $action->generation_run_id
            ? AcademicPmcTimetableGenerationRun::find($action->generation_run_id)
            : null;

        if ($run) {
            $this->authorizeGenerationRunScope($request, $run);
            return;
        }

        $this->pmcPolicy->authorizeWrite($request->user());
    }

    private function authorizePmc(Request $request): void
    {
        $user = $request->user();
        $this->policy->authorizeRead($user);

        abort_unless(
            $user->hasAnyRole([
                'admin',
                'director',
                'academic_department_owner',
                'dean_academics',
                'pmc_head',
                'pmc_manager',
                'pmc_officer',
                'program_chair',
                'program_director',
                'program_leader',
                'hod',
                'semester_coordinator',
                'course_coordinator',
                'faculty_mentor',
            ]),
            403
        );
    }
}
