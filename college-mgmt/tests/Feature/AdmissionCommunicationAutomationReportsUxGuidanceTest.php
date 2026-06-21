<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SeatMatrix;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionCommunicationAutomationReportsUxGuidanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_communication_hub_explains_safe_send_sequence(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();

        $this->actingAs($head)
            ->get(route('admission.communication.index'))
            ->assertOk()
            ->assertSee('Communication safety sequence')
            ->assertSee('Create template')
            ->assertSee('Check consent and approval')
            ->assertSee('Queue message')
            ->assertSee('Dispatch through provider')
            ->assertSee('Monitor delivery status')
            ->assertSee('Use Bulk Communication for audience sends')
            ->assertSee('Recent Messages')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_bulk_communication_and_safety_pages_explain_recipient_controls(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();

        $this->actingAs($head)
            ->get(route('admission.bulk-communication.index'))
            ->assertOk()
            ->assertSee('Bulk-send workflow')
            ->assertSee('Filter audience')
            ->assertSee('Preview recipients')
            ->assertSee('Confirm consent and duplicates')
            ->assertSee('Send and monitor delivery')
            ->assertSee('Any Status')
            ->assertSee('Any Program')
            ->assertSee('Any Batch')
            ->assertDontSee('â', false)
            ->assertDontSee('Ã', false)
            ->assertDontSee('N/A', false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);

        $this->actingAs($head)
            ->get(route('admission.bulk-communication.index', ['filter_status' => 'no_matching_status']))
            ->assertOk()
            ->assertSeeText('No applicants match the selected filters')
            ->assertSeeText('Clear filters or adjust one filter at a time before composing a bulk message.')
            ->assertSee(route('admission.bulk-communication.index'), false)
            ->assertDontSee('No applicants match the selected filters.', false)
            ->assertDontSee('â', false)
            ->assertDontSee('Ã', false)
            ->assertDontSee('N/A', false);

        $this->actingAs($head)
            ->get(route('admission.communication-safety.index'))
            ->assertOk()
            ->assertSee('Safety gate sequence')
            ->assertSee('Capture consent')
            ->assertSee('Approve template')
            ->assertSee('Block opt-outs and duplicates')
            ->assertSee('Delay quiet-hour sends')
            ->assertSee('campaigns, reminders, automations, assessment messages, offers, and parent journeys')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_automation_page_explains_rule_impact_and_safety(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();

        $this->actingAs($head)
            ->get(route('admission.automations.index'))
            ->assertOk()
            ->assertSee('Automation operating sequence')
            ->assertSee('Define trigger')
            ->assertSee('Add conditions')
            ->assertSee('Preview impact')
            ->assertSee('Review execution log')
            ->assertSee('Rules should be idempotent and scoped')
            ->assertSee('approved templates, consent, quiet hours, and provider availability')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_reports_page_explains_how_to_interpret_admission_metrics(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();
        SeatMatrix::query()->delete();

        $this->actingAs($head)
            ->get(route('admission.reports.index'))
            ->assertOk()
            ->assertSee('Report interpretation workflow')
            ->assertSee('Start with funnel totals')
            ->assertSee('Compare source and program conversion')
            ->assertSee('Review category and compliance gaps')
            ->assertSee('Check counsellor and geography signals')
            ->assertSee('Export with current context')
            ->assertSee('open the matching operational list')
            ->assertSee('Seat intake not configured')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('N/A', false)
            ->assertDontSee('â', false)
            ->assertDontSee('Ã', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_reports_empty_state_copy_explains_required_source_data(): void
    {
        $this->actingAs(User::where('email', 'head@college.com')->firstOrFail());
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());

        $html = view('admission.reports.index', [
            'funnel' => [
                ['label' => 'Total Leads', 'count' => 0, 'color' => '#6366f1'],
                ['label' => 'Applications', 'count' => 0, 'color' => '#3b82f6'],
            ],
            'funnelMax' => 1,
            'monthlyTrend' => [
                ['label' => now()->format('M Y'), 'count' => 0],
            ],
            'trendMax' => 1,
            'programStats' => collect(),
            'sourceStats' => collect(),
            'categoryStats' => collect(),
            'totalLeads' => 0,
            'totalApplicants' => 0,
            'selected' => 0,
            'enrolled' => 0,
            'yoyData' => [
                ['year' => now()->year, 'applicants' => 0, 'enrolled' => 0],
            ],
            'categoryCompliance' => [
                ['category' => 'SC', 'mandate_pct' => 15, 'mandate_seats' => 0, 'filled' => 0, 'fill_pct' => 0, 'compliant' => true],
            ],
            'counsellorStats' => collect(),
            'geoStats' => collect(),
            'totalIntake' => 0,
        ])->render();

        $this->assertStringContainsString('Capture or import leads with a source value', $html);
        $this->assertStringContainsString('No active programs are available for admission reporting', $html);
        $this->assertStringContainsString('No applicant category data is available yet', $html);
        $this->assertStringContainsString('Seat intake not configured', $html);
        $this->assertStringContainsString('No counsellor lead assignments are available yet', $html);
        $this->assertStringContainsString('No geographic data is available yet', $html);
        $this->assertStringNotContainsString('N/A', $html);
        $this->assertStringNotContainsString('â', $html);
        $this->assertStringNotContainsString('Ã', $html);
    }
}
