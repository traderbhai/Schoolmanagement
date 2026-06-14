<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Models\AcademicPmcWorkItem;
use App\Models\AcademicPmcApproval;
use App\Models\AcademicPmcOperatingRecord;
use App\Models\AcademicPmcTimetableChangeRequest;
use App\Services\AcademicAccessPolicyService;
use App\Services\AcademicPmcAccessPolicyService;
use App\Services\AcademicPmcOperatingService;
use App\Services\AcademicPmcTimetableV041Service;
use App\Services\AcademicPmcV003Service;
use App\Services\AcademicPmcV004Service;
use Illuminate\Http\Request;

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

    public function v041StoreAllocationBatch(Request $request)
    {
        $this->pmcPolicy->authorizeWrite($request->user());
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'program_id' => 'required|exists:programs,id',
            'batch_id' => 'nullable|exists:batches,id',
            'term_id' => 'nullable|exists:terms,id',
            'subject_ids' => 'required|array|min:1',
            'subject_ids.*' => 'exists:subjects,id',
            'max_credits' => 'nullable|integer|min:1|max:80',
        ]);
        $batch = $this->pmcTimetableV041->bulkAllocateCore($request->user(), $data);

        return back()->with('success', "Course allocation batch created with {$batch->core_allocations} allocations.");
    }

    public function v041StoreGroup(Request $request)
    {
        $this->pmcPolicy->authorizeWrite($request->user());
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
        $group = $this->pmcTimetableV041->createGroup($request->user(), $data);

        return back()->with('success', 'Course group created: ' . $group->name);
    }

    public function v041AssignFaculty(Request $request)
    {
        $this->pmcPolicy->authorizeWrite($request->user());
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
        $assignment = $this->pmcTimetableV041->assignFaculty($request->user(), $data);

        return back()->with('success', 'Faculty assigned to section/group #' . $assignment->course_group_id);
    }

    public function v041StoreLockedSlot(Request $request)
    {
        $this->pmcPolicy->authorizeWrite($request->user());
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
        $slot = $this->pmcTimetableV041->createLockedSlot($request->user(), $data);

        return back()->with('success', 'Locked timetable slot created: ' . $slot->title);
    }

    public function v041Generate(Request $request)
    {
        $this->pmcPolicy->authorizeWrite($request->user());
        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'strategy' => 'nullable|string|max:80',
            'program_id' => 'nullable|exists:programs,id',
            'batch_id' => 'nullable|exists:batches,id',
            'term_id' => 'nullable|exists:terms,id',
        ]);
        $run = $this->pmcTimetableV041->generate($request->user(), $data);

        return back()->with('success', "Timetable generated with {$run->scheduled_count} scheduled and {$run->unscheduled_count} unscheduled classes.");
    }

    public function v041RequestChange(Request $request)
    {
        $this->pmcPolicy->authorizeWrite($request->user());
        $data = $request->validate([
            'timetable_version_id' => 'nullable|exists:timetable_versions,id',
            'change_type' => 'required|string|max:80',
            'reason' => 'required|string|max:2000',
            'impact_summary' => 'nullable|array',
        ]);
        $change = $this->pmcTimetableV041->requestChange($request->user(), $data);

        return back()->with('success', 'Timetable change request created #' . $change->id);
    }

    public function v041DecideChange(Request $request, AcademicPmcTimetableChangeRequest $change)
    {
        $this->pmcPolicy->authorizeWrite($request->user());
        $data = $request->validate([
            'status' => 'required|in:approved,rejected,revision_requested,frozen,unfrozen,published',
            'decision_note' => 'nullable|string|max:2000',
        ]);
        $this->pmcTimetableV041->decideChange($request->user(), $change, $data['status'], $data['decision_note'] ?? null);

        return back()->with('success', 'Timetable change decision recorded.');
    }

    public function v041RecommendSubstitution(Request $request)
    {
        $this->pmcPolicy->authorizeWrite($request->user());
        $data = $request->validate([
            'course_group_id' => 'nullable|exists:academic_pmc_course_groups,id',
            'original_teacher_id' => 'required|exists:teachers,id',
            'substitution_date' => 'nullable|date',
        ]);
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

    private function section(Request $request, string $section)
    {
        $this->authorizePmc($request);

        return view('academics.pmc.section', [
            'section' => $this->pmc->section($request->user(), $section),
        ]);
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
