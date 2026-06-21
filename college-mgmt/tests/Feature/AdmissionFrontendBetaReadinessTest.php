<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AdmissionPartner;
use App\Models\AdmissionPayment;
use App\Models\AdmissionReminderSchedule;
use App\Models\ApplicantDocument;
use App\Models\Applicant;
use App\Models\CounsellingLog;
use App\Models\Lead;
use App\Services\AdmissionKpiDrilldownService;
use App\Support\FrontendNavigation;
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
            ->assertSee('Daily Work')
            ->assertSee('Communication')
            ->assertSee('Finance')
            ->assertSee('Reports')
            ->assertSee('Governance')
            ->assertSee('Command Center')
            ->assertSee('Calling Desk')
            ->assertSee('Document Queue')
            ->assertSee('Assessment Scheduling')
            ->assertSee('Merit List')
            ->assertSee('Offer Letters')
            ->assertSee('Seat Control')
            ->assertSee('All Leads')
            ->assertSee('Bulk Communication')
            ->assertSee('Consent &amp; Safety', false)
            ->assertSee('Refunds')
            ->assertSee('Admission Reports')
            ->assertSee('Lead Analytics')
            ->assertSee('Integration Health')
            ->assertSee('Department Controls')
            ->assertSee('Department Hierarchy')
            ->assertSeeInOrder(['Communication', 'Bulk Communication', 'Consent &amp; Safety'], false)
            ->assertSeeInOrder(['Finance', 'Refunds'], false)
            ->assertSeeInOrder(['Reports', 'Admission Reports', 'Lead Analytics'], false)
            ->assertSeeInOrder(['Governance', 'Integration Health'], false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Whoops', false);

        $admissionGroups = FrontendNavigation::manifest()['admission']['groups'];

        $this->assertNotContains('Lead Analytics', collect($admissionGroups['Leads'])->pluck('label')->all());
        $this->assertContains('Lead Analytics', collect($admissionGroups['Reports'])->pluck('label')->all());
    }

    public function test_admission_dashboard_recent_interactions_empty_state_guides_next_staff_action(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();
        CounsellingLog::query()->delete();

        $this->actingAs($head)
            ->get(route('admission.dashboard'))
            ->assertOk()
            ->assertSeeText('No follow-ups are due in the next 7 days')
            ->assertSeeText('Follow-ups appear here after staff log a counselling interaction')
            ->assertSeeText('No counselling interactions are logged yet')
            ->assertSeeText('log the first call, email, WhatsApp, or walk-in outcome')
            ->assertSeeText('Open applicants')
            ->assertSee(route('admission.applicants.index'), false)
            ->assertDontSeeText('No interactions logged yet.')
            ->assertDontSeeText('No follow-ups due')
            ->assertDontSee('N/A', false);
    }

    public function test_admission_dashboard_activity_panels_use_readable_fallbacks(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();

        $this->actingAs($head)
            ->get(route('admission.dashboard'))
            ->assertOk()
            ->assertSee('Admission CRM Dashboard')
            ->assertSeeText('Daily operating order')
            ->assertSeeText('1. Call next')
            ->assertSeeText('2. Clear documents')
            ->assertSeeText('3. Clear payments')
            ->assertSeeText('4. Run assessments')
            ->assertSeeText('5. Move seats')
            ->assertSee(route('admission.calling-desk.index'), false)
            ->assertSee(route('admission.offer-rounds.index'), false)
            ->assertSee('Follow-ups Due (Next 7 Days)')
            ->assertSee('Recent Interactions')
            ->assertDontSee('N/A', false)
            ->assertDontSee('â', false)
            ->assertDontSee('₹', false);
    }

    public function test_admission_selection_session_detail_uses_readable_assessment_workflow_guidance(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();
        $seededSession = \App\Models\SelectionSession::where('session_name', 'PGDM Case Analysis Panel A')->firstOrFail();

        $this->actingAs($head)
            ->get(route('admission.sessions.show', $seededSession))
            ->assertOk()
            ->assertSeeText('Assessment Panels')
            ->assertSeeText('Assigned Candidates')
            ->assertSeeText('10:00 - 12:00')
            ->assertSeeText('Open Assessment Operations')
            ->assertDontSee('N/A', false)
            ->assertDontSee('Ã¢', false)
            ->assertDontSee('â', false)
            ->assertDontSee('—', false)
            ->assertDontSeeText('No candidates assigned yet.');

        $program = \App\Models\Program::firstOrFail();
        $nextStepOrder = ((int) \App\Models\SelectionProcessStep::where('program_id', $program->id)->max('step_order')) + 1;
        $step = \App\Models\SelectionProcessStep::create([
            'program_id' => $program->id,
            'name' => 'UX Empty Session Check',
            'type' => 'pi',
            'step_order' => $nextStepOrder,
            'max_score' => 100,
            'weightage' => 10,
            'instructions' => 'Use this test-only step to verify empty session guidance.',
            'is_active' => true,
        ]);
        $emptySession = \App\Models\SelectionSession::create([
            'selection_process_step_id' => $step->id,
            'program_id' => $program->id,
            'session_name' => 'UX Empty Candidate Session',
            'scheduled_date' => now()->addDays(3)->toDateString(),
            'start_time' => '14:00',
            'end_time' => '15:00',
            'status' => 'scheduled',
            'created_by' => $head->id,
        ]);

        $this->actingAs($head)
            ->get(route('admission.sessions.show', $emptySession))
            ->assertOk()
            ->assertSeeText('No candidates are assigned to this session yet')
            ->assertSeeText('Assign shortlisted applicants before call letters, attendance, panel scoring, and assessment-day check-in can be used.')
            ->assertSeeText('Coordinator not assigned')
            ->assertSeeText('Unlimited')
            ->assertSee(route('admission.applicants.index', ['status' => 'shortlisted']), false)
            ->assertSee(route('admission.assessment-operations.index'), false)
            ->assertDontSee('N/A', false)
            ->assertDontSee('Ã¢', false)
            ->assertDontSee('â', false)
            ->assertDontSee('—', false)
            ->assertDontSeeText('No candidates assigned yet.');
    }

    public function test_admission_scoring_pages_explain_attendance_and_scorecard_prerequisites(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();

        $program = \App\Models\Program::firstOrFail();
        $nextStepOrder = ((int) \App\Models\SelectionProcessStep::where('program_id', $program->id)->max('step_order')) + 1;
        $step = \App\Models\SelectionProcessStep::create([
            'program_id' => $program->id,
            'name' => 'UX Score Sheet Empty Check',
            'type' => 'pi',
            'step_order' => $nextStepOrder,
            'max_score' => 100,
            'weightage' => 10,
            'instructions' => 'Use this test-only step to verify score sheet guidance.',
            'is_active' => true,
        ]);
        $session = \App\Models\SelectionSession::create([
            'selection_process_step_id' => $step->id,
            'program_id' => $program->id,
            'session_name' => 'UX Empty Score Sheet Session',
            'scheduled_date' => now()->addDays(3)->toDateString(),
            'start_time' => '14:00',
            'end_time' => '15:00',
            'status' => 'scheduled',
            'created_by' => $head->id,
        ]);

        $this->actingAs($head)
            ->get(route('admission.sessions.scores', $session))
            ->assertOk()
            ->assertSeeText('No present applicants are ready for scoring')
            ->assertSeeText('Score entry opens after candidates are assigned to this session and marked Present')
            ->assertSeeText('Venue not assigned')
            ->assertSee(route('admission.sessions.show', $session), false)
            ->assertDontSee('N/A', false)
            ->assertDontSee('Ã¢', false)
            ->assertDontSee('â', false)
            ->assertDontSee('—', false)
            ->assertDontSeeText('No present applicants to score. Mark attendance first.');

        $applicantUser = User::create([
            'name' => 'UX Scorecard Candidate',
            'email' => 'ux.scorecard.candidate@example.test',
            'password' => bcrypt('password'),
            'role' => 'applicant',
        ]);
        $applicant = \App\Models\Applicant::create([
            'user_id' => $applicantUser->id,
            'program_id' => $program->id,
            'application_number' => 'UX-SCORECARD-EMPTY',
            'status' => 'shortlisted',
            'assigned_to' => $head->id,
        ]);

        $this->actingAs($head)
            ->get(route('admission.applicants.scorecard', $applicant))
            ->assertOk()
            ->assertSeeText('No assessment scores are recorded for this applicant yet')
            ->assertSeeText('Scores appear here after the applicant is assigned to a selection session, marked Present, and scored from the session score sheet.')
            ->assertSee(route('admission.sessions.index'), false)
            ->assertSee(route('admission.assessment-operations.index'), false)
            ->assertDontSee('N/A', false)
            ->assertDontSee('Ã¢', false)
            ->assertDontSee('â', false)
            ->assertDontSee('—', false)
            ->assertDontSeeText('No scores recorded for this applicant yet.');
    }

    public function test_admission_pipeline_empty_stage_guides_staff_and_blocks_invalid_board_type(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();

        $this->actingAs($head)
            ->get(route('admission.pipeline.index', ['object_type' => 'lead', 'program_id' => 999999]))
            ->assertOk()
            ->assertSeeText('Admission Pipeline')
            ->assertSeeText('Scope: Leads visible to your Admission role and hierarchy.')
            ->assertSeeText('No leads in this stage')
            ->assertSeeText('Check the other stages or open the lead list for the full source view')
            ->assertDontSeeText('No records.');

        $this->actingAs($head)
            ->get(route('admission.pipeline.index', ['object_type' => 'invalid']))
            ->assertNotFound();

        $this->assertDatabaseMissing('admission_pipeline_boards', [
            'object_type' => 'invalid',
        ]);
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

    public function test_reminder_queue_explains_workflow_empty_filters_and_target_context(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();
        $lead = Lead::whereNotNull('name')->firstOrFail();

        AdmissionReminderSchedule::create([
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
            'assigned_to' => $head->id,
            'owner_user_id' => $head->id,
            'target' => 'lead',
            'reason' => 'callback_retry',
            'channel' => 'sms',
            'status' => 'scheduled',
            'priority' => 'high',
            'due_at' => now()->addDay(),
            'notes' => null,
        ]);

        $this->actingAs($head)
            ->get(route('admission.reminders.index', ['reason' => 'callback_retry']))
            ->assertOk()
            ->assertSee('Reminder operating workflow')
            ->assertSee('Filter due reminders')
            ->assertSee('Open the lead or applicant context')
            ->assertSee('Queue approved communication')
            ->assertSee('Complete only after action is recorded')
            ->assertSee('Pause cadences with a reason')
            ->assertSee('This queue is scoped to your Admission hierarchy.')
            ->assertSee('Active filters:')
            ->assertSee('Reason: callback retry')
            ->assertSee($lead->name)
            ->assertSee(route('admission.leads.show', $lead), false)
            ->assertSee('No reminder notes recorded')
            ->assertDontSee('N/A', false)
            ->assertDontSee('â', false)
            ->assertDontSee('Ã', false)
            ->assertDontSee('href="#"', false);

        $this->actingAs($head)
            ->get(route('admission.reminders.index', ['reason' => 'no_matching_reason_for_ux']))
            ->assertOk()
            ->assertSee('No reminders match this scoped queue.')
            ->assertSee('Clear filters, review the follow-up calendar, or create a reminder from a lead, applicant, Calling Desk, or document/payment blocker workflow.')
            ->assertSee('Clear Filters')
            ->assertSee('Follow-up Calendar')
            ->assertSee('Calling Desk')
            ->assertSee(route('admission.leads.follow-ups.calendar'), false)
            ->assertSee(route('admission.calling-desk.index'), false)
            ->assertDontSee('N/A', false)
            ->assertDontSee('â', false)
            ->assertDontSee('Ã', false)
            ->assertDontSee('href="#"', false);
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
            ->assertSee('Filter: Document: ' . $documentName)
            ->assertDontSee('&bull;', false);

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

    public function test_admission_manager_counsellor_and_telecaller_visible_navigation_links_are_reachable(): void
    {
        foreach ([
            ['admission.manager@college.com', 'admission.manager-workspace.index'],
            ['counsellor@college.com', 'admission.counsellor-desk.index'],
            ['telecaller@college.com', 'admission.calling-desk.index'],
        ] as [$email, $routeName]) {
            $user = User::where('email', $email)->firstOrFail();
            $response = $this->actingAs($user)->get(route($routeName));

            $response->assertOk()
                ->assertDontSee('SERVICE ERROR', false)
                ->assertDontSee('Whoops', false)
                ->assertDontSee('Laravel', false);

            foreach ($this->internalGetLinks($response->getContent()) as $path) {
                $linkResponse = $this->actingAs($user)->get($path);

                $this->assertNotContains($linkResponse->getStatusCode(), [403, 404, 500], "{$email} visible link failed: {$path}");
            }
        }
    }

    public function test_lower_role_admission_dashboard_kpis_match_scoped_drilldown_lists(): void
    {
        $service = app(AdmissionKpiDrilldownService::class);

        foreach ([
            'admission.manager@college.com',
            'counsellor@college.com',
            'telecaller@college.com',
        ] as $email) {
            $user = User::where('email', $email)->firstOrFail();
            $dashboard = $service->dashboard($user);

            $this->actingAs($user)->get(route('admission.leads.index'))
                ->assertOk()
                ->assertSee($dashboard['funnelData']['leads'] . ' records after filters')
                ->assertSee('Filter: All visible leads');

            $this->actingAs($user)->get(route('admission.applicants.index'))
                ->assertOk()
                ->assertSee($dashboard['funnelData']['applied'] . ' records after filters')
                ->assertSee('Filter: All visible applicants');

            $this->actingAs($user)->get(route('admission.documents.queue'))
                ->assertOk()
                ->assertSee('Pending Documents (' . $dashboard['kpis']['docs_pending'] . ')')
                ->assertSee('Filter: All visible pending documents');

            $this->actingAs($user)->get(route('admission.payments.queue'))
                ->assertOk()
                ->assertSee('Pending Payments (' . $dashboard['kpis']['payments_pending'] . ')')
                ->assertSee('Filter: All visible pending payments');
        }
    }

    public function test_lower_role_reminder_scheduling_and_document_actions_are_scoped(): void
    {
        $telecaller = User::where('email', 'telecaller@college.com')->firstOrFail();
        $counsellor = User::where('email', 'counsellor@college.com')->firstOrFail();
        $ownLead = Lead::where('assigned_to', $telecaller->id)->firstOrFail();
        $outsideLead = Lead::where('assigned_to', '<>', $telecaller->id)
            ->whereNotNull('assigned_to')
            ->firstOrFail();

        $this->actingAs($telecaller)
            ->post(route('admission.reminders.store'), [
                'subject_type' => 'lead',
                'subject_id' => $outsideLead->id,
                'reason' => 'callback_retry',
                'channel' => 'sms',
                'priority' => 'high',
                'due_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'notes' => 'Unauthorized reminder should not persist.',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('admission_reminder_schedules', [
            'subject_type' => Lead::class,
            'subject_id' => $outsideLead->id,
            'notes' => 'Unauthorized reminder should not persist.',
        ]);

        $this->actingAs($telecaller)
            ->post(route('admission.reminders.store'), [
                'subject_type' => 'lead',
                'subject_id' => $ownLead->id,
                'reason' => 'callback_retry',
                'channel' => 'sms',
                'priority' => 'high',
                'due_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'notes' => 'Scoped reminder from Batch 3 UX test.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('admission_reminder_schedules', [
            'subject_type' => Lead::class,
            'subject_id' => $ownLead->id,
            'assigned_to' => $telecaller->id,
            'notes' => 'Scoped reminder from Batch 3 UX test.',
        ]);

        $assignedDocument = ApplicantDocument::where('status', 'pending')
            ->whereHas('applicant', fn ($query) => $query->where('assigned_to', $counsellor->id))
            ->firstOrFail();
        $outsideDocument = ApplicantDocument::where('status', 'pending')
            ->whereHas('applicant', fn ($query) => $query->where('assigned_to', '<>', $counsellor->id)->whereNotNull('assigned_to'))
            ->firstOrFail();

        $this->actingAs($counsellor)
            ->post(route('admission.documents.verify', $outsideDocument))
            ->assertForbidden();

        $this->actingAs($counsellor)
            ->post(route('admission.documents.verify', $assignedDocument))
            ->assertRedirect();

        $this->assertSame('verified', $assignedDocument->fresh()->status);
        $this->assertSame('pending', $outsideDocument->fresh()->status);
    }

    public function test_lower_role_offer_seat_and_handoff_lists_show_filters_without_forbidden_actions(): void
    {
        $manager = User::where('email', 'admission.manager@college.com')->firstOrFail();
        $counsellor = User::where('email', 'counsellor@college.com')->firstOrFail();

        $this->actingAs($manager)
            ->get(route('admission.offer-rounds.index'))
            ->assertOk()
            ->assertSee('Read-only view for your Admission scope')
            ->assertSee('Offer Rounds')
            ->assertSee('Waitlist')
            ->assertDontSee("confirm('Publish this offer round and create seat holds for eligible selected applicants?')", false);

        $this->actingAs($manager)
            ->get(route('admission.handoff.index', ['status' => 'blocked']))
            ->assertOk()
            ->assertSee('Admission To Academics / PMC Handoff')
            ->assertSee('Filters: status=blocked');

        $this->actingAs($counsellor)
            ->get(route('admission.handoff.index', ['status' => 'blocked']))
            ->assertForbidden();
    }

    private function internalGetLinks(string $html): array
    {
        preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $matches);

        return collect($matches[1] ?? [])
            ->filter(fn ($href) => ! str_starts_with($href, '#'))
            ->filter(fn ($href) => ! str_starts_with($href, 'javascript:'))
            ->filter(fn ($href) => ! str_starts_with($href, 'mailto:'))
            ->filter(fn ($href) => ! preg_match('/\.(css|js|png|jpg|jpeg|svg|ico|json|webmanifest)(\?|$)/i', $href))
            ->map(function ($href) {
                if (str_starts_with($href, url('/'))) {
                    return parse_url($href, PHP_URL_PATH) . (parse_url($href, PHP_URL_QUERY) ? '?' . parse_url($href, PHP_URL_QUERY) : '');
                }

                return $href;
            })
            ->filter(fn ($href) => str_starts_with($href, '/'))
            ->unique()
            ->values()
            ->all();
    }
}
