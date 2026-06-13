<?php

namespace Tests\Feature;

use App\Models\AdmissionCommunicationTemplate;
use App\Models\Applicant;
use App\Models\Lead;
use App\Models\User;
use App\Services\AdmissionAssessmentResourceService;
use App\Services\AdmissionAssessmentSlotService;
use App\Services\AdmissionCallAttemptService;
use App\Services\AdmissionCommunicationSafetyService;
use App\Services\AdmissionConsentService;
use App\Services\AdmissionIntegrationHealthService;
use App\Services\AdmissionQuickSearchService;
use App\Services\AdmissionSeatControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdmissionOsV038Test extends TestCase
{
    use RefreshDatabase;

    public function test_v038_seeded_real_team_pages_render(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);
        $head = User::where('email', 'head@college.com')->firstOrFail();

        foreach ([
            'admission.calling-desk.index' => 'Admission Calling Desk',
            'admission.assessment-slots.index' => 'Assessment Scheduling',
            'admission.selection-committee.index' => 'Final Selection Committee',
            'admission.offer-rounds.index' => 'Offer And Seat Control',
            'admission.communication-safety.index' => 'Communication Safety',
            'admission.integration-health.index' => 'Integration Health',
            'admission.quick-search.index' => 'Admission Quick Search',
        ] as $route => $text) {
            $this->actingAs($head)->get(route($route))->assertOk()->assertSee($text)->assertDontSee('QueryException');
        }
    }

    public function test_calling_desk_outcome_creates_attempt_log_script_and_reminder(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);
        $telecaller = User::where('email', 'telecaller@college.com')->firstOrFail();
        $lead = Lead::where('assigned_to', $telecaller->id)->firstOrFail();
        $script = DB::table('admission_script_templates')->first();

        app(AdmissionCallAttemptService::class)->record($lead, $telecaller, [
            'disposition' => 'no_answer',
            'outcome' => 'callback',
            'retry_due_at' => now()->addHours(3),
            'script_template_id' => $script->id,
            'script_results' => ['covered', 'missed', 'covered'],
            'notes' => 'Feature test callback.',
        ]);

        $this->assertDatabaseHas('admission_call_attempts', ['subject_type' => Lead::class, 'subject_id' => $lead->id, 'disposition' => 'no_answer']);
        $this->assertDatabaseHas('admission_call_logs', ['subject_type' => Lead::class, 'subject_id' => $lead->id, 'outcome_reason' => 'callback']);
        $this->assertDatabaseHas('admission_script_completion_logs', ['subject_type' => Lead::class, 'subject_id' => $lead->id]);
        $this->assertDatabaseHas('admission_reminder_schedules', ['subject_type' => Lead::class, 'subject_id' => $lead->id, 'reason' => 'callback_retry']);
    }

    public function test_assessment_slot_capacity_conflict_gd_and_committee_decision(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);
        $head = User::where('email', 'head@college.com')->firstOrFail();
        $slot = DB::table('admission_assessment_slots')->first();
        $applicant = Applicant::whereNotIn('id', DB::table('admission_assessment_slot_assignments')->where('slot_id', $slot->id)->pluck('applicant_id'))->firstOrFail();

        app(AdmissionAssessmentSlotService::class)->assignApplicant($slot->id, $applicant, $head);
        $this->assertDatabaseHas('admission_assessment_slot_assignments', ['slot_id' => $slot->id, 'applicant_id' => $applicant->id]);

        $conflicts = app(AdmissionAssessmentResourceService::class)->conflicts();
        $this->assertTrue($conflicts->isNotEmpty());
        $this->assertTrue(DB::table('admission_gd_groups')->exists());

        $this->actingAs($head)->post(route('admission.selection-committee.decide'), [
            'applicant_id' => $applicant->id,
            'decision' => 'waitlist',
            'reason' => 'Good assessment result but seat decision pending.',
        ])->assertRedirect();

        $this->assertDatabaseHas('admission_selection_committee_decisions', ['applicant_id' => $applicant->id, 'decision' => 'waitlist']);
    }

    public function test_offer_waitlist_seat_release_deferral_and_joining_kit(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);
        $head = User::where('email', 'head@college.com')->firstOrFail();

        $this->assertTrue(DB::table('admission_offer_rounds')->exists());
        $this->assertTrue(DB::table('admission_waitlist_entries')->where('status', 'waiting')->exists());

        $hold = DB::table('admission_seat_holds')->where('status', 'held')->first();
        app(AdmissionSeatControlService::class)->release($hold->id, 'Feature test release', $head);

        $this->assertDatabaseHas('admission_seat_holds', ['id' => $hold->id, 'status' => 'released']);
        $this->assertTrue(DB::table('admission_seat_movements')->where('movement_type', 'release')->exists());
        $this->assertTrue(DB::table('admission_deferrals')->where('status', 'approved')->exists());
        $this->assertTrue(DB::table('admission_joining_kit_tasks')->exists());
    }

    public function test_consent_template_approval_and_bulk_preview_block_unsafe_recipients(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);
        $head = User::where('email', 'head@college.com')->firstOrFail();
        $lead = Lead::firstOrFail();
        $template = AdmissionCommunicationTemplate::where('channel', 'whatsapp')->firstOrFail();

        app(AdmissionConsentService::class)->set($lead, 'whatsapp', 'opt_out', $head, 'Feature test opt-out');
        $preview = app(AdmissionCommunicationSafetyService::class)->preview($template, collect([$lead]), $head, ['feature' => 'test']);

        $this->assertFalse(app(AdmissionCommunicationSafetyService::class)->canSend($lead, $template));
        $this->assertGreaterThanOrEqual(1, $preview->blocked_count);
        $this->assertDatabaseHas('admission_consent_records', ['subject_type' => Lead::class, 'subject_id' => $lead->id, 'channel' => 'whatsapp', 'status' => 'opt_out']);
    }

    public function test_vendor_health_retry_queue_and_quick_search(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);
        $head = User::where('email', 'head@college.com')->firstOrFail();

        $health = app(AdmissionIntegrationHealthService::class)->checkAll();
        $this->assertGreaterThanOrEqual(5, $health->count());
        $this->assertTrue(DB::table('admission_integration_retry_queue')->exists());

        $results = app(AdmissionQuickSearchService::class)->search('PGDM', $head);
        $this->assertTrue($results->isNotEmpty());
        $this->assertDatabaseHas('admission_quick_search_logs', ['query' => 'PGDM']);
    }
}
