<?php

namespace Tests\Feature;

use App\Models\AcademicDeanActionItem;
use App\Models\AcademicDeanExportLog;
use App\Models\AcademicDeanReviewMeeting;
use App\Models\Department;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\AcademicDeanAttentionService;
use App\Services\AcademicDeanRiskService;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcademicsDeanV007Test extends TestCase
{
    use RefreshDatabase;

    private function seedDeanFixture(): array
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        $subject = Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT101', 'name' => 'Management Foundations', 'is_active' => true]);
        $studentUser = User::factory()->create(['name' => 'Aarav Dean Risk']);
        Student::factory()->create([
            'user_id' => $studentUser->id,
            'department_id' => $department->id,
            'program_id' => $program->id,
            'status' => 'active',
        ]);
        Semester::factory()->create(['number' => 1, 'is_current' => true]);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        return compact('department', 'program', 'subject');
    }

    public function test_dean_can_open_command_os_with_branch_health_and_risk(): void
    {
        $this->seedDeanFixture();
        $dean = User::where('email', 'dean@college.com')->firstOrFail();

        $this->actingAs($dean)
            ->get(route('academics.dean-os.index'))
            ->assertOk()
            ->assertSee('Dean Academics Command OS')
            ->assertSee('Branch Health')
            ->assertSee('PMC')
            ->assertSee('CoE / Examination')
            ->assertSee('IQAC')
            ->assertSee('Program Leadership')
            ->assertSee('Course Delivery')
            ->assertSee('Program Risk Heatmap')
            ->assertSee('Review Actions');
    }

    public function test_non_dean_branch_users_cannot_access_dean_os(): void
    {
        $this->seedDeanFixture();
        $pmc = User::where('email', 'pmc.manager@college.com')->firstOrFail();

        $this->actingAs($pmc)
            ->get(route('academics.dean-os.index'))
            ->assertForbidden();
    }

    public function test_dean_attention_and_program_risk_are_database_backed(): void
    {
        $this->seedDeanFixture();
        $dean = User::where('email', 'dean@college.com')->firstOrFail();

        $queue = app(AcademicDeanAttentionService::class)->queue('action_items_overdue');
        $this->assertGreaterThan(0, $queue['count']);

        $risks = app(AcademicDeanRiskService::class)->programRisks();
        $this->assertTrue($risks->contains(fn ($risk) => $risk['program']->code === 'PGDM'));

        $this->actingAs($dean)
            ->get(route('academics.dean-os.program-risk'))
            ->assertOk()
            ->assertSee('Program Risk Heatmap')
            ->assertSee('PGDM');
    }

    public function test_dean_can_create_review_action_update_action_and_export(): void
    {
        $this->seedDeanFixture();
        $dean = User::where('email', 'dean@college.com')->firstOrFail();
        $owner = User::where('email', 'pmc.manager@college.com')->firstOrFail();

        $this->actingAs($dean)
            ->post(route('academics.dean-os.reviews.store'), [
                'title' => 'Dean Test Review',
                'review_type' => 'weekly_academic',
                'scheduled_for' => now()->addDay()->format('Y-m-d H:i:s'),
                'scope_type' => 'department',
                'summary' => 'Test review',
            ])
            ->assertRedirect();

        $meeting = AcademicDeanReviewMeeting::where('title', 'Dean Test Review')->firstOrFail();

        $this->actingAs($dean)
            ->post(route('academics.dean-os.actions.store'), [
                'meeting_id' => $meeting->id,
                'title' => 'Dean Test Action',
                'description' => 'Close test action',
                'source_type' => 'manual',
                'owner_user_id' => $owner->id,
                'priority' => 'high',
                'due_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect();

        $action = AcademicDeanActionItem::where('title', 'Dean Test Action')->firstOrFail();

        $this->actingAs($dean)
            ->patch(route('academics.dean-os.actions.update', $action), [
                'owner_user_id' => $owner->id,
                'priority' => 'high',
                'due_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
                'status' => 'done',
                'closure_note' => 'Closed in test',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('academic_dean_action_items', ['id' => $action->id, 'status' => 'done', 'closure_note' => 'Closed in test']);

        $this->actingAs($dean)
            ->get(route('academics.dean-os.export', 'branch_health'))
            ->assertOk();

        $this->assertTrue(AcademicDeanExportLog::where('report_key', 'branch_health')->exists());
    }

    public function test_dean_handoff_reports_calendar_and_legacy_dashboard_links_render(): void
    {
        $this->seedDeanFixture();
        $dean = User::where('email', 'dean@college.com')->firstOrFail();

        $this->actingAs($dean)->get(route('academics.dean-os.handoff'))->assertOk()->assertSee('Admission To Academics Handoff');
        $this->actingAs($dean)->get(route('academics.dean-os.calendar'))->assertOk()->assertSee('Dean Academic Calendar');
        $this->actingAs($dean)->get(route('academics.dean-os.reports'))->assertOk()->assertSee('Dean Reports')->assertSee('Dean branch health');
        $this->actingAs($dean)->get(route('dean.dashboard'))->assertOk()->assertSee('Dean OS');
    }
}
