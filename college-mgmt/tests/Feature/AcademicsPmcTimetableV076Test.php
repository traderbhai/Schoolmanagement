<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcTimetableV076Test extends TestCase
{
    use RefreshDatabase;

    public function test_pmc_timetable_os_shows_guided_semester_launch_control(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-os.index'))
            ->assertOk()
            ->assertSee('Semester Launch Control')
            ->assertSee('Course baskets')
            ->assertSee('Sections and groups')
            ->assertSee('Faculty allocation')
            ->assertSee('Readiness inputs')
            ->assertSee('Generate and validate')
            ->assertSee('Publish and notify')
            ->assertSee('Next PMC action')
            ->assertSee(route('academics.pmc.student-course-baskets.index'), false)
            ->assertSee(route('academics.pmc.timetable-reports.index', ['status' => 'failed']), false);
    }

    public function test_dean_has_oversight_of_guided_semester_launch_control(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $dean = User::where('email', 'dean@college.com')->firstOrFail();

        $this->actingAs($dean)
            ->get(route('academics.pmc.timetable-os.index'))
            ->assertOk()
            ->assertSee('Semester Launch Control')
            ->assertSee('Ordered PMC launch sequence');
    }
}
