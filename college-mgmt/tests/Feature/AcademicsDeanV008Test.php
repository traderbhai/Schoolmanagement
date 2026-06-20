<?php

namespace Tests\Feature;

use App\Models\AcademicDeanActionEvidence;
use App\Models\AcademicDeanActionItem;
use App\Models\AcademicDeanApprovalItem;
use App\Models\AcademicDeanMeetingMinute;
use App\Models\AcademicDeanPlanningCycle;
use App\Models\AcademicDeanPolicyAudit;
use App\Models\AcademicDeanReportPack;
use App\Models\AcademicDeanReviewMeeting;
use App\Models\AcademicDeanRiskMitigation;
use App\Models\AcademicDeanRiskSnapshot;
use App\Models\AcademicDeanSavedView;
use App\Models\Department;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\AcademicDeanPolicyAuditService;
use App\Services\AcademicDeanRiskConfigService;
use App\Services\AcademicDeanRiskService;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsDeanV008Test extends TestCase
{
    use RefreshDatabase;

    private function seedDeanFixture(): User
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT101', 'name' => 'Management Foundations', 'is_active' => true]);
        $studentUser = User::factory()->create(['name' => 'Aarav Dean V008']);
        Student::factory()->create(['user_id' => $studentUser->id, 'department_id' => $department->id, 'program_id' => $program->id, 'status' => 'active']);
        Semester::factory()->create(['number' => 1, 'is_current' => true]);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        return User::where('email', 'dean@college.com')->firstOrFail();
    }

    public function test_dean_can_open_all_v008_surfaces(): void
    {
        $dean = $this->seedDeanFixture();

        foreach ([
            'academics.dean-os.planning.index' => 'Dean Academic Planning Cycle OS',
            'academics.dean-os.review-templates.index' => 'Advanced Review Meetings',
            'academics.dean-os.actions.index' => 'Advanced Action Governance',
            'academics.dean-os.risk-settings.index' => 'Advanced Risk Governance',
            'academics.dean-os.approval-cockpit.index' => 'Unified Dean Approval Cockpit',
            'academics.dean-os.faculty-workload.index' => 'Faculty Workload Governance',
            'academics.dean-os.student-success.index' => 'Student Success Command',
            'academics.dean-os.curriculum-governance.index' => 'Curriculum Governance',
            'academics.dean-os.exam-readiness.index' => 'Exam Readiness Command Board',
            'academics.dean-os.quality-command.index' => 'IQAC Quality Command',
            'academics.dean-os.induction.index' => 'Dean-To-Academics Induction',
            'academics.dean-os.analytics.index' => 'Dean Analytics',
            'academics.dean-os.planning-calendar.index' => 'Interactive Planning Calendar',
            'academics.dean-os.policy-audit.index' => 'Dean Route-Level Policy Audit',
        ] as $route => $text) {
            $this->actingAs($dean)->get(route($route))->assertOk()->assertSee($text);
        }
    }

    public function test_non_dean_users_are_blocked_from_v008_routes(): void
    {
        $this->seedDeanFixture();
        $pmc = User::where('email', 'pmc.manager@college.com')->firstOrFail();

        $this->actingAs($pmc)->get(route('academics.dean-os.planning.index'))->assertForbidden();
        $this->actingAs($pmc)->post(route('academics.dean-os.planning.store'), ['title' => 'Blocked', 'cycle_type' => 'annual_plan'])->assertForbidden();
    }

    public function test_dean_creates_and_approves_academic_plan(): void
    {
        $dean = $this->seedDeanFixture();

        $this->actingAs($dean)->post(route('academics.dean-os.planning.store'), [
            'title' => 'Dean V008 Test Annual Plan',
            'cycle_type' => 'annual_plan',
            'academic_year' => '2026-27',
            'status' => 'draft',
        ])->assertRedirect();

        $cycle = AcademicDeanPlanningCycle::where('title', 'Dean V008 Test Annual Plan')->firstOrFail();
        $this->assertGreaterThan(0, $cycle->readinessItems()->count());

        $this->actingAs($dean)->patch(route('academics.dean-os.planning.approve', $cycle), ['status' => 'published'])->assertRedirect();
        $this->assertDatabaseHas('academic_dean_planning_cycles', ['id' => $cycle->id, 'status' => 'published']);
    }

    public function test_minutes_approval_creates_follow_up_action_and_evidence_can_be_added(): void
    {
        $dean = $this->seedDeanFixture();
        $meeting = AcademicDeanReviewMeeting::firstOrFail();

        $this->actingAs($dean)->post(route('academics.dean-os.meeting-minutes.store', $meeting), [
            'minutes' => 'Dean approved the action and asked team to close evidence.',
            'status' => 'submitted',
        ])->assertRedirect();

        $minute = AcademicDeanMeetingMinute::where('meeting_id', $meeting->id)->firstOrFail();
        $this->actingAs($dean)->patch(route('academics.dean-os.meeting-minutes.approve', $minute))->assertRedirect();

        $action = AcademicDeanActionItem::where('source_type', 'meeting_minutes')->firstOrFail();
        $this->actingAs($dean)->post(route('academics.dean-os.action-evidence.store', $action), [
            'title' => 'Closure evidence',
            'notes' => 'Evidence uploaded in test.',
        ])->assertRedirect();

        $this->assertTrue(AcademicDeanActionEvidence::where('action_item_id', $action->id)->exists());
    }

    public function test_risk_snapshot_mitigation_and_thresholds_work(): void
    {
        $dean = $this->seedDeanFixture();

        $this->assertSame('critical', app(AcademicDeanRiskConfigService::class)->band(90));

        $this->actingAs($dean)->post(route('academics.dean-os.risk-history.capture'))->assertRedirect();
        $snapshot = AcademicDeanRiskSnapshot::latest()->firstOrFail();

        $this->actingAs($dean)
            ->get(route('academics.dean-os.risk-settings.index'))
            ->assertOk()
            ->assertSee($snapshot->program?->code ?? $snapshot->program?->name)
            ->assertDontSee('#'.$snapshot->program_id);

        $this->actingAs($dean)->post(route('academics.dean-os.risk-mitigation.store'), [
            'risk_snapshot_id' => $snapshot->id,
            'plan' => 'Mitigate by assigning owner and weekly review.',
        ])->assertRedirect();

        $this->assertTrue(AcademicDeanRiskMitigation::where('risk_snapshot_id', $snapshot->id)->exists());
    }

    public function test_risk_snapshot_capture_is_idempotent_for_program_and_date(): void
    {
        $dean = $this->seedDeanFixture();
        AcademicDeanRiskSnapshot::query()->delete();

        $expectedSnapshots = app(AcademicDeanRiskService::class)->programRisks()->count();

        $this->actingAs($dean)->post(route('academics.dean-os.risk-history.capture'))->assertRedirect();
        $this->actingAs($dean)->post(route('academics.dean-os.risk-history.capture'))->assertRedirect();

        $this->assertSame($expectedSnapshots, AcademicDeanRiskSnapshot::whereDate('snapshot_date', now()->toDateString())->count());
    }

    public function test_approval_saved_view_report_pack_and_policy_audit(): void
    {
        $dean = $this->seedDeanFixture();
        $approval = AcademicDeanApprovalItem::where('status', 'pending')->firstOrFail();

        $this->actingAs($dean)->patch(route('academics.dean-os.approval-cockpit.decide', $approval), [
            'status' => 'approved',
            'decision_reason' => 'Approved in v0.08 test.',
        ])->assertRedirect();
        $this->assertDatabaseHas('academic_dean_approval_items', ['id' => $approval->id, 'status' => 'approved']);

        $this->actingAs($dean)->post(route('academics.dean-os.saved-views.store'), [
            'name' => 'V008 Test View',
            'surface' => 'planning',
            'filters' => ['status' => 'open'],
            'is_default' => true,
        ])->assertRedirect();
        $this->assertTrue(AcademicDeanSavedView::where('name', 'V008 Test View')->exists());

        $pack = AcademicDeanReportPack::firstOrFail();
        $this->actingAs($dean)->patch(route('academics.dean-os.scheduled-reports.generate', $pack))->assertRedirect();
        $this->assertNotNull($pack->fresh()->last_generated_at);

        $count = app(AcademicDeanPolicyAuditService::class)->refresh();
        $this->assertGreaterThan(0, $count);
        $this->assertSame(0, AcademicDeanPolicyAudit::where('has_policy', false)->count());
    }

    public function test_finalized_dean_approval_cannot_be_rewritten(): void
    {
        $dean = $this->seedDeanFixture();
        $approval = AcademicDeanApprovalItem::where('status', 'pending')->firstOrFail();

        $this->actingAs($dean)->patch(route('academics.dean-os.approval-cockpit.decide', $approval), [
            'status' => 'approved',
            'decision_reason' => 'Initial approved decision.',
        ])->assertRedirect();

        $this->actingAs($dean)->patch(route('academics.dean-os.approval-cockpit.decide', $approval->fresh()), [
            'status' => 'rejected',
            'decision_reason' => 'Try to rewrite final decision.',
        ])->assertStatus(422);

        $this->assertSame('approved', $approval->fresh()->status);
        $this->assertSame('Initial approved decision.', $approval->fresh()->decision_reason);
    }

    public function test_dean_approval_evidence_request_requires_reason(): void
    {
        $dean = $this->seedDeanFixture();
        $approval = AcademicDeanApprovalItem::where('status', 'pending')->firstOrFail();

        $this->actingAs($dean)->patch(route('academics.dean-os.approval-cockpit.decide', $approval), [
            'status' => 'requested_evidence',
            'decision_reason' => '   ',
        ])->assertStatus(422);

        $this->assertSame('pending', $approval->fresh()->status);

        $this->actingAs($dean)->patch(route('academics.dean-os.approval-cockpit.decide', $approval), [
            'status' => 'requested_evidence',
            'decision_reason' => 'Upload signed timetable readiness evidence.',
        ])->assertRedirect();

        $this->assertDatabaseHas('academic_dean_approval_items', [
            'id' => $approval->id,
            'status' => 'requested_evidence',
            'decision_reason' => 'Upload signed timetable readiness evidence.',
        ]);
    }

    public function test_published_dean_planning_cycle_cannot_be_downgraded(): void
    {
        $dean = $this->seedDeanFixture();

        $this->actingAs($dean)->post(route('academics.dean-os.planning.store'), [
            'title' => 'Dean Finality Test Plan',
            'cycle_type' => 'academic_calendar',
            'academic_year' => '2026-27',
            'status' => 'draft',
        ])->assertRedirect();

        $cycle = AcademicDeanPlanningCycle::where('title', 'Dean Finality Test Plan')->firstOrFail();
        $this->actingAs($dean)->patch(route('academics.dean-os.planning.approve', $cycle), ['status' => 'published'])->assertRedirect();

        $this->actingAs($dean)->patch(route('academics.dean-os.planning.approve', $cycle->fresh()), ['status' => 'draft'])->assertStatus(422);

        $this->assertSame('published', $cycle->fresh()->status);
    }

    public function test_readiness_blocker_action_creation_is_idempotent(): void
    {
        $dean = $this->seedDeanFixture();

        $this->actingAs($dean)->post(route('academics.dean-os.planning.store'), [
            'title' => 'Dean Readiness Action Idempotency Plan',
            'cycle_type' => 'semester_readiness',
            'academic_year' => '2026-27',
            'status' => 'draft',
        ])->assertRedirect();

        $cycle = AcademicDeanPlanningCycle::where('title', 'Dean Readiness Action Idempotency Plan')->firstOrFail();
        $item = $cycle->readinessItems()->where('is_blocker', true)->firstOrFail();

        $this->actingAs($dean)->post(route('academics.dean-os.semester-readiness.action', $item))->assertRedirect();
        $this->actingAs($dean)->post(route('academics.dean-os.semester-readiness.action', $item))->assertRedirect();

        $this->assertSame(1, AcademicDeanActionItem::where('source_type', 'planning_readiness')->where('source_key', (string) $item->id)->count());
    }

    public function test_approved_meeting_minutes_cannot_create_duplicate_follow_up_actions(): void
    {
        $dean = $this->seedDeanFixture();
        $meeting = AcademicDeanReviewMeeting::firstOrFail();

        $this->actingAs($dean)->post(route('academics.dean-os.meeting-minutes.store', $meeting), [
            'minutes' => 'Dean approved one follow-up only.',
            'status' => 'submitted',
        ])->assertRedirect();

        $minute = AcademicDeanMeetingMinute::where('meeting_id', $meeting->id)->firstOrFail();
        $this->actingAs($dean)->patch(route('academics.dean-os.meeting-minutes.approve', $minute))->assertRedirect();

        $this->actingAs($dean)->patch(route('academics.dean-os.meeting-minutes.approve', $minute->fresh()))->assertStatus(422);

        $this->assertSame(1, AcademicDeanActionItem::where('source_type', 'meeting_minutes')->where('source_key', (string) $minute->id)->count());
    }
}
