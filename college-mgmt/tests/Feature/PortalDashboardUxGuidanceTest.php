<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalDashboardUxGuidanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_student_dashboard_explains_daily_self_service_sequence(): void
    {
        $student = User::where('email', 'arjun.k@demo.edu')->firstOrFail();

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Student daily sequence')
            ->assertSee('Use each card link to open your own source records only')
            ->assertSee('1. Check priority')
            ->assertSee('2. Attend classes')
            ->assertSee('3. Submit due work')
            ->assertSee('4. Clear fees or blockers')
            ->assertSee('5. Ask for help if stuck')
            ->assertSee('Owner:')
            ->assertSee('Source:')
            ->assertSee('Open attendance details')
            ->assertSee('Open current SGPA and result details')
            ->assertSee('Open CGPA and result details')
            ->assertSee('Open fee outstanding details')
            ->assertSee(route('student.attendance'), false)
            ->assertSee(route('student.results'), false)
            ->assertSee(route('student.fees'), false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false);
    }

    public function test_teacher_dashboard_explains_daily_teaching_sequence(): void
    {
        $teacher = User::where('email', 'anjali@demo.edu')->firstOrFail();

        $this->actingAs($teacher)
            ->get(route('teacher.dashboard'))
            ->assertOk()
            ->assertSee('Teacher daily sequence')
            ->assertSee('move from class readiness to attendance, assignments, materials, marks, and mentoring')
            ->assertSee('1. Review timetable')
            ->assertSee('2. Mark attendance')
            ->assertSee('3. Grade submissions')
            ->assertSee('4. Upload materials')
            ->assertSee('5. Follow up mentees')
            ->assertSee('Open weekly load timetable')
            ->assertSee("Open today's classes timetable", false)
            ->assertSee('Open attendance marking')
            ->assertSee(route('teacher.timetable.index'), false)
            ->assertSee(route('teacher.attendance.mark'), false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false);
    }

    public function test_parent_dashboard_explains_child_monitoring_sequence(): void
    {
        $parent = User::where('email', 'parent@demo.edu')->firstOrFail();

        $this->actingAs($parent)
            ->get(route('parent.dashboard'))
            ->assertOk()
            ->assertSee('Parent monitoring sequence')
            ->assertSee('Start with the priority card')
            ->assertSee('1. Check parent priority')
            ->assertSee('2. Review child alerts')
            ->assertSee('3. Open attendance/results/fees')
            ->assertSee('4. Read notices')
            ->assertSee('5. Contact institute if blocked')
            ->assertSee('Owner:')
            ->assertSee('Source:')
            ->assertSee('Open attendance details for')
            ->assertSee('Open result details for')
            ->assertSee('Open fee details for')
            ->assertDontSee('N/A')
            ->assertSee('/parent/children/', false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false);
    }
}
