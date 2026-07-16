<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Models\AcademicDeanActionItem;
use App\Models\AcademicDeanActionEvidence;
use App\Models\AcademicDeanApprovalItem;
use App\Models\AcademicDeanMeetingMinute;
use App\Models\AcademicDeanOperatingRecord;
use App\Models\AcademicDeanPlanningCycle;
use App\Models\AcademicDeanReadinessItem;
use App\Models\AcademicDeanReportPack;
use App\Models\AcademicDeanReviewMeeting;
use App\Models\AcademicDeanSavedView;
use App\Models\DepartmentMember;
use App\Models\User;
use App\Services\AcademicDeanActionGovernanceService;
use App\Services\AcademicDeanAnalyticsService;
use App\Services\AcademicDeanApprovalCockpitService;
use App\Services\AcademicDeanAttentionService;
use App\Services\AcademicDeanCalendarService;
use App\Services\AcademicDeanCommandService;
use App\Services\AcademicDeanDecisionRegisterService;
use App\Services\AcademicDeanExportService;
use App\Services\AcademicDeanMinutesService;
use App\Services\AcademicDeanOperatingRecordService;
use App\Services\AcademicDeanPlanningCalendarService;
use App\Services\AcademicDeanPlanningService;
use App\Services\AcademicDeanPolicyAuditService;
use App\Services\AcademicDeanReportPackService;
use App\Services\AcademicDeanReviewService;
use App\Services\AcademicDeanRiskConfigService;
use App\Services\AcademicDeanRiskMitigationService;
use App\Services\AcademicDeanRiskService;
use App\Services\AcademicDeanRiskSnapshotService;
use App\Services\AcademicDeanSavedViewService;
use App\Services\AcademicAccessPolicyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeanOsController extends Controller
{
    public function __construct(
        private AcademicDeanCommandService $command,
        private AcademicDeanAttentionService $attention,
        private AcademicDeanRiskService $risk,
        private AcademicDeanReviewService $reviews,
        private AcademicDeanCalendarService $calendar,
        private AcademicDeanExportService $exports,
        private AcademicAccessPolicyService $academicPolicy
    ) {}

    public function index(Request $request)
    {
        $this->authorizeDeanOs($request);

        return view('academics.dean-os.dashboard', $this->command->dashboard($request->user()));
    }

    public function attention(Request $request, string $queue)
    {
        $this->authorizeDeanOs($request);

        return view('academics.dean-os.attention', [
            'queue' => $this->attention->queue($queue),
            'queues' => $this->attention->queues(),
        ]);
    }

    public function branchHealth(Request $request)
    {
        $this->authorizeDeanOs($request);

        return view('academics.dean-os.branch-health', ['branches' => $this->command->branchHealth($request->user())]);
    }

    public function programRisk(Request $request)
    {
        $this->authorizeDeanOs($request);

        $filters = $request->only(['band', 'program_id']);
        $risks = $this->risk->programRisks()
            ->when($request->filled('band'), function ($collection) use ($request) {
                $bands = $request->band === 'critical_high'
                    ? ['critical', 'high']
                    : [(string) $request->band];

                return $collection->whereIn('band', $bands);
            })
            ->when($request->filled('program_id'), fn ($collection) => $collection->filter(fn ($risk) => (int) $risk['program']->id === (int) $request->program_id))
            ->values();

        return view('academics.dean-os.program-risk', compact('risks', 'filters'));
    }

    public function reviews(Request $request)
    {
        $this->authorizeDeanOs($request);

        $filters = $request->only(['status']);
        $actions = AcademicDeanActionItem::with(['owner', 'meeting'])
            ->when($request->filled('status'), function ($query) use ($request) {
                $status = (string) $request->status;

                if ($status === 'open') {
                    $query->whereNotIn('status', ['done', 'cancelled']);
                } else {
                    $query->where('status', $status);
                }
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('academics.dean-os.reviews', [
            'meetings' => AcademicDeanReviewMeeting::with(['chair', 'actions.owner'])->latest('scheduled_for')->paginate(10),
            'actions' => $actions,
            'filters' => $filters,
            'members' => DepartmentMember::with('user')->whereHas('department', fn ($q) => $q->where('code', 'ACAD'))->where('is_active', true)->get()->pluck('user')->filter()->unique('id')->values(),
        ]);
    }

    public function storeReview(Request $request)
    {
        $this->authorizeDeanOs($request, true);
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'review_type' => 'required|string|max:80',
            'scheduled_for' => 'nullable|date',
            'scope_type' => 'nullable|string|max:40',
            'scope_id' => 'nullable|integer',
            'summary' => 'nullable|string|max:2000',
        ]);

        $this->reviews->createMeeting($request->user(), $data);

        return back()->with('success', 'Dean review meeting created.');
    }

    public function storeAction(Request $request)
    {
        $this->authorizeDeanOs($request, true);
        $data = $request->validate([
            'meeting_id' => 'nullable|exists:academic_dean_review_meetings,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'source_type' => 'nullable|string|max:80',
            'source_key' => 'nullable|string|max:120',
            'owner_user_id' => 'nullable|exists:users,id',
            'priority' => 'required|string|max:30',
            'due_at' => 'nullable|date',
        ]);

        $this->reviews->createAction($request->user(), $data);

        return back()->with('success', 'Dean action item created.');
    }

    public function updateAction(Request $request, AcademicDeanActionItem $action)
    {
        $this->authorizeDeanOs($request, true);
        $data = $request->validate([
            'owner_user_id' => 'nullable|exists:users,id',
            'priority' => 'required|string|max:30',
            'due_at' => 'nullable|date',
            'status' => 'required|string|max:40',
            'closure_note' => 'nullable|string|max:2000',
        ]);

        $this->reviews->updateAction($request->user(), $action, $data);

        return back()->with('success', 'Dean action item updated.');
    }

    public function handoff(Request $request)
    {
        $this->authorizeDeanOs($request);
        $records = DB::getSchemaBuilder()->hasTable('admission_handoff_records')
            ? DB::table('admission_handoff_records')
                ->leftJoin('applicants', 'applicants.id', '=', 'admission_handoff_records.applicant_id')
                ->leftJoin('users', 'users.id', '=', 'applicants.user_id')
                ->select('admission_handoff_records.*', 'applicants.application_number', 'users.name as applicant_name')
                ->when($request->filled('status'), function ($query) use ($request) {
                    if ($request->status === 'blocking') {
                        $query->whereIn('admission_handoff_records.status', ['blocked', 'pending_admission_completion', 'returned_for_correction']);
                    } else {
                        $query->where('admission_handoff_records.status', $request->status);
                    }
                })
                ->latest('admission_handoff_records.updated_at')
                ->paginate(20)
                ->withQueryString()
            : collect();

        $counts = DB::getSchemaBuilder()->hasTable('admission_handoff_records')
            ? DB::table('admission_handoff_records')->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')
            : collect();

        $filters = $request->only(['status']);

        return view('academics.dean-os.handoff', compact('records', 'counts', 'filters'));
    }

    public function calendar(Request $request)
    {
        $this->authorizeDeanOs($request);

        return view('academics.dean-os.calendar', ['events' => $this->calendar->events()]);
    }

    public function reports(Request $request)
    {
        $this->authorizeDeanOs($request);

        return view('academics.dean-os.reports', [
            'reports' => $this->command->reports($request->user()),
            'savedViews' => AcademicDeanSavedView::where('user_id', $request->user()->id)->orWhereNull('user_id')->latest()->get(),
        ]);
    }

    public function export(Request $request, string $report)
    {
        $this->authorizeDeanOs($request);

        return $this->exports->export($report, $request->user(), $request->query());
    }

    public function planning(Request $request)
    {
        $this->authorizeDeanOs($request);

        return view('academics.dean-os.v008.planning', app(AcademicDeanPlanningService::class)->dashboard());
    }

    public function storePlanning(Request $request)
    {
        $this->authorizeDeanOs($request, true);
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'cycle_type' => 'required|string|max:60',
            'academic_year' => 'nullable|string|max:40',
            'program_id' => 'nullable|exists:programs,id',
            'batch_id' => 'nullable|exists:batches,id',
            'term_id' => 'nullable|exists:terms,id',
            'branch' => 'nullable|string|max:80',
            'owner_user_id' => 'nullable|exists:users,id',
            'status' => 'nullable|string|max:50',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
        ]);

        app(AcademicDeanPlanningService::class)->createPlan($request->user(), $data);

        return back()->with('success', 'Dean academic plan created.');
    }

    public function approvePlanning(Request $request, AcademicDeanPlanningCycle $cycle)
    {
        $this->authorizeDeanOs($request, true);
        app(AcademicDeanPlanningService::class)->approve($cycle, $request->input('status', 'approved'));

        return back()->with('success', 'Planning cycle updated.');
    }

    public function actionFromReadiness(Request $request, AcademicDeanReadinessItem $item)
    {
        $this->authorizeDeanOs($request, true);
        app(AcademicDeanPlanningService::class)->createActionFromBlocker($request->user(), $item);

        return back()->with('success', 'Action item created from readiness blocker.');
    }

    public function reviewTemplates(Request $request)
    {
        $this->authorizeDeanOs($request);

        return view('academics.dean-os.v008.review-templates', [
            'templates' => \App\Models\AcademicDeanReviewTemplate::latest()->paginate(20),
            'meetings' => AcademicDeanReviewMeeting::latest('scheduled_for')->paginate(12),
        ]);
    }

    public function storeMinutes(Request $request, AcademicDeanReviewMeeting $meeting)
    {
        $this->authorizeDeanOs($request, true);
        $data = $request->validate(['minutes' => 'required|string|max:5000', 'status' => 'nullable|string|max:40']);
        app(AcademicDeanMinutesService::class)->store($request->user(), $meeting, $data);

        return back()->with('success', 'Meeting minutes saved.');
    }

    public function approveMinutes(Request $request, AcademicDeanMeetingMinute $minute)
    {
        $this->authorizeDeanOs($request, true);
        app(AcademicDeanMinutesService::class)->approve($request->user(), $minute);

        return back()->with('success', 'Minutes approved and follow-up action created.');
    }

    public function storeDecision(Request $request)
    {
        $this->authorizeDeanOs($request, true);
        $data = $request->validate([
            'meeting_id' => 'nullable|exists:academic_dean_review_meetings,id',
            'title' => 'required|string|max:255',
            'decision_type' => 'required|string|max:80',
            'program_id' => 'nullable|exists:programs,id',
            'batch_id' => 'nullable|exists:batches,id',
            'term_id' => 'nullable|exists:terms,id',
            'owner_user_id' => 'nullable|exists:users,id',
            'status' => 'nullable|string|max:50',
            'due_at' => 'nullable|date',
            'evidence' => 'nullable|string|max:2000',
        ]);
        app(AcademicDeanDecisionRegisterService::class)->create($request->user(), $data);

        return back()->with('success', 'Decision registered.');
    }

    public function actionsIndex(Request $request)
    {
        $this->authorizeDeanOs($request);

        return view('academics.dean-os.v008.actions', app(AcademicDeanActionGovernanceService::class)->dashboard());
    }

    public function storeActionEvidence(Request $request, AcademicDeanActionItem $action)
    {
        $this->authorizeDeanOs($request, true);
        $data = $request->validate(['title' => 'required|string|max:255', 'path' => 'nullable|string|max:255', 'notes' => 'nullable|string|max:2000']);
        app(AcademicDeanActionGovernanceService::class)->addEvidence($request->user(), $action, $data);

        return back()->with('success', 'Action evidence added.');
    }

    public function riskSettings(Request $request)
    {
        $this->authorizeDeanOs($request);

        return view('academics.dean-os.v008.risk', [
            'threshold' => app(AcademicDeanRiskConfigService::class)->threshold(),
            'history' => app(AcademicDeanRiskSnapshotService::class)->history(),
            'mitigations' => \App\Models\AcademicDeanRiskMitigation::latest()->paginate(15),
        ]);
    }

    public function captureRiskSnapshot(Request $request)
    {
        $this->authorizeDeanOs($request, true);
        app(AcademicDeanRiskSnapshotService::class)->capture();

        return back()->with('success', 'Risk snapshot captured.');
    }

    public function storeRiskMitigation(Request $request)
    {
        $this->authorizeDeanOs($request, true);
        $data = $request->validate([
            'risk_snapshot_id' => 'nullable|exists:academic_dean_risk_snapshots,id',
            'owner_user_id' => 'nullable|exists:users,id',
            'status' => 'nullable|string|max:50',
            'plan' => 'required|string|max:3000',
            'due_at' => 'nullable|date',
        ]);
        app(AcademicDeanRiskMitigationService::class)->create($request->user(), $data);

        return back()->with('success', 'Risk mitigation plan saved.');
    }

    public function approvalCockpit(Request $request)
    {
        $this->authorizeDeanOs($request);

        return view('academics.dean-os.v008.approvals', app(AcademicDeanApprovalCockpitService::class)->dashboard());
    }

    public function decideApproval(Request $request, AcademicDeanApprovalItem $item)
    {
        $this->authorizeDeanOs($request, true);
        $data = $request->validate(['status' => 'required|string|max:50', 'decision_reason' => 'nullable|string|max:2000']);
        app(AcademicDeanApprovalCockpitService::class)->decide($request->user(), $item, $data['status'], $data['decision_reason'] ?? null);

        return back()->with('success', 'Approval item updated.');
    }

    public function operatingSurface(Request $request, string $surface)
    {
        $this->authorizeDeanOs($request);
        $map = $this->surfaceMap();
        abort_unless(isset($map[$surface]), 404);

        return view('academics.dean-os.v008.operating-surface', [
            'surface' => $surface,
            'config' => $map[$surface],
            'data' => app(AcademicDeanOperatingRecordService::class)->dashboard($map[$surface]['record_type'], $request->query()),
            'savedViews' => app(AcademicDeanSavedViewService::class)->list($request->user(), $surface),
        ]);
    }

    public function analytics(Request $request)
    {
        $this->authorizeDeanOs($request);

        return view('academics.dean-os.v008.analytics', app(AcademicDeanAnalyticsService::class)->dashboard() + app(AcademicDeanReportPackService::class)->dashboard());
    }

    public function generateReportPack(Request $request, AcademicDeanReportPack $pack)
    {
        $this->authorizeDeanOs($request, true);
        app(AcademicDeanReportPackService::class)->generate($pack);

        return back()->with('success', 'Report pack generated.');
    }

    public function planningCalendar(Request $request)
    {
        $this->authorizeDeanOs($request);

        return view('academics.dean-os.v008.planning-calendar', app(AcademicDeanPlanningCalendarService::class)->dashboard($request->query()));
    }

    public function policyAudit(Request $request)
    {
        $this->authorizeDeanOs($request);

        return view('academics.dean-os.v008.policy-audit', app(AcademicDeanPolicyAuditService::class)->dashboard());
    }

    public function storeSavedView(Request $request)
    {
        $this->authorizeDeanOs($request, true);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'surface' => 'required|string|max:80',
            'filters' => 'nullable|array',
            'is_default' => 'nullable|boolean',
        ]);
        app(AcademicDeanSavedViewService::class)->save($request->user(), $data);

        return back()->with('success', 'Saved view stored.');
    }

    private function surfaceMap(): array
    {
        return [
            'faculty-workload' => ['title' => 'Faculty Workload Governance', 'record_type' => 'faculty_workload', 'description' => 'Teaching load, overload, shortage, substitution, and mentoring pressure.'],
            'faculty-performance' => ['title' => 'Faculty Performance Review', 'record_type' => 'faculty_performance', 'description' => 'Feedback, result performance, course delivery, and mentoring follow-up.'],
            'mentoring-governance' => ['title' => 'Mentoring Governance', 'record_type' => 'mentoring_governance', 'description' => 'Mentor load, missed mentoring, and student support ownership.'],
            'student-success' => ['title' => 'Student Success Command', 'record_type' => 'student_success', 'description' => 'Cohort risk, intervention plans, retention, and parent escalation.'],
            'interventions' => ['title' => 'Student Interventions', 'record_type' => 'student_intervention', 'description' => 'Mentor meetings, remedials, parent calls, and program director review.'],
            'retention-risk' => ['title' => 'Retention Risk', 'record_type' => 'retention_risk', 'description' => 'Dropout early warning and retention risk ownership.'],
            'curriculum-governance' => ['title' => 'Curriculum Governance', 'record_type' => 'curriculum_governance', 'description' => 'Curriculum approval, rollout, CO/PO mapping, and sign-off.'],
            'syllabus-versions' => ['title' => 'Syllabus Versions', 'record_type' => 'syllabus_version', 'description' => 'Versioned syllabus changes by program, batch, term, and subject.'],
            'compliance-mapping' => ['title' => 'Compliance Mapping', 'record_type' => 'compliance_mapping', 'description' => 'Regulatory, credit, OBE, assessment, and evidence compliance.'],
            'exam-readiness' => ['title' => 'Exam Readiness Command Board', 'record_type' => 'exam_readiness', 'description' => 'Question papers, invigilation, hall tickets, evaluation, and result SLA.'],
            'quality-command' => ['title' => 'IQAC Quality Command', 'record_type' => 'quality_command', 'description' => 'NAAC/NBA, OBE, feedback closure, and quality audit gaps.'],
            'audit-evidence' => ['title' => 'Audit Evidence', 'record_type' => 'audit_evidence', 'description' => 'Evidence ownership, gaps, status, and due dates.'],
            'obe-action-plans' => ['title' => 'OBE Action Plans', 'record_type' => 'obe_action_plan', 'description' => 'Outcome attainment improvement plans and closure tracking.'],
            'induction' => ['title' => 'Dean-To-Academics Induction', 'record_type' => 'induction_onboarding', 'description' => 'Section, mentor, bridge course, orientation, and active-student readiness.'],
            'onboarding' => ['title' => 'Academic Onboarding Readiness', 'record_type' => 'onboarding_readiness', 'description' => 'Academic handoff blockers and onboarding progress.'],
        ];
    }

    private function authorizeDeanOs(Request $request, bool $write = false): void
    {
        $user = $request->user();
        abort_unless($user, 403);

        $this->academicPolicy->authorizeDeanOperatingSystem($user);
    }
}
