<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Applicant;
use App\Models\AdmissionCommunicationLog;
use App\Models\AdmissionCommunicationTemplate;
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
        AdmissionCommunicationLog::query()->delete();
        AdmissionCommunicationTemplate::query()->delete();

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
            ->assertSee('No communication templates are configured yet.')
            ->assertSee('Create an approved template before counsellors, reminders, automations, assessments, offers, or parent journeys can queue reusable messages.')
            ->assertSee('No communication logs are visible in this scope yet.')
            ->assertSee('Messages appear here after a lead, applicant, reminder, automation, assessment, offer, or bulk-send workflow queues communication through the safety service.')
            ->assertSee('Bulk Communication')
            ->assertSee('Communication Safety')
            ->assertSee('Reminder Queue')
            ->assertSee(route('admission.bulk-communication.index'), false)
            ->assertSee(route('admission.communication-safety.index'), false)
            ->assertSee(route('admission.reminders.index'), false)
            ->assertDontSee('No templates.')
            ->assertDontSee('No messages yet.')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('N/A', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_reminder_queue_prioritizes_send_action_and_deemphasizes_secondary_actions(): void
    {
        $manager = User::where('email', 'admission.manager@college.com')->firstOrFail();

        $this->actingAs($manager)
            ->get(route('admission.reminders.index'))
            ->assertOk()
            ->assertSee('Reminder Queue')
            ->assertSee('btn btn-sm btn-success', false)
            ->assertSee('Send')
            ->assertSee('Done')
            ->assertSee('Pause')
            ->assertSee('Mark this reminder as completed after the student or lead action has been recorded?', false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_admission_payment_verification_confirms_amount_applicant_and_reference_checks(): void
    {
        foreach ([
            'admission/payments/queue.blade.php',
            'admission/payments/applicant.blade.php',
        ] as $view) {
            $contents = file_get_contents(resource_path("views/{$view}"));

            $this->assertStringContainsString('Verify payment of Rs.', $contents);
            $this->assertStringContainsString('Confirm bank/gateway reference, proof file, installment, and applicant record before marking it verified.', $contents);
            $this->assertStringNotContainsString("confirm('Verify this payment?')", $contents);
        }
    }

    public function test_bulk_communication_and_safety_pages_explain_recipient_controls(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();
        $applicant = Applicant::with(['user', 'program'])->whereNotNull('program_id')->firstOrFail();

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
            ->assertSee('No audience preview yet')
            ->assertSee('The send form stays hidden until staff can see the matching applicants and confirm the audience source.')
            ->assertSee(route('admission.communication-safety.index'), false)
            ->assertSee(route('admission.communication.index'), false)
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
            ->assertSee('Active filters:')
            ->assertSee('Status: No Matching Status')
            ->assertSeeText('Clear filters or adjust one filter at a time before composing a bulk message.')
            ->assertSee(route('admission.bulk-communication.index'), false)
            ->assertSee(route('admission.applicants.index'), false)
            ->assertDontSee('No applicants match the selected filters.', false)
            ->assertDontSee('â', false)
            ->assertDontSee('Ã', false)
            ->assertDontSee('N/A', false);

        $this->actingAs($head)
            ->get(route('admission.bulk-communication.index', [
                'filter_status' => $applicant->status,
                'filter_program_id' => $applicant->program_id,
            ]))
            ->assertOk()
            ->assertSee('recipient(s) selected')
            ->assertSee('Audience filter summary:')
            ->assertSee('Status: ' . ucwords(str_replace('_', ' ', $applicant->status)))
            ->assertSee('Program: ' . $applicant->program->name)
            ->assertSee($applicant->user->name)
            ->assertSee('#' . $applicant->application_number)
            ->assertSee('Email: ' . $applicant->user->email)
            ->assertSee('Open Communication Safety before high-volume send')
            ->assertSee(route('admission.communication-safety.index'), false)
            ->assertDontSee('N/A', false)
            ->assertDontSee('href="#"', false);

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

    public function test_integration_health_actions_explain_retry_and_check_impact(): void
    {
        $contents = file_get_contents(resource_path('views/admission/v0038/integration-health.blade.php'));

        $this->assertStringContainsString('Run integration health check', $contents);
        $this->assertStringContainsString('Retry integration job', $contents);
        $this->assertStringContainsString('refresh provider status, credential readiness, webhook health, and retry diagnostics', $contents);
        $this->assertStringContainsString('the applicant communication or webhook payload will not be duplicated incorrectly', $contents);
        $this->assertStringNotContainsString('>Retry</button>', $contents);
    }

    public function test_admission_operating_actions_use_specific_safety_labels(): void
    {
        $communicationSafety = file_get_contents(resource_path('views/admission/v0038/communication-safety.blade.php'));
        $callingDesk = file_get_contents(resource_path('views/admission/v0038/calling-desk.blade.php'));
        $integrations = file_get_contents(resource_path('views/admission/v0037/integrations.blade.php'));
        $conflicts = file_get_contents(resource_path('views/admission/v0037/assessment-schedule-conflicts.blade.php'));

        foreach ([
            'Save communication consent',
            'Request template approval',
            'Preview safe audience',
            'Approve template version',
            'downstream campaign/reminder impact',
            'consent rules, quiet-hour handling, duplicate blocking, and opt-out checks',
        ] as $snippet) {
            $this->assertStringContainsString($snippet, $communicationSafety);
        }

        foreach ([
            'Save call outcome',
            'Skip current call record',
            'Confirm disposition, admission intent, script coverage, retry time, next action, and candidate timeline impact',
            'Confirm the reason, retry ownership, and queue impact',
        ] as $snippet) {
            $this->assertStringContainsString($snippet, $callingDesk);
        }

        foreach ([
            'sandbox test',
            'Retry provider delivery',
            'Confirm sandbox credentials, test recipient behavior, webhook capture, and provider rate limits',
            'Confirm the failure reason, recipient, provider health, consent state, and duplicate-message risk',
        ] as $snippet) {
            $this->assertStringContainsString($snippet, $integrations);
        }

        $this->assertStringContainsString('Refresh panel conflicts', $conflicts);
        $this->assertStringContainsString('Confirm evaluator availability, room/resource usage, capacity, rubric readiness, and double-booking impact', $conflicts);
        $this->assertStringNotContainsString('>Save Consent</button>', $communicationSafety);
        $this->assertStringNotContainsString('>Request</button>', $communicationSafety);
        $this->assertStringNotContainsString('>Preview Audience</button>', $communicationSafety);
        $this->assertStringNotContainsString('>Save Call Outcome</button>', $callingDesk);
        $this->assertStringNotContainsString('>Skip This Record</button>', $callingDesk);
        $this->assertStringNotContainsString('>Sandbox Test</button>', $integrations);
        $this->assertStringNotContainsString('>Retry</button>', $integrations);
        $this->assertStringNotContainsString('>Refresh</button>', $conflicts);
        $this->assertStringNotContainsString(' Â· ', $conflicts);
    }
}
