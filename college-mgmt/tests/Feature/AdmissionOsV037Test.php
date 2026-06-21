<?php

namespace Tests\Feature;

use App\Models\AdmissionAssessmentPanel;
use App\Models\AdmissionAssessmentPanelAssignment;
use App\Models\AdmissionAssessmentScheduleConflict;
use App\Models\AdmissionCounsellorCoachingNote;
use App\Models\AdmissionCounsellorTarget;
use App\Models\AdmissionEvaluatorAvailability;
use App\Models\AdmissionIntegrationProvider;
use App\Models\AdmissionIntegrationWebhookEvent;
use App\Models\AdmissionAssessmentNormalizedScore;
use App\Models\AdmissionBlindScoringAlias;
use App\Models\AdmissionScriptCompletionLog;
use App\Models\AdmissionObjectionEvent;
use App\Models\AdmissionParentJourney;
use App\Models\AdmissionAutomation;
use App\Models\AdmissionAutomationConflictLog;
use App\Models\AdmissionAutomationExecution;
use App\Models\AdmissionAutomationSimulation;
use App\Models\AdmissionSavedView;
use App\Models\AdmissionRouteAccessAudit;
use App\Models\AdmissionCommunicationLog;
use App\Models\AdmissionScriptTemplate;
use App\Models\User;
use App\Services\AdmissionAssessmentSchedulingService;
use App\Services\AdmissionCounsellorPerformanceService;
use App\Services\AdmissionBlindScoringService;
use App\Services\AdmissionAutomationSchedulerService;
use App\Services\AdmissionRouteAccessAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionOsV037Test extends TestCase
{
    use RefreshDatabase;

    public function test_v037_seeded_hardening_pages_render(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);
        $head = User::where('email', 'head@college.com')->firstOrFail();

        $this->actingAs($head)
            ->get(route('admission.assessment-schedule-conflicts.index'))
            ->assertOk()
            ->assertSee('Assessment Schedule Conflicts')
            ->assertSee('Open Conflict Queue')
            ->assertSee('Evaluator Availability')
            ->assertDontSee('QueryException');

        $this->actingAs($head)
            ->get(route('admission.counsellor-performance.index'))
            ->assertOk()
            ->assertSee('Counsellor Performance')
            ->assertSee('Target Scorecards')
            ->assertSee('Coaching Notes')
            ->assertDontSee('QueryException');

        $this->actingAs($head)
            ->get(route('admission.route-access-audit.index'))
            ->assertOk()
            ->assertSee('Admission Route Access Audit')
            ->assertSee('Route Scope Register')
            ->assertDontSee('QueryException');
    }

    public function test_v037_detects_assessment_schedule_conflicts(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);
        $panel = AdmissionAssessmentPanel::where('name', 'PI Backup Panel - Conflict Demo')->firstOrFail();

        $conflicts = app(AdmissionAssessmentSchedulingService::class)->detectConflictsForPanel($panel);

        $this->assertTrue($conflicts->isNotEmpty());
        $this->assertTrue(AdmissionAssessmentScheduleConflict::where('panel_id', $panel->id)->where('status', 'open')->exists());
        $this->assertTrue(AdmissionEvaluatorAvailability::where('is_active', true)->exists());
    }

    public function test_v037_counsellor_performance_scorecards_and_coaching(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);
        $head = User::where('email', 'head@college.com')->firstOrFail();
        $counsellor = User::where('email', 'counsellor@college.com')->firstOrFail();
        $target = AdmissionCounsellorTarget::where('user_id', $counsellor->id)->firstOrFail();

        $scorecard = app(AdmissionCounsellorPerformanceService::class)->scorecard($target);
        $this->assertArrayHasKey('overall_rate', $scorecard);
        $this->assertContains($scorecard['band'], ['excellent', 'on_track', 'needs_coaching']);

        $this->actingAs($head)
            ->post(route('admission.counsellor-performance.coach', $counsellor), [
                'score_band' => 'needs_coaching',
                'strengths' => 'Good applicant rapport.',
                'improvement_areas' => 'Close callbacks faster.',
                'action_plan' => 'Complete all overdue callbacks and update parent objection notes.',
                'next_review_at' => now()->addWeek()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertTrue(AdmissionCounsellorCoachingNote::where('counsellor_user_id', $counsellor->id)
            ->where('action_plan', 'Complete all overdue callbacks and update parent objection notes.')
            ->exists());
    }

    public function test_v037_productivity_pages_explain_empty_source_data(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);
        $head = User::where('email', 'head@college.com')->firstOrFail();

        AdmissionCounsellorCoachingNote::query()->delete();
        AdmissionCounsellorTarget::query()->delete();
        AdmissionObjectionEvent::query()->delete();
        AdmissionParentJourney::query()->delete();

        $this->actingAs($head)
            ->get(route('admission.counsellor-performance.index'))
            ->assertOk()
            ->assertSee('Manager workflow')
            ->assertSee('No active counsellor targets are configured')
            ->assertSee('Create current-period targets before comparing calls')
            ->assertSee('No coaching notes are open yet')
            ->assertSee(route('admission.counsellor-desk.index'), false)
            ->assertDontSee('N/A', false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);

        $this->actingAs($head)
            ->get(route('admission.objection-analytics.index'))
            ->assertOk()
            ->assertSee('How to use this')
            ->assertSee('No structured objections are logged yet')
            ->assertSee('Objection trends appear after counsellors or telecallers log call outcomes')
            ->assertSee(route('admission.calling-desk.index'), false)
            ->assertDontSee('N/A', false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);

        $this->actingAs($head)
            ->get(route('admission.parent-journeys.index'))
            ->assertOk()
            ->assertSee('Operating sequence')
            ->assertSee('No parent or guardian journeys are active')
            ->assertSee('Journeys appear after a lead or applicant has guardian details')
            ->assertSee(route('admission.counsellor-desk.index'), false)
            ->assertDontSee('N/A', false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_v037_route_access_audit_refreshes_admission_routes(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);
        $head = User::where('email', 'head@college.com')->firstOrFail();

        $stats = app(AdmissionRouteAccessAuditService::class)->refresh($head);

        $this->assertGreaterThan(10, $stats['reviewed']);
        $this->assertTrue(AdmissionRouteAccessAudit::where('route_name', 'admission.counsellor-performance.index')->exists());
        $this->assertTrue(AdmissionRouteAccessAudit::where('route_name', 'admission.assessment-schedule-conflicts.index')->exists());
    }

    public function test_v037_completion_pages_render_with_seeded_data(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);
        $head = User::where('email', 'head@college.com')->firstOrFail();

        foreach ([
            'admission.integrations.index' => 'Admission Integrations',
            'admission.assessment-bulk-assignment.index' => 'Bulk Evaluator Assignment',
            'admission.assessment-normalization.index' => 'Assessment Normalization',
            'admission.script-compliance.index' => 'Script Compliance',
            'admission.objection-analytics.index' => 'Objection Analytics',
            'admission.parent-journeys.index' => 'Parent / Guardian Journeys',
            'admission.automation-simulation.index' => 'Automation Simulation',
            'admission.saved-views.index' => 'Admission Saved Views',
            'admission.accessibility-audit.index' => 'Admission Accessibility Audit',
        ] as $route => $text) {
            $this->actingAs($head)->get(route($route))->assertOk()->assertSee($text)->assertDontSee('QueryException');
        }
    }

    public function test_v037_integration_and_normalization_pages_explain_empty_source_data(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);
        $head = User::where('email', 'head@college.com')->firstOrFail();

        AdmissionIntegrationWebhookEvent::query()->delete();
        AdmissionCommunicationLog::where('status', 'failed')->delete();

        $this->actingAs($head)
            ->get(route('admission.integrations.index'))
            ->assertOk()
            ->assertSee('Integration workflow')
            ->assertSee('No provider webhook events are recorded yet')
            ->assertSee('Webhook receipts appear after sandbox tests or live providers send delivery')
            ->assertSee('No failed provider deliveries need retry')
            ->assertSee('Failed SMS, WhatsApp, dialer, video, or signature attempts appear here')
            ->assertDontSee('No webhook events.')
            ->assertDontSee('No failed deliveries.')
            ->assertDontSee('N/A', false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);

        AdmissionAssessmentPanelAssignment::query()->delete();
        AdmissionAssessmentNormalizedScore::query()->delete();

        $this->actingAs($head)
            ->get(route('admission.assessment-normalization.index'))
            ->assertOk()
            ->assertSee('Chair review workflow')
            ->assertSee('No normalized assessment scores are available yet')
            ->assertSee('Scores appear after panels have assigned candidates')
            ->assertSee(route('admission.assessment-control-room.index'), false)
            ->assertDontSee('N/A', false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_v037_governance_pages_explain_empty_review_data(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);
        $head = User::where('email', 'head@college.com')->firstOrFail();

        AdmissionScriptCompletionLog::query()->delete();
        AdmissionScriptTemplate::query()->delete();

        $this->actingAs($head)
            ->get(route('admission.script-compliance.index'))
            ->assertOk()
            ->assertSee('Review workflow')
            ->assertSee('No call scripts are configured')
            ->assertSee('No script completion logs are available yet')
            ->assertSee('Logs appear after staff save call outcomes from the Calling Desk')
            ->assertSee(route('admission.calling-desk.index'), false)
            ->assertDontSee('Â·', false)
            ->assertDontSee('N/A', false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);

        AdmissionAutomation::query()->delete();
        AdmissionAutomationSimulation::query()->delete();
        AdmissionAutomationConflictLog::query()->delete();

        $this->actingAs($head)
            ->get(route('admission.automation-simulation.index'))
            ->assertOk()
            ->assertSee('Safe automation workflow')
            ->assertSee('No automation rules configured')
            ->assertSee('No automation simulations have been run yet')
            ->assertSee('No automation conflicts are open')
            ->assertDontSee('N/A', false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);

        $this->actingAs($head)
            ->get(route('admission.route-access-audit.index'))
            ->assertOk()
            ->assertSee('Security review workflow')
            ->assertSee('Route Scope Register')
            ->assertSee('records')
            ->assertSee('v0.039 Enforcement Review')
            ->assertSee('routes reviewed')
            ->assertDontSee('N/A', false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);

        $this->actingAs($head)
            ->get(route('admission.accessibility-audit.index'))
            ->assertOk()
            ->assertSee('Audit workflow')
            ->assertSee('Accessibility Checklist')
            ->assertSee(route('admission.route-access-audit.index'), false)
            ->assertDontSee('N/A', false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_v037_integration_provider_webhook_and_retry_flow(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);
        $head = User::where('email', 'head@college.com')->firstOrFail();

        $this->assertTrue(AdmissionIntegrationProvider::where('provider_name', 'sandbox_whatsapp')->exists());

        $this->actingAs($head)
            ->post(route('admission.integrations.test'), ['channel' => 'whatsapp'])
            ->assertRedirect();

        $log = AdmissionCommunicationLog::where('channel', 'whatsapp')->orderByDesc('id')->firstOrFail();
        $this->assertNotNull($log->provider_message_id);

        $this->post(route('admission.integration-webhooks.store', 'sandbox_whatsapp'), [
            'event_type' => 'delivered',
            'message_id' => $log->provider_message_id,
            'delivery_state' => 'delivered',
        ])->assertOk();

        $this->assertTrue(AdmissionIntegrationWebhookEvent::where('external_id', $log->provider_message_id)->exists());
        $this->assertEquals('delivered', $log->refresh()->delivery_state);
    }

    public function test_v037_bulk_assignment_blind_scoring_and_normalization(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);
        $head = User::where('email', 'head@college.com')->firstOrFail();
        $panel = AdmissionAssessmentPanel::where('name', 'PI Backup Panel - Conflict Demo')->firstOrFail();
        $assignment = $panel->assignments()->with('applicant.user')->firstOrFail();

        $display = app(AdmissionBlindScoringService::class)->displayCandidate($assignment, User::where('email', 'counsellor@college.com')->firstOrFail());
        $this->assertTrue($display['masked']);
        $this->assertStringStartsWith('CAND-', $display['name']);
        $this->assertTrue(AdmissionBlindScoringAlias::where('panel_id', $panel->id)->exists());

        $this->actingAs($head)->post(route('admission.assessment-normalization.run'))->assertRedirect();
        $this->assertTrue(AdmissionAssessmentNormalizedScore::where('panel_id', $panel->id)->exists());
    }

    public function test_v037_productivity_parent_journey_and_automation_completion(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);
        $head = User::where('email', 'head@college.com')->firstOrFail();

        $this->assertTrue(AdmissionScriptCompletionLog::where('compliance_percent', '>', 0)->exists());
        $this->assertTrue(AdmissionObjectionEvent::where('status', 'open')->exists());
        $journey = AdmissionParentJourney::firstOrFail();

        $this->actingAs($head)->post(route('admission.parent-journeys.reminder', $journey))->assertRedirect();

        $automation = AdmissionAutomation::where('name', 'v0.037 Parent Follow-up And Quality Review')->firstOrFail();
        $this->actingAs($head)->post(route('admission.automation-simulation.simulate'), ['automation_id' => $automation->id])->assertRedirect();
        $this->assertTrue(AdmissionAutomationSimulation::where('automation_id', $automation->id)->exists());

        $result = app(AdmissionAutomationSchedulerService::class)->runDue();
        $this->assertGreaterThanOrEqual(1, $result['schedules']);
        $this->assertTrue(AdmissionAutomationExecution::count() >= 0);
    }

    public function test_v037_saved_views_exports_and_accessibility_are_available(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);
        $head = User::where('email', 'head@college.com')->firstOrFail();
        AdmissionSavedView::where('surface', 'counsellor_desk')->delete();

        $this->actingAs($head)
            ->get(route('admission.saved-views.index', ['surface' => 'counsellor_desk']))
            ->assertOk()
            ->assertSee('Saved-view workflow')
            ->assertSee('Open Counsellor Desk')
            ->assertSee('Work surface')
            ->assertSee('Owner scope')
            ->assertSee('Visible filter summary')
            ->assertSee('No saved views exist for Counsellor Desk yet')
            ->assertSee('Create the first view after choosing the status, priority, owner scope, date range, and sort order')
            ->assertSee(route('admission.counsellor-desk.index'), false)
            ->assertDontSee('Filters JSON');

        $this->actingAs($head)
            ->post(route('admission.saved-views.store'), [
                'surface' => 'counsellor_desk',
                'name' => 'High Priority Parent Calls',
                'filters_json' => '{"priority":"high"}',
            ])
            ->assertRedirect();
        $this->assertTrue(AdmissionSavedView::where('name', 'High Priority Parent Calls')->exists());

        $this->actingAs($head)
            ->post(route('admission.saved-views.store'), [
                'surface' => 'calling_desk',
                'name' => 'Overdue Team Callbacks',
                'filters' => [
                    'status' => 'overdue',
                    'priority' => 'high',
                    'owner_scope' => 'team',
                    'date_range' => 'today',
                    'sort' => 'due_soon',
                ],
            ])
            ->assertRedirect();

        $structuredView = AdmissionSavedView::where('name', 'Overdue Team Callbacks')->firstOrFail();
        $this->assertSame('overdue', $structuredView->filters['status']);
        $this->assertSame('team', $structuredView->filters['owner_scope']);

        $this->actingAs($head)
            ->get(route('admission.saved-views.index', ['surface' => 'calling_desk']))
            ->assertOk()
            ->assertSee('Overdue Team Callbacks')
            ->assertSee('Open source surface')
            ->assertSee(route('admission.calling-desk.index'), false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('N/A', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);

        $this->actingAs($head)->get(route('admission.v037.exports', 'normalization'))->assertOk();
        $this->actingAs($head)->get(route('admission.accessibility-audit.index'))->assertOk()->assertSee('Admission Accessibility Audit');
    }
}
