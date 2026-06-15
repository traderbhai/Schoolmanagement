<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseGroup;
use App\Models\Department;
use App\Models\Program;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcTimetableV078Test extends TestCase
{
    use RefreshDatabase;

    public function test_section_group_diagnostics_render_on_dashboard_and_group_builder(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-os.index'))
            ->assertOk()
            ->assertSee('Section And Group Diagnostics')
            ->assertSee('Unlocked')
            ->assertSee('Under Min')
            ->assertSee('Over Capacity')
            ->assertSee('No Faculty')
            ->assertSee('Strength Mismatch')
            ->assertSee('Open group source list');

        $this->actingAs($chair)
            ->get(route('academics.pmc.course-groups.index'))
            ->assertOk()
            ->assertSee('Section And Group Diagnostics')
            ->assertSee('Resolve capacity, lock, membership, faculty, and adjustment blockers')
            ->assertSee('Sections And Groups');
    }

    public function test_over_capacity_group_without_faculty_is_counted_as_launch_blocker(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();
        $department = Department::firstOrCreate(['code' => 'V078'], ['name' => 'v078 Department']);
        $program = Program::create([
            'department_id' => $department->id,
            'name' => 'v078 Program',
            'code' => 'V078-PGM',
            'is_active' => true,
        ]);
        $subject = Subject::create([
            'department_id' => $department->id,
            'program_id' => $program->id,
            'name' => 'v078 Group Course',
            'code' => 'V078-GROUP',
            'credits' => 3,
            'type' => 'core',
            'hours_per_week' => 3,
            'is_active' => true,
        ]);

        AcademicPmcCourseGroup::create([
            'name' => 'v078 Over Capacity Group',
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'owner_user_id' => $chair->id,
            'min_capacity' => 5,
            'max_capacity' => 10,
            'current_strength' => 15,
            'status' => 'draft',
            'is_locked' => false,
        ]);

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-os.index'))
            ->assertOk()
            ->assertSee('Over Capacity')
            ->assertSee('No Faculty')
            ->assertSee('Resolve group capacity, locking, faculty, and membership blockers before timetable generation.');
    }
}
