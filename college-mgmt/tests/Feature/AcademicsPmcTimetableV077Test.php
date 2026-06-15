<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseAllocationBatch;
use App\Models\AcademicPmcStudentCourseAllocation;
use App\Models\Department;
use App\Models\Program;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcTimetableV077Test extends TestCase
{
    use RefreshDatabase;

    public function test_course_basket_diagnostics_render_on_timetable_os_and_basket_page(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-os.index'))
            ->assertOk()
            ->assertSee('Course Basket Diagnostics')
            ->assertSee('Ungrouped')
            ->assertSee('Waitlisted')
            ->assertSee('Pending Exceptions')
            ->assertSee('Credit Overload')
            ->assertSee('Open basket source list');

        $this->actingAs($chair)
            ->get(route('academics.pmc.student-course-baskets.index'))
            ->assertOk()
            ->assertSee('Course Basket Diagnostics')
            ->assertSee('Readiness signals that must be cleared')
            ->assertSee('Student Course Baskets');
    }

    public function test_credit_overload_basket_is_counted_as_a_launch_blocker(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();
        $department = Department::firstOrCreate(['code' => 'V077'], ['name' => 'v077 Department']);
        $program = Program::create([
            'department_id' => $department->id,
            'name' => 'v077 Program',
            'code' => 'V077-PGM',
            'is_active' => true,
        ]);
        $subject = Subject::create([
            'department_id' => $department->id,
            'program_id' => $program->id,
            'name' => 'v077 Overload Course',
            'code' => 'V077-OVERLOAD',
            'credits' => 3,
            'type' => 'core',
            'hours_per_week' => 3,
            'is_active' => true,
        ]);

        $batch = AcademicPmcCourseAllocationBatch::create([
            'title' => 'v077 overload batch',
            'owner_user_id' => $chair->id,
            'status' => 'approved',
            'student_count' => 1,
            'core_allocations' => 1,
            'rules' => ['max_credits' => 1],
        ]);

        AcademicPmcStudentCourseAllocation::create([
            'allocation_batch_id' => $batch->id,
            'subject_id' => $subject->id,
            'allocation_type' => 'core',
            'allocation_source' => 'pmc_test',
            'approval_status' => 'allocated',
            'basket_status' => 'approved',
            'validation_flags' => [],
        ]);

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-os.index'))
            ->assertOk()
            ->assertSee('Credit Overload')
            ->assertSee('Review basket blockers before section/group locking.');
    }
}
