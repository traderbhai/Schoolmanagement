<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsIqacUxGuidanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_iqac_dashboard_explains_quality_operating_sequence(): void
    {
        $iqac = User::where('email', 'iqac.head@college.com')->firstOrFail();

        $this->actingAs($iqac)
            ->get(route('academics.iqac.index'))
            ->assertOk()
            ->assertSee('IQAC quality operating sequence')
            ->assertSee('Each KPI opens the scoped source list behind the count')
            ->assertSee('Owner: IQAC quality team')
            ->assertSee('Source: OBE mapping, attainment, feedback, audit evidence, corrective actions')
            ->assertSee('Owner / Source')
            ->assertSee('1. Close OBE readiness gaps')
            ->assertSee('2. Review CO/PO attainment misses')
            ->assertSee('3. Track feedback closure')
            ->assertSee('4. Collect audit evidence')
            ->assertSee('5. Create corrective actions')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false);
    }

    public function test_iqac_source_lists_explain_quality_signal_to_closure_workflow(): void
    {
        $iqac = User::where('email', 'iqac.head@college.com')->firstOrFail();

        foreach ([
            'academics.iqac.obe-readiness',
            'academics.iqac.attainment-monitoring',
            'academics.iqac.feedback-quality',
            'academics.iqac.audit-compliance',
            'academics.iqac.reports',
        ] as $route) {
            $this->actingAs($iqac)
                ->get(route($route))
                ->assertOk()
                ->assertSee('IQAC source-list workflow')
                ->assertSee('Owner: IQAC quality team')
                ->assertSee('Source:')
                ->assertSee('1. Filter program/term/status')
                ->assertSee('2. Review quality gap')
                ->assertSee('3. Open source workflow')
                ->assertSee('4. Check evidence/action owner')
                ->assertSee('5. Export current view')
                ->assertSee('Visible filter summary')
                ->assertSee('Export current view')
                ->assertSee('Owner / Source')
                ->assertDontSee('href="#"', false)
                ->assertDontSee('Whoops', false)
                ->assertDontSee('SERVICE ERROR', false);
        }
    }

    public function test_iqac_empty_filtered_source_list_explains_quality_review_boundaries(): void
    {
        $iqac = User::where('email', 'iqac.head@college.com')->firstOrFail();

        $this->actingAs($iqac)
            ->get(route('academics.iqac.audit-compliance', ['search' => 'no-matching-quality-record']))
            ->assertOk()
            ->assertSee('No IQAC records match this source list')
            ->assertSee('current program/term/status filters have no quality gaps')
            ->assertSee('source workflows have not yet created OBE mapping, attainment, feedback, audit evidence, or corrective-action records')
            ->assertSee('recheck owner assignment, evidence availability, action status, and target/threshold boundaries')
            ->assertDontSee('No IQAC records match the current scope and filters')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false);
    }
}
