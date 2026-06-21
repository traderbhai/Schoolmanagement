<?php

namespace Tests\Feature;

use App\Models\AdmissionManagerReview;
use App\Models\AdmissionReminderSchedule;
use App\Models\Lead;
use App\Models\User;
use App\Services\AdmissionKpiService;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AdmissionSupervisorUxGuidanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_head_command_center_explains_supervisor_control_cycle(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();

        $this->actingAs($head)
            ->get(route('admission.command-center.index'))
            ->assertOk()
            ->assertSee('Supervisor control cycle')
            ->assertSee('Clear immediate attention')
            ->assertSee('Assign or rebalance work')
            ->assertSee('Unblock documents/payments/offers')
            ->assertSee('Review forecast and automation')
            ->assertSee('Open the queue, assign an owner, close the blocker')
            ->assertSee('Monitor calling pressure')
            ->assertSee(route('admission.workbench'), false)
            ->assertSee(route('admission.manager-workspace.index'), false)
            ->assertSee(route('admission.attention.index'), false)
            ->assertSee(route('admission.attention.index', ['queue' => 'sla_breaches']), false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_manager_command_center_workload_drills_into_scoped_leads(): void
    {
        $manager = User::where('email', 'admission.manager@college.com')->firstOrFail();

        $this->actingAs($manager)
            ->get(route('admission.command-center.index'))
            ->assertOk()
            ->assertSee('Lead Workload')
            ->assertSee('Open scoped lead workload')
            ->assertSee(route('admission.leads.index'), false)
            ->assertDontSee('Open scoped applicant workload')
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_manager_workspace_explains_team_operating_loop(): void
    {
        $manager = User::where('email', 'admission.manager@college.com')->firstOrFail();

        $this->actingAs($manager)
            ->get(route('admission.manager-workspace.index'))
            ->assertOk()
            ->assertSee('Manager operating loop')
            ->assertSee('Check team KPI rollup')
            ->assertSee('Reassign stale/unassigned leads')
            ->assertSee('Audit reminders')
            ->assertSee('Close pending reviews or escalate')
            ->assertSee('Click counts to open matching records')
            ->assertSee('Assign owner or update next action')
            ->assertSee('Coach, audit, or escalate')
            ->assertSee(route('admission.manager-reviews.index'), false)
            ->assertSee(route('admission.reminders.index'), false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_manager_workspace_explains_empty_operational_queues(): void
    {
        $manager = User::where('email', 'admission.manager@college.com')->firstOrFail();

        $this->app->instance(AdmissionKpiService::class, new class extends AdmissionKpiService
        {
            public function __construct() {}

            public function rollupByUser(User $viewer, array $filters = []): Collection
            {
                return collect();
            }
        });

        Lead::query()->update([
            'assigned_to' => $manager->id,
            'last_activity_at' => now(),
        ]);
        AdmissionReminderSchedule::query()->delete();
        AdmissionManagerReview::query()->update(['status' => 'closed']);

        $this->actingAs($manager)
            ->get(route('admission.manager-workspace.index'))
            ->assertOk()
            ->assertSee('No team members in your current scope')
            ->assertSee('Assign admission members to this manager or adjust hierarchy scope')
            ->assertSee('No unassigned or stale leads')
            ->assertSee('Your visible lead queue is clear for unassigned ownership and stale activity')
            ->assertSee('No reminder activity in this scope')
            ->assertSee('Scheduled, sent, failed, and escalated reminders will appear here once created')
            ->assertSee('No pending manager reviews')
            ->assertSee('Coaching, audit, and escalation reviews are clear for your current scope')
            ->assertDontSee('N/A', false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_workbench_explains_operating_order_for_queue_triage(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();

        $this->actingAs($head)
            ->get(route('admission.workbench'))
            ->assertOk()
            ->assertSee('Workbench operating order')
            ->assertSee('Apply program/counsellor filters')
            ->assertSee('Clear overdue follow-ups/unassigned leads')
            ->assertSee('Verify documents/payments')
            ->assertSee('Move enrollment-ready applicants')
            ->assertSee('Each card opens the queue that caused the count')
            ->assertSee('Open matching lead workload')
            ->assertSee('Open breached SLA queue')
            ->assertSee('Open conversion reports')
            ->assertSee('Open follow-up reminders')
            ->assertSee('Open, assign, or set next action')
            ->assertSee('Verify blockers before enrolling')
            ->assertSee(route('admission.attention.index', ['queue' => 'unassigned_hot_leads']), false)
            ->assertSee(route('admission.attention.index', ['queue' => 'sla_breaches']), false)
            ->assertSee(route('admission.reports.index'), false)
            ->assertSee(route('admission.assignment-rules.index'), false)
            ->assertSee(route('admission.process-templates.index'), false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_workbench_empty_operational_panels_explain_next_source_workflow(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();

        $this->actingAs($head)
            ->get(route('admission.workbench', ['priority' => 'low', 'program_id' => 999999]))
            ->assertOk()
            ->assertSee('No leads match this workbench scope.')
            ->assertSee('Clear filters, review unassigned leads, or confirm whether new enquiries are entering through web forms, walk-ins, partner submissions, or imports.')
            ->assertSee('No enrollment-ready applicants in this scope.')
            ->assertSee('Applicants appear here only after selection, required documents, payment readiness, offer acceptance, and enrollment blockers are clear.')
            ->assertSee('No pending documents in this scope.')
            ->assertSee('Document blockers appear here after applicants upload files that need staff verification.')
            ->assertSee('No pending payments in this scope.')
            ->assertSee('Payment proof and gateway-review items appear here after applicants submit payable admission milestones.')
            ->assertSee('No assessment sessions scheduled today.')
            ->assertSee('No offer expiry risk in the next 3 days.')
            ->assertSee(route('admission.workbench'), false)
            ->assertSee(route('admission.leads.index', ['assigned' => 'unassigned']), false)
            ->assertSee(route('admission.documents.queue'), false)
            ->assertSee(route('admission.payments.queue'), false)
            ->assertSee(route('admission.assessment-control-room.index'), false)
            ->assertSee(route('admission.offer-rounds.index'), false)
            ->assertDontSee('No leads in scope.')
            ->assertDontSee('No pending documents.')
            ->assertDontSee('No pending payments.')
            ->assertDontSee('No sessions today.')
            ->assertDontSee('N/A', false)
            ->assertDontSee('â', false)
            ->assertDontSee('Ã', false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false);
    }

    public function test_attention_queue_honors_selected_queue_filter_and_explains_empty_state(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();

        $this->actingAs($head)
            ->get(route('admission.attention.index', [
                'queue' => 'unassigned_hot_leads',
                'priority' => 'low',
                'program_id' => 999999,
            ]))
            ->assertOk()
            ->assertSee('Unassigned Hot Leads')
            ->assertSee('Displayed total')
            ->assertSee('0 item(s)')
            ->assertSee('Active source filters')
            ->assertSee('Queue: Unassigned Hot Leads')
            ->assertSee('Priority: low')
            ->assertSee('Program Id: 999999')
            ->assertSee('No unassigned hot leads match this scope.')
            ->assertSee('Clear filters, open all visible leads, or review assignment rules to confirm new high-priority enquiries are being routed.')
            ->assertSee(route('admission.leads.index', ['assigned' => 'unassigned']), false)
            ->assertSee('/admission/attention?program_id=999999&amp;priority=low', false)
            ->assertDontSee('Pending Documents')
            ->assertDontSee('No items.')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }
}
