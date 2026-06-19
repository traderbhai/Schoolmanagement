<?php

namespace Tests\Feature;

use App\Models\AdmissionAssessmentPanel;
use App\Models\AdmissionCommunicationLog;
use App\Models\AdmissionCommunicationTemplate;
use App\Models\AdmissionManagerReview;
use App\Models\AdmissionReminderSchedule;
use App\Models\AdmissionWalkIn;
use App\Models\Applicant;
use App\Models\ApplicantScore;
use App\Models\Lead;
use App\Models\Program;
use App\Models\ScoringParameter;
use App\Models\SelectionProcessStep;
use App\Models\SelectionSession;
use App\Models\SessionApplicant;
use App\Models\User;
use App\Services\AdmissionAssessmentPanelService;
use App\Services\AdmissionCalendarService;
use App\Services\AdmissionManagerReviewService;
use App\Services\AdmissionReminderService;
use App\Services\AdmissionWalkInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdmissionOsV031Test extends TestCase
{
    use RefreshDatabase;

    public function test_v031_reminders_assessments_walkins_and_reviews_are_db_backed(): void
    {
        $admin = $this->admin();
        $program = Program::factory()->create();
        $lead = Lead::factory()->create(['program_id' => $program->id, 'assigned_to' => $admin->id, 'current_handler_user_id' => $admin->id]);
        $applicant = Applicant::factory()->create(['program_id' => $program->id, 'assigned_to' => $admin->id]);
        $template = AdmissionCommunicationTemplate::create([
            'name' => 'Reminder Template',
            'channel' => 'whatsapp',
            'purpose' => 'follow_up',
            'body' => 'Hi {{ name }}, please complete {{ next_action }}.',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $reminder = app(AdmissionReminderService::class)->schedule($lead, [
            'template_id' => $template->id,
            'reason' => 'no_response_follow_up',
            'channel' => 'whatsapp',
            'due_at' => now()->addHour(),
            'notes' => 'Call back',
        ], $admin);
        app(AdmissionReminderService::class)->sendNow($reminder, $admin);
        $this->assertDatabaseHas('admission_communication_logs', [
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
            'status' => 'queued',
            'provider' => 'mock_whatsapp',
        ]);

        $step = SelectionProcessStep::create([
            'program_id' => $program->id,
            'name' => 'Personal Interview',
            'type' => 'pi',
            'step_order' => 1,
            'max_score' => 50,
            'weightage' => 50,
            'is_active' => true,
        ]);
        ScoringParameter::create(['selection_process_step_id' => $step->id, 'name' => 'Communication', 'max_score' => 50, 'sort_order' => 1]);
        $session = SelectionSession::create([
            'selection_process_step_id' => $step->id,
            'program_id' => $program->id,
            'session_name' => 'PI Panel',
            'scheduled_date' => today(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'scheduled',
            'created_by' => $admin->id,
        ]);

        $panelService = app(AdmissionAssessmentPanelService::class);
        $panel = $panelService->createPanel([
            'name' => 'PI Panel A',
            'panel_type' => 'personal_interview',
            'program_id' => $program->id,
            'selection_session_id' => $session->id,
            'capacity' => 1,
            'scheduled_at' => now(),
        ], $admin);
        $panelService->addEvaluator($panel, $admin, 'chair', true);
        $assignment = $panelService->assignApplicant($panel, $applicant, $admin);
        $this->assertSame('pending', $assignment->score_status);

        $score = ApplicantScore::create([
            'applicant_id' => $applicant->id,
            'selection_session_id' => $session->id,
            'selection_process_step_id' => $step->id,
            'scored_by' => $admin->id,
            'parameter_scores' => ['Communication' => 42],
            'total_score' => 42,
            'max_possible_score' => 50,
            'percentage' => 84,
        ]);
        $panelService->finalizeScore($score, $admin, 'recommended');
        $this->assertSame('finalized', $score->fresh()->score_status);
        $this->assertSame('finalized', $assignment->fresh()->score_status);

        $walkIn = app(AdmissionWalkInService::class)->record([
            'visitor_name' => 'Walk In Candidate',
            'visitor_phone' => '9999999999',
            'visitor_email' => 'walkin@example.test',
            'program_id' => $program->id,
            'assigned_counsellor_id' => $admin->id,
            'purpose' => 'admission_enquiry',
            'next_followup_at' => now()->addDay(),
        ], $admin);
        $convertedLead = app(AdmissionWalkInService::class)->convertToLead($walkIn, $admin);
        $this->assertSame($convertedLead->id, $walkIn->fresh()->lead_id);

        $review = app(AdmissionManagerReviewService::class)->create($applicant, [
            'review_type' => 'assessment_score_override_review',
            'assigned_manager_id' => $admin->id,
            'finding' => 'Score reviewed',
        ], $admin);
        app(AdmissionManagerReviewService::class)->resolve($review, $admin, 'Reviewed and accepted.');
        $this->assertSame('resolved', $review->fresh()->status);

        $events = app(AdmissionCalendarService::class)->eventsFor($admin);
        $this->assertTrue($events->whereIn('type', ['reminder', 'assessment_session', 'assessment_panel', 'walk_in'])->count() >= 3);
    }

    public function test_v031_routes_and_demo_data_render_for_admission_head(): void
    {
        $this->seed(\Database\Seeders\DemoDataSeeder::class);
        $head = User::where('email', 'head@college.com')->firstOrFail();

        $this->assertDatabaseHas('admission_reminder_schedules', ['reason' => 'no_response_follow_up']);
        $this->assertDatabaseHas('admission_assessment_panels', ['name' => 'Case Analysis Panel A']);
        $this->assertDatabaseHas('admission_walk_ins', ['visitor_email' => 'rahul.walkin@demo.local']);
        $this->assertDatabaseHas('admission_manager_reviews', ['review_type' => 'duplicate_review']);

        foreach ([
            'admission.counsellor-workspace.index' => 'Counsellor Workspace',
            'admission.manager-workspace.index' => 'Manager Workspace',
            'admission.reminders.index' => 'Reminder And Cadence Engine',
            'admission.assessment-panels.index' => 'Assessment Panels',
            'admission.assessment-operations.index' => 'Assessment Operations',
            'admission.calendar.index' => 'Admission Calendar',
            'admission.walk-ins.index' => 'Walk-in And Campus Visit Desk',
            'admission.manager-reviews.index' => 'Manager Review Queue',
        ] as $routeName => $text) {
            $this->actingAs($head)
                ->get(route($routeName))
                ->assertOk()
                ->assertSee($text)
                ->assertDontSee('Illuminate\\Database\\QueryException')
                ->assertDontSee('LARAVEL');
        }
    }

    public function test_call_letter_requires_active_session_assignment(): void
    {
        $admin = $this->admin();
        $program = Program::factory()->create();
        $step = SelectionProcessStep::create([
            'program_id' => $program->id,
            'name' => 'Case Discussion',
            'type' => 'gd',
            'step_order' => 1,
            'max_score' => 50,
            'weightage' => 50,
            'is_active' => true,
        ]);
        $session = SelectionSession::create([
            'selection_process_step_id' => $step->id,
            'program_id' => $program->id,
            'session_name' => 'Case Panel',
            'scheduled_date' => today()->addDay(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'scheduled',
            'created_by' => $admin->id,
        ]);

        $assigned = Applicant::factory()->create(['program_id' => $program->id, 'status' => 'shortlisted']);
        $unassigned = Applicant::factory()->create(['program_id' => $program->id, 'status' => 'shortlisted']);
        $final = Applicant::factory()->create(['program_id' => $program->id, 'status' => 'enrolled']);

        SessionApplicant::create([
            'selection_session_id' => $session->id,
            'applicant_id' => $assigned->id,
            'assigned_at' => now(),
            'attendance_status' => 'pending',
        ]);
        SessionApplicant::create([
            'selection_session_id' => $session->id,
            'applicant_id' => $final->id,
            'assigned_at' => now(),
            'attendance_status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admission.applicants.call-letter', $unassigned))
            ->assertNotFound();

        $this->actingAs($admin)
            ->get(route('admission.applicants.call-letter', $final))
            ->assertNotFound();

        $response = $this->actingAs($admin)
            ->get(route('admission.applicants.call-letter', $assigned));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }
}
