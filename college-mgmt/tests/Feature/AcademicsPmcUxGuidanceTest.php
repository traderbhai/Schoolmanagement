<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcUxGuidanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_pmc_command_explains_daily_operating_sequence(): void
    {
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($chair)
            ->get(route('academics.pmc.command'))
            ->assertOk()
            ->assertSee('PMC daily operating sequence')
            ->assertSee('Check semester readiness')
            ->assertSee('Clear curriculum and subject blockers')
            ->assertSee('Resolve faculty load gaps')
            ->assertSee('Fix timetable conflicts')
            ->assertSee('Close student/delivery actions')
            ->assertSee('Each metric links to the scoped source list')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_pmc_v004_surfaces_explain_record_and_readiness_workflow(): void
    {
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($chair)
            ->get(route('academics.pmc.planning.index'))
            ->assertOk()
            ->assertSee('PMC record workflow')
            ->assertSee('Filter scope')
            ->assertSee('Review open/critical/overdue')
            ->assertSee('Create action for blocker')
            ->assertSee('Update owner and evidence')
            ->assertSee('Export current view')
            ->assertSee('Move plans through review only after readiness evidence and blockers are clear')
            ->assertSee('Blockers should become owned work items before publication or Dean escalation')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_pmc_timetable_dashboard_explains_pre_timetable_sequence(): void
    {
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-os.index'))
            ->assertOk()
            ->assertSee('Timetable build sequence')
            ->assertSee('Allocate student course baskets')
            ->assertSee('Build sections and elective groups')
            ->assertSee('Assign faculty to exact groups')
            ->assertSee('Lock fixed slots and rooms')
            ->assertSee('Generate, validate, approve, freeze')
            ->assertSee('Hard conflicts block publish')
            ->assertSee('Open Quality Score source list', false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_pmc_v041_source_surfaces_explain_how_to_fix_records_before_generation(): void
    {
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        foreach ([
            'academics.pmc.course-allocation.index',
            'academics.pmc.student-course-baskets.index',
            'academics.pmc.course-groups.index',
            'academics.pmc.section-faculty-allocation.index',
            'academics.pmc.timetable-generator.index',
            'academics.pmc.timetable-planner.index',
        ] as $routeName) {
            $this->actingAs($chair)
                ->get(route($routeName))
                ->assertOk()
                ->assertSee('PMC timetable source workflow')
                ->assertSee('Filter program/batch/term')
                ->assertSee('Resolve diagnostics')
                ->assertSee('Update source records')
                ->assertSee('Recheck readiness')
                ->assertSee('Export or continue to generator')
                ->assertSee('Fix records here before generating, publishing, freezing, or notifying students and faculty')
                ->assertDontSee('href="#"', false)
                ->assertDontSee('Whoops', false)
                ->assertDontSee('SERVICE ERROR', false)
                ->assertDontSee('Laravel\\', false);
        }
    }
}
