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
            ->assertSee('Open, assign, or set next action')
            ->assertSee('Verify blockers before enrolling')
            ->assertSee(route('admission.assignment-rules.index'), false)
            ->assertSee(route('admission.process-templates.index'), false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }
}
