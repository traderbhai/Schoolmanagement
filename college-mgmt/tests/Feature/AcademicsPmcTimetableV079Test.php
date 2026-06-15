<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcFacultyLoadReview;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\Department;
use App\Models\Program;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcTimetableV079Test extends TestCase
{
    use RefreshDatabase;

    public function test_faculty_allocation_diagnostics_render_on_dashboard_and_faculty_page(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-os.index'))
            ->assertOk()
            ->assertSee('Faculty Allocation Diagnostics')
            ->assertSee('Missing Primary')
            ->assertSee('No Backup')
            ->assertSee('Pending Ack')
            ->assertSee('Load Blockers')
            ->assertSee('Open faculty allocation source list');

        $this->actingAs($chair)
            ->get(route('academics.pmc.section-faculty-allocation.index'))
            ->assertOk()
            ->assertSee('Faculty Allocation Diagnostics')
            ->assertSee('Resolve exact faculty, acknowledgement, preference, backup, and load-review blockers')
            ->assertSee('Faculty Assigned To Exact Groups');
    }

    public function test_missing_primary_preference_and_load_review_are_counted_as_launch_blockers(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();
        $department = Department::firstOrCreate(['code' => 'V079'], ['name' => 'v079 Department']);
        $program = Program::create([
            'department_id' => $department->id,
            'name' => 'v079 Program',
            'code' => 'V079-PGM',
            'is_active' => true,
        ]);
        $subject = Subject::create([
            'department_id' => $department->id,
            'program_id' => $program->id,
            'name' => 'v079 Faculty Course',
            'code' => 'V079-FAC',
            'credits' => 3,
            'type' => 'core',
            'hours_per_week' => 3,
            'is_active' => true,
        ]);
        $teacherUser = User::factory()->create(['name' => 'v079 Faculty']);
        $teacher = Teacher::create([
            'user_id' => $teacherUser->id,
            'department_id' => $department->id,
            'employee_id' => 'V079-FAC',
            'designation' => 'Assistant Professor',
            'employment_type' => 'full_time',
            'status' => 'active',
        ]);
        $group = AcademicPmcCourseGroup::create([
            'name' => 'v079 Backup Only Group',
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'owner_user_id' => $chair->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 20,
            'status' => 'active',
            'is_locked' => true,
        ]);
        AcademicPmcGroupFacultyAssignment::create([
            'course_group_id' => $group->id,
            'teacher_id' => $teacher->id,
            'assignment_role' => 'backup',
            'assignment_source' => 'pmc_test',
            'approval_status' => 'draft',
            'weekly_hours' => 3,
            'is_backup' => true,
            'assigned_by' => $chair->id,
        ]);
        AcademicPmcFacultyLoadReview::create([
            'teacher_id' => $teacher->id,
            'assigned_weekly_hours' => 24,
            'scheduled_classes' => 8,
            'max_classes_in_day' => 5,
            'configured_weekly_limit' => 18,
            'configured_daily_limit' => 4,
            'load_band' => 'overload',
            'status' => 'approval_required',
            'risk_reasons' => ['weekly_limit_exceeded'],
        ]);

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-os.index'))
            ->assertOk()
            ->assertSee('Missing Primary')
            ->assertSee('Missing Preference')
            ->assertSee('Load Blockers')
            ->assertSee('Resolve faculty assignment, acknowledgement, preference, and load-review blockers before timetable generation.');
    }
}
