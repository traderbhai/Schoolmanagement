<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsProgramLeadershipUxGuidanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_program_leadership_dashboard_explains_operating_sequence(): void
    {
        $leader = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($leader)
            ->get(route('academics.program-leadership.index'))
            ->assertOk()
            ->assertSee('Program leadership operating sequence')
            ->assertSee('Each KPI opens the scoped source list behind the count')
            ->assertSee('1. Review portfolio scope')
            ->assertSee('2. Clear course delivery gaps')
            ->assertSee('3. Triage student risk')
            ->assertSee('4. Check quality signals')
            ->assertSee('5. Escalate through Chair workflows')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false);
    }

    public function test_program_leadership_source_lists_explain_signal_to_action_workflow(): void
    {
        $leader = User::where('email', 'chair@college.com')->firstOrFail();

        foreach ([
            'academics.program-leadership.portfolio',
            'academics.program-leadership.course-delivery',
            'academics.program-leadership.student-success',
            'academics.program-leadership.quality-signals',
            'academics.program-leadership.reports',
        ] as $route) {
            $this->actingAs($leader)
                ->get(route($route))
                ->assertOk()
                ->assertSee('Program source-list workflow')
                ->assertSee('1. Filter program/status')
                ->assertSee('2. Review risk or blocker')
                ->assertSee('3. Open source workflow')
                ->assertSee('4. Assign or escalate action')
                ->assertSee('5. Export current view')
                ->assertSee('Visible filter summary')
                ->assertSee('Export current view')
                ->assertDontSee('href="#"', false)
                ->assertDontSee('Whoops', false)
                ->assertDontSee('SERVICE ERROR', false);
        }
    }
}
