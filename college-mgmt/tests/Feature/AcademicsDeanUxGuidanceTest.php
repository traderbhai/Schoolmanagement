<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsDeanUxGuidanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_dean_dashboard_explains_daily_command_sequence(): void
    {
        $dean = User::where('email', 'dean@college.com')->firstOrFail();

        $this->actingAs($dean)
            ->get(route('academics.dean-os.index'))
            ->assertOk()
            ->assertSee('Dean daily command sequence')
            ->assertSee('Open today priority')
            ->assertSee('Clear overdue approvals')
            ->assertSee('Review critical risks')
            ->assertSee('Assign branch actions')
            ->assertSee('Check handoff blockers')
            ->assertSee('Each linked metric opens the source list')
            ->assertSee('Owner:')
            ->assertSee('Source:')
            ->assertSee('Critical attention queue')
            ->assertSee('Due')
            ->assertSee('Open Critical Attention source list', false)
            ->assertDontSee('Summary only')
            ->assertDontSee('Unassigned')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_dean_planning_reviews_and_approvals_explain_operating_order(): void
    {
        $dean = User::where('email', 'dean@college.com')->firstOrFail();

        $this->actingAs($dean)
            ->get(route('academics.dean-os.planning.index'))
            ->assertOk()
            ->assertSee('Academic planning sequence')
            ->assertSee('Create annual or semester plan')
            ->assertSee('Check readiness blockers')
            ->assertSee('Create action for blocker')
            ->assertSee('Approve only when evidence is ready')
            ->assertSee('Planning records coordinate PMC, CoE, IQAC, Program Leadership, and Course Delivery')
            ->assertDontSee('href="#"', false);

        $this->actingAs($dean)
            ->get(route('academics.dean-os.reviews'))
            ->assertOk()
            ->assertSee('Review-to-action sequence')
            ->assertSee('Create review meeting')
            ->assertSee('Assign action owner')
            ->assertSee('Close with note/evidence')
            ->assertSee('formal Dean review and accountable follow-up')
            ->assertDontSee('href="#"', false);

        $this->actingAs($dean)
            ->get(route('academics.dean-os.approval-cockpit.index'))
            ->assertOk()
            ->assertSee('Approval cockpit sequence')
            ->assertSee('Review pending and overdue')
            ->assertSee('Request evidence when unclear')
            ->assertSee('Keep final decisions locked')
            ->assertSee('final rows preserve the decision trail')
            ->assertDontSee('href="#"', false);
    }

    public function test_dean_risk_operating_records_and_analytics_explain_follow_up_workflow(): void
    {
        $dean = User::where('email', 'dean@college.com')->firstOrFail();

        $this->actingAs($dean)
            ->get(route('academics.dean-os.risk-settings.index'))
            ->assertOk()
            ->assertSee('Risk governance sequence')
            ->assertSee('Check thresholds')
            ->assertSee('Capture snapshot')
            ->assertSee('Assign mitigation')
            ->assertSee('High and critical rows should have an owner')
            ->assertDontSee('href="#"', false);

        $this->actingAs($dean)
            ->get(route('academics.dean-os.faculty-workload.index'))
            ->assertOk()
            ->assertSee('Operating record workflow')
            ->assertSee('Filter by status or severity')
            ->assertSee('Sort by due date or score')
            ->assertSee('Save useful view')
            ->assertSee('Export current scope')
            ->assertDontSee('href="#"', false);

        $this->actingAs($dean)
            ->get(route('academics.dean-os.analytics.index'))
            ->assertOk()
            ->assertSee('Management review pack sequence')
            ->assertSee('Review trend panels')
            ->assertSee('Generate scheduled pack')
            ->assertSee('Convert findings into review actions')
            ->assertDontSee('href="#"', false);
    }
}
