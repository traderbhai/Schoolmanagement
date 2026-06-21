<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AcademicPmcOperatingService;
use App\Services\AcademicPmcTimetableV041Service;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcKpiDrilldownConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_pmc_dashboard_kpis_match_filtered_drilldowns(): void
    {
        $user = User::where('email', 'chair@college.com')->firstOrFail();
        $service = app(AcademicPmcOperatingService::class);
        $dashboard = $service->dashboard($user);

        $cases = [
            ['kpi' => 'programs', 'section' => 'programs', 'metric' => 'active_programs', 'route' => 'academics.pmc.programs'],
            ['kpi' => 'curriculum_gaps', 'section' => 'curriculum-readiness', 'metric' => 'curriculum_gaps', 'route' => 'academics.pmc.curriculum-readiness'],
            ['kpi' => 'faculty_gaps', 'section' => 'faculty-allocation', 'metric' => 'faculty_gaps', 'route' => 'academics.pmc.faculty-allocation'],
            ['kpi' => 'student_risk', 'section' => 'student-monitoring', 'metric' => 'student_risk', 'route' => 'academics.pmc.student-monitoring'],
        ];

        foreach ($cases as $case) {
            $drilldown = $service->section($user, $case['section'], ['metric' => $case['metric']]);

            $this->assertSame($dashboard['kpis'][$case['kpi']], $drilldown['items']->count(), $case['kpi']);

            $this->actingAs($user)
                ->get(route($case['route'], ['metric' => $case['metric']]))
                ->assertOk()
                ->assertSee('Filtered Source List (' . $dashboard['kpis'][$case['kpi']] . ')')
                ->assertSee('Visible filter summary: Metric: ' . $case['metric']);
        }
    }

    public function test_pmc_section_metric_cards_are_real_drilldowns_not_anchor_placeholders(): void
    {
        $user = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($user)
            ->get(route('academics.pmc.curriculum-readiness'))
            ->assertOk()
            ->assertDontSee('href="#source-list"', false)
            ->assertSee('metric=mapping_gaps', false)
            ->assertSee('Export current view');
    }

    public function test_pmc_scoped_programs_card_opens_filtered_program_source_list(): void
    {
        $user = User::where('email', 'chair@college.com')->firstOrFail();
        $service = app(AcademicPmcOperatingService::class);
        $dashboard = $service->dashboard($user);

        $this->actingAs($user)
            ->get(route('academics.pmc.index'))
            ->assertOk()
            ->assertSee('Scoped Programs')
            ->assertSee(route('academics.pmc.programs', ['metric' => 'active_programs']), false)
            ->assertDontSee('Summary only');

        $this->actingAs($user)
            ->get(route('academics.pmc.programs', ['metric' => 'active_programs']))
            ->assertOk()
            ->assertSee('Filtered Source List (' . $dashboard['kpis']['programs'] . ')')
            ->assertSee('Visible filter summary: Metric: active_programs');
    }

    public function test_pmc_section_empty_state_explains_scope_filters_and_next_steps(): void
    {
        $user = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($user)
            ->get(route('academics.pmc.faculty-allocation', ['search' => 'NO_MATCH_PMC_SCOPE_999']))
            ->assertOk()
            ->assertSee('Faculty Allocation')
            ->assertSee('Filtered Source List (0)')
            ->assertSee('Visible filter summary: Search: NO_MATCH_PMC_SCOPE_999')
            ->assertSee('No PMC records match this view')
            ->assertSee('Clear filters, check your assigned program/batch scope, or create/update the source workflow')
            ->assertDontSee('No records match the current PMC scope.');
    }

    public function test_pmc_timetable_conflict_kpis_match_filtered_planner_panel(): void
    {
        $user = User::where('email', 'chair@college.com')->firstOrFail();
        $service = app(AcademicPmcTimetableV041Service::class);
        $dashboard = $service->dashboard($user);

        foreach (['hard' => 'hard_conflicts', 'soft' => 'soft_warnings'] as $severity => $kpi) {
            $surface = $service->surface($user, 'timetable-planner', ['severity' => $severity]);

            $this->assertSame($dashboard['kpis'][$kpi], $surface['constraints']->total(), $kpi);

            $this->actingAs($user)
                ->get(route('academics.pmc.timetable-planner.index', ['severity' => $severity]))
                ->assertOk()
                ->assertSee('Visible filter summary: severity=' . $severity)
                ->assertSee('Filtered Source List (' . $dashboard['kpis'][$kpi] . ')');
        }
    }

    public function test_pmc_timetable_quality_score_opens_quality_source_surface(): void
    {
        $user = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($user)
            ->get(route('academics.pmc.timetable-os.index'))
            ->assertOk()
            ->assertSee('Quality Score')
            ->assertSee(route('academics.pmc.timetable-quality.index'), false)
            ->assertSee('Open Quality Score source list', false)
            ->assertDontSee('Summary score');

        $this->actingAs($user)
            ->get(route('academics.pmc.timetable-quality.index'))
            ->assertOk()
            ->assertSee('PMC Constraint-Based Timetable Generator')
            ->assertSee('Visible filter summary:');
    }
}
