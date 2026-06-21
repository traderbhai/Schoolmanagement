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

    public function test_dean_critical_attention_card_opens_matching_aggregate_queue(): void
    {
        $dean = User::where('email', 'dean@college.com')->firstOrFail();
        $criticalQueue = app(\App\Services\AcademicDeanAttentionService::class)->queue('critical_attention');

        $this->actingAs($dean)
            ->get(route('academics.dean-os.index'))
            ->assertOk()
            ->assertSee('Critical Attention')
            ->assertSee(route('academics.dean-os.attention', 'critical_attention'), false)
            ->assertSee('Open Critical Attention source list', false)
            ->assertDontSee('Summary only');

        $this->actingAs($dean)
            ->get(route('academics.dean-os.attention', 'critical_attention'))
            ->assertOk()
            ->assertSee('Critical Attention')
            ->assertSee('Visible filter: queue = critical_attention | Records = ' . $criticalQueue['count']);
    }

    public function test_dean_empty_attention_queue_explains_next_steps(): void
    {
        $dean = User::where('email', 'dean@college.com')->firstOrFail();

        DB::table('approval_workflows')->delete();

        $this->actingAs($dean)
            ->get(route('academics.dean-os.attention', 'overdue_dean_approvals'))
            ->assertOk()
            ->assertSee('Overdue Dean Approvals')
            ->assertSee('Visible filter: queue = overdue_dean_approvals | Records = 0')
            ->assertSee('No open Dean attention records')
            ->assertSee('Continue from the Dean OS dashboard, review another queue, or create an action item')
            ->assertDontSee('No records in this queue.');
    }
}
