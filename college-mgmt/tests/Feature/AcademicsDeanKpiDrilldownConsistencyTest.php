<?php

namespace Tests\Feature;

use App\Models\AcademicDeanActionItem;
use App\Models\User;
use App\Services\AcademicDeanCommandService;
use App\Services\AcademicDeanRiskService;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AcademicsDeanKpiDrilldownConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_dean_dashboard_action_risk_and_handoff_kpis_match_filtered_pages(): void
    {
        $dean = User::where('email', 'dean@college.com')->firstOrFail();
        $dashboard = app(AcademicDeanCommandService::class)->dashboard($dean);
        $riskCount = app(AcademicDeanRiskService::class)->programRisks()->whereIn('band', ['critical', 'high'])->count();
        $openActionCount = AcademicDeanActionItem::whereNotIn('status', ['done', 'cancelled'])->count();
        $handoffCount = DB::getSchemaBuilder()->hasTable('admission_handoff_records')
            ? DB::table('admission_handoff_records')
                ->whereIn('status', ['blocked', 'pending_admission_completion', 'returned_for_correction'])
                ->count()
            : 0;

        $this->assertSame($openActionCount, $dashboard['kpis']['open_actions']);
        $this->assertSame($riskCount, $dashboard['kpis']['critical_program_risks']);
        $this->assertSame($handoffCount, $dashboard['kpis']['handoff_blockers']);

        $this->actingAs($dean)
            ->get(route('academics.dean-os.reviews', ['status' => 'open']))
            ->assertOk()
            ->assertSee('Action Tracker (' . $openActionCount . ')')
            ->assertSee('Visible filter summary: Status: open');

        $this->actingAs($dean)
            ->get(route('academics.dean-os.program-risk', ['band' => 'critical_high']))
            ->assertOk()
            ->assertSee('Total: ' . $riskCount)
            ->assertSee('Visible filter summary: Band: critical_high');

        $this->actingAs($dean)
            ->get(route('academics.dean-os.handoff', ['status' => 'blocking']))
            ->assertOk()
            ->assertSee('Filtered Source List (' . $handoffCount . ')')
            ->assertSee('Visible filter summary: Status: blocking');
    }

    public function test_dean_aggregate_attention_card_is_summary_only_not_fake_drilldown(): void
    {
        $dean = User::where('email', 'dean@college.com')->firstOrFail();

        $this->actingAs($dean)
            ->get(route('academics.dean-os.index'))
            ->assertOk()
            ->assertSee('Critical Attention')
            ->assertSee('Summary only')
            ->assertDontSee(route('academics.dean-os.attention', 'action_items_overdue') . '\"', false);
    }
}
