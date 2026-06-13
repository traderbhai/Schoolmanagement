<?php

namespace Tests\Feature;

use App\Models\AdmissionAutomation;
use App\Models\AdmissionAutomationExecution;
use App\Models\AdmissionCommunicationTemplate;
use App\Models\AdmissionJourney;
use App\Models\AdmissionPartner;
use App\Models\Applicant;
use App\Models\Department;
use App\Models\DepartmentMember;
use App\Models\DepartmentRole;
use App\Models\Lead;
use App\Models\Program;
use App\Models\User;
use App\Services\AdmissionApprovalService;
use App\Services\AdmissionAutomationService;
use App\Services\AdmissionCallService;
use App\Services\AdmissionCommunicationService;
use App\Services\AdmissionDataQualityService;
use App\Services\AdmissionForecastingService;
use App\Services\AdmissionJourneyService;
use App\Services\AdmissionLeadScoringService;
use App\Services\AdmissionPartnerService;
use App\Services\AdmissionPipelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdmissionOsV003Test extends TestCase
{
    use RefreshDatabase;

    public function test_v003_services_cover_real_world_admission_operations(): void
    {
        $head = $this->admissionUser('admission_head');
        $this->member($head, 'admission_head');
        $program = Program::factory()->create();
        $lead = Lead::factory()->create([
            'program_id' => $program->id,
            'source' => 'web_form',
            'status' => 'new',
            'priority' => 'urgent',
            'assigned_to' => $head->id,
            'current_handler_user_id' => $head->id,
        ]);

        $template = AdmissionCommunicationTemplate::create([
            'name' => 'Hot Lead Reminder',
            'channel' => 'sms',
            'purpose' => 'follow_up',
            'body' => 'Hello {{ name }}, continue your {{ program }} admission.',
            'created_by' => $head->id,
        ]);
        $communication = app(AdmissionCommunicationService::class);
        $message = $communication->queue($lead, $template, $head);
        $this->assertSame('queued', $message->status);
        $this->assertSame('mock_sms', $message->provider);
        $this->assertSame(1, $communication->dispatchQueued());
        $this->assertSame('sent', $message->fresh()->status);

        app(AdmissionCallService::class)->logCall($lead, $head, [
            'disposition' => 'interested',
            'duration_seconds' => 180,
            'next_followup_at' => now()->addDay(),
            'notes' => 'Asked for scholarship details.',
        ]);
        $this->assertSame('interested', $lead->fresh()->status);
        $this->assertDatabaseHas('admission_call_logs', ['subject_id' => $lead->id, 'disposition' => 'interested']);

        $score = app(AdmissionLeadScoringService::class)->score($lead->fresh(), $head);
        $this->assertGreaterThan(0, $score->score);
        $this->assertContains($lead->fresh()->score_band, ['hot', 'warm', 'cold']);

        app(AdmissionPipelineService::class)->move($lead->fresh(), 'contacted', $head, 'Validated by telecaller');
        $this->assertSame('contacted', $lead->fresh()->status);

        $automation = AdmissionAutomation::create([
            'name' => 'Score web lead once',
            'trigger' => 'lead_updated',
            'priority' => 1,
            'conditions' => ['source' => 'web_form'],
            'actions' => [['type' => 'score_lead'], ['type' => 'next_action', 'value' => 'Manager review']],
            'created_by' => $head->id,
        ]);
        $firstRun = app(AdmissionAutomationService::class)->run('lead_updated', $lead->fresh(), $head);
        $secondRun = app(AdmissionAutomationService::class)->run('lead_updated', $lead->fresh(), $head);
        $this->assertCount(1, $firstRun);
        $this->assertCount(1, $secondRun);
        $this->assertSame(1, AdmissionAutomationExecution::where('automation_id', $automation->id)->count());
        $this->assertSame('Manager review', $lead->fresh()->next_action);

        $journey = AdmissionJourney::create(['name' => 'Default UG Journey', 'program_id' => $program->id, 'created_by' => $head->id]);
        $version = app(AdmissionJourneyService::class)->publish($journey, [
            'stages' => ['draft', 'submitted', 'selected', 'enrolled'],
            'documents' => ['photo', 'marksheet'],
            'enrollment_blockers' => ['registration_fee'],
        ], $head);
        $applicant = Applicant::factory()->create(['program_id' => $program->id, 'status' => 'submitted']);
        $checklist = app(AdmissionJourneyService::class)->checklist($applicant);
        $this->assertSame($version->id, $applicant->fresh()->journey_version_id);
        $this->assertContains('Registration fee is not paid.', $checklist['blockers']);

        $partner = AdmissionPartner::create(['name' => 'City Channel', 'allowed_program_ids' => [$program->id]]);
        app(AdmissionPartnerService::class)->approve($partner, $head);
        $partnerLead = app(AdmissionPartnerService::class)->submitLead($partner->fresh(), [
            'name' => 'Partner Student',
            'phone' => '9999999999',
            'program_id' => $program->id,
        ]);
        $this->assertSame($partner->id, $partnerLead->admission_partner_id);

        $flags = app(AdmissionDataQualityService::class)->scanLead(Lead::factory()->create([
            'program_id' => $program->id,
            'phone' => null,
            'source' => 'web_form',
        ]));
        $this->assertGreaterThanOrEqual(1, $flags->count());

        $snapshot = app(AdmissionForecastingService::class)->snapshot(['program_id' => $program->id, 'target_seats' => 10], $head);
        $this->assertGreaterThanOrEqual(1, $snapshot->lead_count);
        $this->assertSame(10, $snapshot->target_seats);

        $approval = app(AdmissionApprovalService::class)->request($lead->fresh(), 'priority_override', $head, ['priority' => 'high'], 'Head override');
        app(AdmissionApprovalService::class)->approve($approval, $head);
        $this->assertSame('approved', $approval->fresh()->status);
        $this->assertSame('high', $lead->fresh()->priority);
    }

    public function test_v003_staff_routes_render_for_admission_head(): void
    {
        $head = $this->admissionUser('admission_head');
        $this->member($head, 'admission_head');

        foreach ([
            'admission.command-center.index',
            'admission.communication.index',
            'admission.call-queue.index',
            'admission.pipeline.index',
            'admission.automations.index',
            'admission.scoring.index',
            'admission.journeys.index',
            'admission.partners.index',
            'admission.data-quality.index',
            'admission.forecasting.index',
            'admission.approvals.index',
        ] as $routeName) {
            $this->actingAs($head)->get(route($routeName))->assertOk();
        }
    }

    public function test_demo_seeder_populates_v003_admission_operating_data(): void
    {
        $this->seed(\Database\Seeders\DemoDataSeeder::class);

        $head = User::where('email', 'head@college.com')->firstOrFail();
        $applicant = Applicant::whereHas('user', fn ($query) => $query->where('email', 'rahul.verma@applicant.demo'))->firstOrFail();

        $this->assertDatabaseHas('admission_communication_templates', ['name' => 'Application Completion Reminder']);
        $this->assertDatabaseHas('admission_partners', ['name' => 'City Admissions Channel', 'status' => 'approved']);
        $this->assertDatabaseHas('admission_pipeline_boards', ['object_type' => 'lead', 'is_default' => true]);
        $this->assertDatabaseHas('admission_saved_views', ['name' => 'Admission Head Command Center']);
        $this->assertDatabaseHas('admission_automations', ['name' => 'Auto-score urgent web leads']);
        $this->assertDatabaseHas('admission_journeys', ['name' => 'PGDM Guided Admission Journey']);
        $this->assertDatabaseHas('admission_assignment_events', ['subject_type' => Applicant::class, 'subject_id' => $applicant->id]);
        $this->assertDatabaseHas('admission_communication_logs', ['subject_type' => Applicant::class, 'subject_id' => $applicant->id]);
        $this->assertDatabaseHas('admission_call_logs', ['subject_type' => Applicant::class, 'subject_id' => $applicant->id]);
        $this->assertDatabaseHas('admission_forecast_snapshots', ['source' => 'all']);
        $this->assertDatabaseHas('admission_data_quality_flags', ['flag_type' => 'possible_duplicate']);
        $this->assertDatabaseHas('admission_approvals', ['action' => 'offer_withdrawal_review']);

        $this->actingAs($head)
            ->get(route('admission.applicants.show', $applicant))
            ->assertOk()
            ->assertSee('v0.03 Operating Timeline')
            ->assertDontSee('Illuminate\\Database\\QueryException')
            ->assertDontSee('LARAVEL');
    }

    private function admissionUser(string $roleName): User
    {
        Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($roleName);

        return $user;
    }

    private function member(User $user, string $roleCode, ?DepartmentMember $manager = null): DepartmentMember
    {
        $department = Department::where('code', 'ADM')->firstOrFail();
        $role = DepartmentRole::where('department_id', $department->id)->where('code', $roleCode)->firstOrFail();

        return DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $role->id,
            'user_id' => $user->id,
            'reports_to_member_id' => $manager?->id,
            'is_active' => true,
        ]);
    }
}
