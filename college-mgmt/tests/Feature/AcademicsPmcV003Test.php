<?php

namespace Tests\Feature;

use App\Models\AcademicPmcExportLog;
use App\Models\AcademicPmcReviewMeeting;
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

class AcademicsPmcV003Test extends TestCase
{
    use RefreshDatabase;

    private function seedPmcFixture(): User
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT101', 'name' => 'Management Foundations', 'is_active' => true]);
        $studentUser = User::factory()->create(['name' => 'Aarav PMC v003']);
        Student::factory()->create(['user_id' => $studentUser->id, 'department_id' => $department->id, 'program_id' => $program->id, 'status' => 'active']);
        Semester::factory()->create(['number' => 1, 'is_current' => true]);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        return User::where('email', 'chair@college.com')->firstOrFail();
    }

    public function test_pmc_head_can_open_all_v003_surfaces(): void
    {
        $chair = $this->seedPmcFixture();

        foreach ([
            'academics.pmc.command' => 'PMC Command OS',
            'academics.pmc.workbench' => 'PMC Workbench',
            'academics.pmc.curriculum-governance' => 'PMC Curriculum Governance',
            'academics.pmc.faculty-workload' => 'PMC Faculty Workload Governance',
            'academics.pmc.timetable-control' => 'PMC Timetable Control Room',
            'academics.pmc.student-success' => 'PMC Student Success Command',
            'academics.pmc.reviews' => 'PMC Reviews And Actions',
            'academics.pmc.reports' => 'PMC Reports',
        ] as $route => $text) {
            $this->actingAs($chair)->get(route($route))->assertOk()->assertSee($text);
        }
    }

    public function test_pmc_work_item_review_saved_view_and_export_flows_work(): void
    {
        $chair = $this->seedPmcFixture();

        $this->actingAs($chair)->post(route('academics.pmc.work-items.store'), [
            'work_type' => 'curriculum',
            'title' => 'Test PMC Work Item',
            'priority' => 'high',
            'severity' => 'high',
            'status' => 'open',
            'due_at' => now()->addDay()->toDateString(),
        ])->assertRedirect();

        $item = AcademicPmcWorkItem::where('title', 'Test PMC Work Item')->firstOrFail();
        $this->actingAs($chair)->patch(route('academics.pmc.work-items.update', $item), [
            'priority' => 'high',
            'severity' => 'high',
            'status' => 'done',
            'due_at' => now()->addDay()->toDateString(),
        ])->assertRedirect();
        $this->assertDatabaseHas('academic_pmc_work_items', ['id' => $item->id, 'status' => 'done']);

        $this->actingAs($chair)->post(route('academics.pmc.reviews.store'), [
            'title' => 'Test PMC Review',
            'review_type' => 'weekly_pmc',
            'agenda' => 'Review test action',
        ])->assertRedirect();
        $this->assertTrue(AcademicPmcReviewMeeting::where('title', 'Test PMC Review')->exists());

        $this->actingAs($chair)->post(route('academics.pmc.saved-views.store'), [
            'name' => 'Test PMC View',
            'surface' => 'command',
            'filters' => ['severity' => 'critical'],
            'is_default' => true,
        ])->assertRedirect();
        $this->assertTrue(AcademicPmcSavedView::where('name', 'Test PMC View')->exists());

        $this->actingAs($chair)->get(route('academics.pmc.export', 'workbench'))->assertOk();
        $this->assertTrue(AcademicPmcExportLog::where('report_key', 'workbench')->exists());
    }

    public function test_non_academic_user_cannot_access_pmc_v003(): void
    {
        $this->seedPmcFixture();
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $student = User::factory()->create();
        $student->assignRole('student');

        $this->actingAs($student)->get(route('academics.pmc.command'))->assertForbidden();
    }
}
