<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AdmissionPartner;
use App\Models\AdmissionPayment;
use App\Models\ApplicantDocument;
use App\Models\Applicant;
use App\Models\Lead;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdmissionFrontendBetaReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_primary_admission_operating_surfaces_open_without_debug_traces(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();

        foreach ([
            'admission.dashboard',
            'admission.command-center.index',
            'admission.calling-desk.index',
            'admission.counsellor-desk.index',
            'admission.assessment-control-room.index',
            'admission.offer-rounds.index',
        ] as $routeName) {
            $response = $this->actingAs($head)->get(route($routeName));

            $response
                ->assertOk()
                ->assertDontSee('SERVICE ERROR')
                ->assertDontSee('Whoops')
                ->assertDontSee('Laravel')
                ->assertSee('<title', false);
        }
    }

    public function test_admission_daily_work_metrics_link_to_source_lists(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();

        $this->actingAs($head)
            ->get(route('admission.command-center.index'))
            ->assertOk()
            ->assertSee(route('admission.applicants.index'), false)
            ->assertSee(route('admission.attention.index'), false)
            ->assertSee(route('admission.calling-desk.index'), false)
            ->assertSee(route('admission.forecasting.index'), false);

        $this->actingAs($head)
            ->get(route('admission.counsellor-desk.index'))
            ->assertOk()
            ->assertSee(route('admission.calling-desk.index'), false)
            ->assertSee(route('admission.applicants.index', ['status' => 'under_review']), false)
            ->assertSee(route('admission.assessment-control-room.index'), false)
            ->assertSee(route('admission.reminders.index'), false);

        $this->actingAs($head)
            ->get(route('admission.calling-desk.index'))
            ->assertOk()
            ->assertSee(route('admission.counsellor-performance.index'), false)
            ->assertSee(route('admission.reminders.index', ['reason' => 'callback_retry']), false)
            ->assertSee(route('admission.parent-journeys.index'), false);
    }

    public function test_primary_admission_views_do_not_have_broken_action_markup(): void
    {
        foreach ([
            'admission/v003/command-center.blade.php',
            'admission/v0036/counsellor-desk.blade.php',
            'admission/v0036/assessment-control-room.blade.php',
            'admission/v0038/calling-desk.blade.php',
            'admission/v0038/offer-seat-control.blade.php',
        ] as $view) {
            $contents = file_get_contents(resource_path("views/{$view}"));

            $this->assertStringNotContainsString('href="#"', $contents, "{$view} contains a placeholder action link.");
            $this->assertStringNotContainsString("href='#'", $contents, "{$view} contains a placeholder action link.");
            $this->assertStringNotContainsString('Â', $contents, "{$view} contains mojibake output.");
            $this->assertStringNotContainsString('</form><form', $contents, "{$view} contains adjacent forms without stable layout markup.");
        }
    }

    public function test_admission_shared_shell_uses_manifest_grouped_sidebar_links(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();

        $response = $this->actingAs($head)->get(route('admission.dashboard'));

        $response->assertOk()
            ->assertSee('Command Center')
            ->assertSee('Calling Desk')
            ->assertSee('Document Queue')
            ->assertSee('Assessment Scheduling')
            ->assertSee('Merit List')
            ->assertSee('Offer Letters')
            ->assertSee('Seat Control')
            ->assertSee('All Leads')
            ->assertSee('Consent &amp; Safety', false)
            ->assertSee('Department Controls')
            ->assertSee('Department Hierarchy')
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Whoops', false);
    }

    public function test_admission_handoff_sidebar_link_matches_policy_visibility(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();
        $officer = User::where('email', 'officer@college.com')->firstOrFail();

        $this->actingAs($head)
            ->get(route('admission.dashboard'))
            ->assertOk()
            ->assertSee(route('admission.handoff.index'), false)
            ->assertSee('Handoff');

        $this->actingAs($officer)
            ->get(route('admission.dashboard'))
            ->assertOk()
            ->assertDontSee(route('admission.handoff.index'), false)
            ->assertDontSee('Handoff');

        $this->actingAs($officer)
            ->get(route('admission.handoff.index'))
            ->assertForbidden();
    }

    public function test_seeded_admission_partner_has_scoped_portal_and_can_submit_leads(): void
    {
        $partner = User::where('email', 'partner.citychannel@demo.edu')->firstOrFail();
        $admissionPartner = AdmissionPartner::where('contact_user_id', $partner->id)->firstOrFail();
        $programId = collect($admissionPartner->allowed_program_ids)->first();

        $this->actingAs($partner)
            ->get(route('admission.partner-portal.dashboard'))
            ->assertOk()
            ->assertSee('Admission Partner Dashboard')
            ->assertSee('City Admissions Channel')
            ->assertSee('Submit lead')
            ->assertDontSee('SERVICE ERROR')
            ->assertDontSee('Whoops');

        $this->actingAs($partner)
            ->get(route('admission.partner-portal.leads'))
            ->assertOk()
            ->assertSee('Filtered Source List')
            ->assertSee('Farah Khan');

        $this->actingAs($partner)
            ->post(route('admission.partner-portal.leads.store'), [
                'name' => 'Partner Portal Candidate',
                'phone' => '7777700001',
                'program_id' => $programId,
                'partner_reference' => 'CITY-PORTAL-001',
                'priority' => 'high',
            ])
            ->assertRedirect(route('admission.partner-portal.leads', ['q' => '7777700001']));

        $this->assertDatabaseHas('leads', [
            'name' => 'Partner Portal Candidate',
            'phone' => '7777700001',
            'partner_reference' => 'CITY-PORTAL-001',
            'source' => 'agent',
        ]);

        $this->actingAs(User::where('email', 'officer@college.com')->firstOrFail())
            ->get(route('admission.partner-portal.dashboard'))
            ->assertForbidden();
    }

    public function test_lower_role_calling_desk_post_actions_are_scoped_to_visible_records(): void
    {
        $telecaller = User::where('email', 'telecaller@college.com')->firstOrFail();
        $assignedLead = Lead::where('assigned_to', $telecaller->id)->firstOrFail();
        $outsideLead = Lead::where('assigned_to', '<>', $telecaller->id)
            ->whereNotNull('assigned_to')
            ->firstOrFail();

        $outsideAttemptCount = DB::table('admission_call_attempts')
            ->where('subject_type', Lead::class)
            ->where('subject_id', $outsideLead->id)
            ->count();
        $outsideSkipCount = DB::table('admission_call_queue_skips')
            ->where('subject_type', Lead::class)
            ->where('subject_id', $outsideLead->id)
            ->where('user_id', $telecaller->id)
            ->count();

        $this->actingAs($telecaller)
            ->post(route('admission.calling-desk.outcome'), [
                'subject_type' => 'lead',
                'subject_id' => $outsideLead->id,
                'disposition' => 'connected',
                'outcome' => 'interested',
                'duration_seconds' => 120,
                'next_action' => 'Unauthorized direct post should not persist',
                'notes' => 'Unauthorized direct post should not persist',
            ])
            ->assertForbidden();

        $this->actingAs($telecaller)
            ->post(route('admission.call-attempts.skip'), [
                'subject_type' => 'lead',
                'subject_id' => $outsideLead->id,
                'reason' => 'Unauthorized direct skip should not persist',
            ])
            ->assertForbidden();

        $this->assertSame($outsideAttemptCount, DB::table('admission_call_attempts')
            ->where('subject_type', Lead::class)
            ->where('subject_id', $outsideLead->id)
            ->count());
        $this->assertSame($outsideSkipCount, DB::table('admission_call_queue_skips')
            ->where('subject_type', Lead::class)
            ->where('subject_id', $outsideLead->id)
            ->where('user_id', $telecaller->id)
            ->count());

        $this->actingAs($telecaller)
            ->post(route('admission.calling-desk.outcome'), [
                'subject_type' => 'lead',
                'subject_id' => $assignedLead->id,
                'disposition' => 'connected',
                'outcome' => 'callback',
                'retry_due_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'duration_seconds' => 180,
                'next_action' => 'Scoped callback scheduled',
                'notes' => 'Scoped call outcome from feature test.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('admission_call_attempts', [
            'subject_type' => Lead::class,
            'subject_id' => $assignedLead->id,
            'caller_user_id' => $telecaller->id,
            'outcome' => 'callback',
        ]);
    }

    public function test_lower_role_reminder_actions_show_confirmation_before_status_or_communication_changes(): void
    {
        $counsellor = User::where('email', 'counsellor@college.com')->firstOrFail();

        $this->actingAs($counsellor)
            ->get(route('admission.reminders.index'))
            ->assertOk()
            ->assertSee('send, complete, and pause actions are audited')
            ->assertSee("confirm('Queue this reminder through the communication hub?')", false)
            ->assertSee("confirm('Mark this reminder as completed?')", false)
            ->assertSee("confirm('Pause this reminder cadence for this record?')", false);

        $this->actingAs($counsellor)
            ->get(route('admission.counsellor-desk.index'))
            ->assertOk()
            ->assertSee("confirm('Queue this reminder through the communication hub?')", false);
    }

    public function test_payment_verification_queue_exports_current_filtered_view(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();
        $payment = AdmissionPayment::with(['applicant.user', 'applicant.program'])
            ->where('status', 'pending')
            ->whereNotNull('payment_mode')
            ->firstOrFail();

        $query = ['payment_mode' => $payment->payment_mode];

        $this->actingAs($head)
            ->get(route('admission.payments.queue', $query))
            ->assertOk()
            ->assertSee('Export Current View')
            ->assertSee(route('admission.payments.queue.export', $query), false)
            ->assertSee('Pending Payments (')
            ->assertSee('Filter: Mode: ' . strtoupper($payment->payment_mode));

        $response = $this->actingAs($head)
            ->get(route('admission.payments.queue.export', $query));

        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('"Application Number",Applicant,Program,Installment', $csv);
        $this->assertStringContainsString($payment->applicant->application_number, $csv);
        $this->assertStringContainsString($payment->applicant->user->name, $csv);
        $this->assertStringContainsString($payment->payment_mode, $csv);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'admission_payment_queue_export',
            'model_type' => null,
            'model_id' => null,
        ]);
    }

    public function test_document_verification_queue_exports_current_filtered_view(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();
        $document = ApplicantDocument::with(['applicant.user', 'requiredDocument'])
            ->where('status', 'pending')
            ->firstOrFail();
        $documentName = $document->requiredDocument?->name ?? $document->original_name;
        $query = ['document_name' => $documentName];

        $this->actingAs($head)
            ->get(route('admission.documents.queue', $query))
            ->assertOk()
            ->assertSee('Export Current View')
            ->assertSee(route('admission.documents.queue.export', $query), false)
            ->assertSee('Pending Documents (')
            ->assertSee('Filter: Document: ' . $documentName);

        $response = $this->actingAs($head)
            ->get(route('admission.documents.queue.export', $query));

        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('"Application Number",Applicant,Program,Batch,Document', $csv);
        $this->assertStringContainsString($document->applicant->application_number, $csv);
        $this->assertStringContainsString($document->applicant->user->name, $csv);
        $this->assertStringContainsString($documentName, $csv);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'admission_document_queue_export',
            'model_type' => null,
            'model_id' => null,
        ]);
    }

    public function test_offer_seat_control_is_read_only_for_lower_roles_and_write_protected(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();
        $officer = User::where('email', 'officer@college.com')->firstOrFail();
        $round = DB::table('admission_offer_rounds')->firstOrFail();
        $hold = DB::table('admission_seat_holds')->where('status', 'held')->firstOrFail();
        $applicant = \App\Models\Applicant::whereIn('status', ['selected', 'shortlisted', 'submitted', 'under_review'])->firstOrFail();

        $this->actingAs($head)
            ->get(route('admission.offer-rounds.index'))
            ->assertOk()
            ->assertDontSee('Read-only view for your Admission scope')
            ->assertSee("confirm('Publish this offer round and create seat holds for eligible selected applicants?')", false);

        $this->actingAs($officer)
            ->get(route('admission.offer-rounds.index'))
            ->assertOk()
            ->assertSee('Read-only view for your Admission scope')
            ->assertSee('disabled', false);

        $this->actingAs($officer)
            ->post(route('admission.offer-rounds.store'), [
                'program_id' => $round->program_id,
                'batch_id' => $round->batch_id,
                'round_number' => 99,
                'name' => 'Unauthorized Officer Round',
                'offer_valid_until' => now()->addDays(3)->format('Y-m-d H:i:s'),
            ])
            ->assertForbidden();

        $this->actingAs($officer)
            ->post(route('admission.offer-rounds.publish', $round->id))
            ->assertForbidden();

        $this->actingAs($officer)
            ->post(route('admission.waitlist.store'), [
                'applicant_id' => $applicant->id,
                'rank' => 99,
            ])
            ->assertForbidden();

        $this->actingAs($officer)
            ->post(route('admission.seat-control.release', $hold->id), [
                'reason' => 'Unauthorized officer release attempt',
            ])
            ->assertForbidden();

        $this->actingAs($officer)
            ->post(route('admission.deferrals.store'), [
                'applicant_id' => $applicant->id,
                'to_batch_id' => $round->batch_id,
                'reason' => 'Unauthorized officer deferral attempt',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('admission_offer_rounds', [
            'name' => 'Unauthorized Officer Round',
        ]);
        $this->assertSame('held', DB::table('admission_seat_holds')->where('id', $hold->id)->value('status'));
    }

    public function test_assessment_scheduling_is_read_only_for_lower_roles_and_write_protected(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();
        $officer = User::where('email', 'officer@college.com')->firstOrFail();
        $panel = DB::table('admission_assessment_panels')->firstOrFail();
        $slot = DB::table('admission_assessment_slots')->firstOrFail();
        $assignment = DB::table('admission_assessment_slot_assignments')->firstOrFail();
        $applicant = Applicant::findOrFail($assignment->applicant_id);

        $this->actingAs($head)
            ->get(route('admission.assessment-slots.index'))
            ->assertOk()
            ->assertDontSee('Read-only view for your Admission scope')
            ->assertSee("confirm('Create this assessment slot?')", false);

        $this->actingAs($officer)
            ->get(route('admission.assessment-slots.index'))
            ->assertOk()
            ->assertSee('Read-only view for your Admission scope')
            ->assertSee('disabled', false);

        $this->actingAs($officer)
            ->post(route('admission.assessment-slots.store'), [
                'panel_id' => $panel->id,
                'slot_code' => 'UNAUTHORIZED-OFFICER-SLOT',
                'starts_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
                'ends_at' => now()->addDays(2)->addHour()->format('Y-m-d H:i:s'),
                'capacity' => 4,
            ])
            ->assertForbidden();

        $this->actingAs($officer)
            ->post(route('admission.assessment-slots.assign'), [
                'slot_id' => $slot->id,
                'applicant_id' => $applicant->id,
            ])
            ->assertForbidden();

        $this->actingAs($officer)
            ->post(route('admission.assessment-slots.check-in'), [
                'assignment_id' => $assignment->id,
                'status' => 'checked_in',
            ])
            ->assertForbidden();

        $this->actingAs($officer)
            ->post(route('admission.gd-groups.build'), [
                'panel_id' => $panel->id,
                'capacity' => 6,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('admission_assessment_slots', [
            'slot_code' => 'UNAUTHORIZED-OFFICER-SLOT',
        ]);
    }

    public function test_communication_and_automation_controls_are_read_only_for_lower_roles(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();
        $telecaller = User::where('email', 'telecaller@college.com')->firstOrFail();
        $template = \App\Models\AdmissionCommunicationTemplate::firstOrFail();
        $outsideLead = Lead::where('assigned_to', '<>', $telecaller->id)
            ->whereNotNull('assigned_to')
            ->firstOrFail();

        $this->actingAs($head)
            ->get(route('admission.communication.index'))
            ->assertOk()
            ->assertDontSee('Read-only view for your Admission scope')
            ->assertSee("confirm('Save this communication template for Admission use?')", false);

        $this->actingAs($telecaller)
            ->get(route('admission.communication.index'))
            ->assertOk()
            ->assertSee('Template management and queued-message dispatch require Admission leadership approval')
            ->assertSee('disabled', false);

        $this->actingAs($telecaller)
            ->post(route('admission.communication.templates.store'), [
                'name' => 'Unauthorized Telecaller Template',
                'channel' => 'sms',
                'purpose' => 'unauthorized',
                'body' => 'This should not be saved.',
            ])
            ->assertForbidden();

        $this->actingAs($telecaller)
            ->post(route('admission.communication.dispatch'))
            ->assertForbidden();

        $this->actingAs($telecaller)
            ->post(route('admission.communication.send'), [
                'template_id' => $template->id,
                'subject_type' => 'lead',
                'subject_id' => $outsideLead->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('admission_communication_templates', [
            'name' => 'Unauthorized Telecaller Template',
        ]);

        $this->actingAs($head)
            ->get(route('admission.automations.index'))
            ->assertOk()
            ->assertDontSee('Read-only view for your Admission scope')
            ->assertSee("confirm('Save this Admission automation rule?')", false);

        $this->actingAs($telecaller)
            ->get(route('admission.automations.index'))
            ->assertOk()
            ->assertSee('Automation rule changes and manual runs require Admission leadership approval')
            ->assertSee('disabled', false);

        $this->actingAs($telecaller)
            ->post(route('admission.automations.store'), [
                'name' => 'Unauthorized Telecaller Automation',
                'trigger' => 'lead_created',
                'priority' => 1,
                'conditions_json' => '{}',
                'actions_json' => '[{"type":"next_action","value":"Unauthorized"}]',
            ])
            ->assertForbidden();

        $this->actingAs($telecaller)
            ->post(route('admission.automations.run'), [
                'trigger' => 'lead_created',
                'subject_type' => 'lead',
                'subject_id' => $outsideLead->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('admission_automations', [
            'name' => 'Unauthorized Telecaller Automation',
        ]);
    }
}
