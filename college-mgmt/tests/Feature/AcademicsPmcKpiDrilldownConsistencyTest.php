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

    public function test_pmc_scoped_programs_card_is_summary_only_until_program_source_list_exists(): void
    {
        $user = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($user)
            ->get(route('academics.pmc.index'))
            ->assertOk()
            ->assertSee('Scoped Programs')
            ->assertSee('Summary only');
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

    public function test_pmc_timetable_quality_score_is_summary_only(): void
    {
        $user = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($user)
            ->get(route('academics.pmc.timetable-os.index'))
            ->assertOk()
            ->assertSee('Quality Score')
            ->assertSee('Summary score')
            ->assertDontSee('>Quality Score</div><div class="h4 mb-0">', false);
    }
}
