<?php

namespace Tests\Feature;

use App\Models\AcademicPmcApproval;
use App\Models\AcademicPmcAutomationExecution;
use App\Models\AcademicPmcExportLog;
use App\Models\AcademicPmcOperatingRecord;
use App\Models\AcademicPmcPolicyAudit;
use App\Models\AcademicPmcSavedView;
use App\Models\AcademicPmcWorkItem;
use App\Models\Department;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcademicsPmcV004Test extends TestCase
{
    use RefreshDatabase;

    private function seedPmcFixture(): User
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT401', 'name' => 'Management Analytics', 'is_active' => true]);
        $studentUser = User::factory()->create(['name' => 'PMC v004 Student']);
        Student::factory()->create(['user_id' => $studentUser->id, 'department_id' => $department->id, 'program_id' => $program->id, 'status' => 'active']);
        Semester::factory()->create(['number' => 1, 'is_current' => true]);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        return User::where('email', 'chair@college.com')->firstOrFail();
    }

    public function test_pmc_head_can_open_v004_operating_surfaces(): void
    {
        $chair = $this->seedPmcFixture();

        foreach ([
            'academics.pmc.command' => 'PMC Command OS',
            'academics.pmc.planning.index' => 'PMC Academic Planning Cycle',
            'academics.pmc.semester-readiness.index' => 'PMC Semester Readiness',
            'academics.pmc.curriculum-governance.index' => 'PMC Deep Curriculum Governance',
            'academics.pmc.faculty-allocation-v004.index' => 'PMC Faculty Allocation Governance',
            'academics.pmc.timetable-governance.index' => 'PMC Timetable Governance',
            'academics.pmc.course-delivery.index' => 'PMC Course Delivery Control',
            'academics.pmc.student-success-v004.index' => 'PMC Student Success And Mentoring Control',
            'academics.pmc.review-templates.index' => 'PMC Reviews, Decisions And Actions',
            'academics.pmc.approvals.index' => 'PMC Approval Cockpit',
            'academics.pmc.automation.index' => 'PMC Automation And Attention',
            'academics.pmc.analytics.index' => 'PMC Analytics And Reports',
            'academics.pmc.policy-audit.index' => 'PMC Policy Audit',
        ] as $route => $text) {
            $this->actingAs($chair)->get(route($route))->assertOk()->assertSee($text);
        }
    }

    public function test_v004_workflows_create_actions_decide_approvals_refresh_automation_and_export(): void
    {
        $chair = $this->seedPmcFixture();

        $this->actingAs($chair)->post(route('academics.pmc.v004.records.store'), [
            'record_type' => 'semester_readiness',
            'category' => 'timetable_conflict_free',
            'title' => 'Test readiness blocker',
            'description' => 'Conflict-free timetable required.',
            'status' => 'blocked',
            'risk_band' => 'critical',
            'score' => 40,
            'due_at' => now()->addDay()->toDateString(),
        ])->assertRedirect();

        $record = AcademicPmcOperatingRecord::where('title', 'Test readiness blocker')->firstOrFail();
        $this->actingAs($chair)->post(route('academics.pmc.v004.records.work-item', $record))->assertRedirect();
        $this->assertTrue(AcademicPmcWorkItem::where('source_key', (string) $record->id)->exists());

        $approval = AcademicPmcApproval::where('status', 'pending')->firstOrFail();
        $this->actingAs($chair)->patch(route('academics.pmc.v004.approvals.decide', $approval), [
            'status' => 'approved',
            'decision_reason' => 'Approved after PMC review.',
        ])->assertRedirect();
        $this->assertDatabaseHas('academic_pmc_approvals', ['id' => $approval->id, 'status' => 'approved']);

        $this->actingAs($chair)->post(route('academics.pmc.v004.automation.refresh'))->assertRedirect();
        $this->assertTrue(AcademicPmcAutomationExecution::where('subject_type', 'pmc_signal')->exists());

        $this->actingAs($chair)->post(route('academics.pmc.saved-views.store'), [
            'name' => 'PMC v004 Test View',
            'surface' => 'planning',
            'filters' => ['risk_band' => 'critical'],
            'is_default' => true,
        ])->assertRedirect();
        $this->assertTrue(AcademicPmcSavedView::where('name', 'PMC v004 Test View')->exists());

        $this->actingAs($chair)->get(route('academics.pmc.export', 'planning'))->assertOk();
        $this->assertTrue(AcademicPmcExportLog::where('report_key', 'planning')->exists());
    }

    public function test_v004_policy_scope_and_demo_data_are_present(): void
    {
        $chair = $this->seedPmcFixture();
        $manager = User::where('email', 'pmc.manager@college.com')->firstOrFail();
        $officer = User::where('email', 'pmc.officer@college.com')->firstOrFail();
        $mentor = User::where('email', 'faculty.mentor@college.com')->firstOrFail();

        foreach ([$chair, $manager, $officer, $mentor] as $user) {
            $this->actingAs($user)->get(route('academics.pmc.command'))->assertOk()->assertSee('PMC Command OS');
        }

        $this->assertGreaterThanOrEqual(20, AcademicPmcOperatingRecord::count());
        $this->assertTrue(AcademicPmcPolicyAudit::where('missing_enforcement', false)->exists());

        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $student = User::factory()->create();
        $student->assignRole('student');
        $this->actingAs($student)->get(route('academics.pmc.command'))->assertForbidden();
    }
}
