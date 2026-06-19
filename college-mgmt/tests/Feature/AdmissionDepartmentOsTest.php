<?php

namespace Tests\Feature;

use App\Models\AdmissionFeeInstallment;
use App\Models\AdmissionPayment;
use App\Models\AdmissionPaymentGatewayEvent;
use App\Models\AdmissionProcessTemplate;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\Batch;
use App\Models\Department;
use App\Models\DepartmentActivityLog;
use App\Models\DepartmentFeatureSetting;
use App\Models\DepartmentImpersonationSession;
use App\Models\DepartmentMember;
use App\Models\DepartmentRole;
use App\Models\DepartmentTeam;
use App\Models\Lead;
use App\Models\Program;
use App\Models\RequiredDocument;
use App\Models\User;
use App\Services\DepartmentHierarchyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdmissionDepartmentOsTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function applicant(string $status = 'submitted'): Applicant
    {
        Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);
        $program = Program::factory()->create(['is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $user = User::factory()->create();
        $user->assignRole('applicant');

        return Applicant::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => $status,
        ]);
    }

    public function test_admission_head_can_open_workbench_with_priority_queues(): void
    {
        $head = $this->userWithRole('admission_head');
        $applicant = $this->applicant('selected');
        $document = RequiredDocument::create([
            'program_id' => $applicant->program_id,
            'name' => 'Transfer Certificate',
            'is_mandatory' => true,
            'accepted_formats' => 'pdf',
            'is_active' => true,
        ]);

        Lead::factory()->create([
            'program_id' => $applicant->program_id,
            'status' => 'new',
            'priority' => 'urgent',
            'assigned_to' => null,
            'next_action' => 'Call parent',
        ]);
        ApplicantDocument::create([
            'applicant_id' => $applicant->id,
            'required_document_id' => $document->id,
            'file_path' => 'documents/tc.pdf',
            'original_name' => 'tc.pdf',
            'file_size_kb' => 100,
            'status' => 'pending',
            'uploaded_at' => now(),
            'version' => 1,
        ]);

        $installment = AdmissionFeeInstallment::create([
            'program_id' => $applicant->program_id,
            'batch_id' => $applicant->batch_id,
            'name' => 'First Installment',
            'amount' => 50000,
            'installment_number' => 1,
            'is_active' => true,
        ]);
        AdmissionPayment::create([
            'applicant_id' => $applicant->id,
            'admission_fee_installment_id' => $installment->id,
            'amount_paid' => 50000,
            'payment_date' => now()->toDateString(),
            'payment_mode' => 'upi',
            'status' => 'pending',
            'submitted_by' => $applicant->user_id,
        ]);

        $this->actingAs($head)
            ->get(route('admission.workbench'))
            ->assertOk()
            ->assertSee('Admission Workbench')
            ->assertSee('Call parent')
            ->assertSee('Transfer Certificate')
            ->assertSee('First Installment');
    }

    public function test_assignment_routes_update_owner_priority_and_sla(): void
    {
        $head = $this->userWithRole('admission_head');
        $counsellor = $this->userWithRole('admission_officer');
        $applicant = $this->applicant();
        $lead = Lead::factory()->create(['program_id' => $applicant->program_id]);

        $this->actingAs($head)->post(route('admission.applicants.assign', $applicant), [
            'assigned_to' => $counsellor->id,
            'priority' => 'high',
            'sla_due_at' => now()->addDay()->toDateTimeString(),
            'next_action' => 'Review documents',
        ])->assertRedirect();

        $this->actingAs($head)->post(route('admission.leads.workbench-assign', $lead), [
            'assigned_to' => $counsellor->id,
            'priority' => 'urgent',
            'sla_due_at' => now()->addHours(4)->toDateTimeString(),
            'next_action' => 'Call immediately',
            'team' => 'North',
            'region' => 'Delhi NCR',
        ])->assertRedirect();

        $this->assertDatabaseHas('applicants', [
            'id' => $applicant->id,
            'assigned_to' => $counsellor->id,
            'priority' => 'high',
            'next_action' => 'Review documents',
        ]);
        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'assigned_to' => $counsellor->id,
            'priority' => 'urgent',
            'team' => 'North',
            'region' => 'Delhi NCR',
        ]);
    }

    public function test_applicant_checklist_exposes_blockers(): void
    {
        $applicant = $this->applicant('draft');
        RequiredDocument::create([
            'program_id' => $applicant->program_id,
            'name' => '10th Marksheet',
            'is_mandatory' => true,
            'accepted_formats' => 'pdf',
            'is_active' => true,
        ]);

        $this->actingAs($applicant->user)
            ->get(route('applicant.checklist'))
            ->assertOk()
            ->assertSee('Admission Checklist')
            ->assertSee('10th Marksheet has not been uploaded.')
            ->assertSee('Registration fee is not recorded.');
    }

    public function test_admission_head_can_create_process_template_with_default_stages(): void
    {
        $head = $this->userWithRole('admission_head');
        $program = Program::factory()->create(['is_active' => true]);

        $this->actingAs($head)->post(route('admission.process-templates.store'), [
            'program_id' => $program->id,
            'name' => 'MBA Flexible Intake',
            'offer_validity_days' => 10,
            'waitlist_rule' => 'Promote after offer expiry',
        ])->assertRedirect();

        $template = AdmissionProcessTemplate::firstOrFail();
        $this->assertSame('MBA Flexible Intake', $template->name);
        $this->assertCount(5, $template->stages);
    }

    public function test_gateway_payment_order_and_webhook_are_idempotent(): void
    {
        $applicant = $this->applicant('selected');
        $installment = AdmissionFeeInstallment::create([
            'program_id' => $applicant->program_id,
            'batch_id' => $applicant->batch_id,
            'name' => 'Admission Fee',
            'amount' => 25000,
            'installment_number' => 1,
            'is_active' => true,
        ]);
        $payment = AdmissionPayment::create([
            'applicant_id' => $applicant->id,
            'admission_fee_installment_id' => $installment->id,
            'amount_paid' => 25000,
            'payment_date' => now()->toDateString(),
            'payment_mode' => 'upi',
            'status' => 'pending',
            'submitted_by' => $applicant->user_id,
        ]);

        $this->actingAs($applicant->user)
            ->post(route('applicant.fees.gateway.initiate', $payment))
            ->assertRedirect();

        $payment->refresh();
        $this->assertNotNull($payment->gateway_order_id);
        $this->assertDatabaseHas('department_activity_logs', [
            'department_id' => Department::where('code', 'ADM')->firstOrFail()->id,
            'actor_user_id' => $applicant->user_id,
            'action' => 'department_feature_action',
        ]);
        $this->assertSame(
            'admission.gateway_payments',
            DepartmentActivityLog::where('actor_user_id', $applicant->user_id)
                ->where('action', 'department_feature_action')
                ->latest()
                ->firstOrFail()
                ->metadata['feature_key']
        );

        $payload = [
            'provider' => 'razorpay_mock',
            'event_id' => 'evt_adm_001',
            'event' => 'payment.captured',
            'order_id' => $payment->gateway_order_id,
            'payment_id' => 'pay_adm_001',
            'status' => 'captured',
        ];

        $this->postJson(route('admission.gateway.webhook'), $payload)->assertOk();
        $this->postJson(route('admission.gateway.webhook'), $payload)->assertOk();

        $this->assertSame('verified', $payment->fresh()->status);
        $this->assertSame('pay_adm_001', $payment->fresh()->gateway_payment_id);
        $this->assertSame(1, AdmissionPaymentGatewayEvent::where('event_id', 'evt_adm_001')->count());
    }

    public function test_gateway_webhook_does_not_update_payment_when_order_and_payment_identifiers_conflict(): void
    {
        $firstApplicant = $this->applicant('selected');
        $secondApplicant = $this->applicant('selected');
        $firstInstallment = AdmissionFeeInstallment::create([
            'program_id' => $firstApplicant->program_id,
            'batch_id' => $firstApplicant->batch_id,
            'name' => 'Admission Fee',
            'amount' => 20000,
            'installment_number' => 1,
            'is_active' => true,
        ]);
        $secondInstallment = AdmissionFeeInstallment::create([
            'program_id' => $secondApplicant->program_id,
            'batch_id' => $secondApplicant->batch_id,
            'name' => 'Admission Fee',
            'amount' => 20000,
            'installment_number' => 1,
            'is_active' => true,
        ]);
        $firstPayment = AdmissionPayment::create([
            'applicant_id' => $firstApplicant->id,
            'admission_fee_installment_id' => $firstInstallment->id,
            'amount_paid' => 20000,
            'payment_date' => now()->toDateString(),
            'payment_mode' => 'upi',
            'status' => 'pending',
            'submitted_by' => $firstApplicant->user_id,
            'provider' => 'razorpay_mock',
            'gateway_order_id' => 'order_conflict_first',
            'gateway_status' => 'created',
        ]);
        $secondPayment = AdmissionPayment::create([
            'applicant_id' => $secondApplicant->id,
            'admission_fee_installment_id' => $secondInstallment->id,
            'amount_paid' => 20000,
            'payment_date' => now()->toDateString(),
            'payment_mode' => 'upi',
            'status' => 'pending',
            'submitted_by' => $secondApplicant->user_id,
            'provider' => 'razorpay_mock',
            'gateway_order_id' => 'order_conflict_second',
            'gateway_payment_id' => 'pay_conflict_second',
            'gateway_status' => 'created',
        ]);

        $this->postJson(route('admission.gateway.webhook'), [
            'provider' => 'razorpay_mock',
            'event_id' => 'evt_conflict_identifier',
            'event' => 'payment.captured',
            'order_id' => $firstPayment->gateway_order_id,
            'payment_id' => $secondPayment->gateway_payment_id,
            'status' => 'captured',
        ])->assertOk();

        $this->assertSame('pending', $firstPayment->fresh()->status);
        $this->assertNull($firstPayment->fresh()->gateway_payment_id);
        $this->assertSame('pending', $secondPayment->fresh()->status);
        $this->assertNotNull(
            AdmissionPaymentGatewayEvent::where('provider', 'razorpay_mock')
                ->where('event_id', 'evt_conflict_identifier')
                ->firstOrFail()
                ->processed_at
        );
    }

    public function test_gateway_webhook_payment_id_lookup_is_provider_scoped(): void
    {
        $applicant = $this->applicant('selected');
        $installment = AdmissionFeeInstallment::create([
            'program_id' => $applicant->program_id,
            'batch_id' => $applicant->batch_id,
            'name' => 'Admission Fee',
            'amount' => 15000,
            'installment_number' => 1,
            'is_active' => true,
        ]);
        $payment = AdmissionPayment::create([
            'applicant_id' => $applicant->id,
            'admission_fee_installment_id' => $installment->id,
            'amount_paid' => 15000,
            'payment_date' => now()->toDateString(),
            'payment_mode' => 'upi',
            'status' => 'pending',
            'submitted_by' => $applicant->user_id,
            'provider' => 'razorpay_mock',
            'gateway_order_id' => 'order_provider_scoped',
            'gateway_payment_id' => 'pay_shared_provider_id',
            'gateway_status' => 'created',
        ]);

        $this->postJson(route('admission.gateway.webhook'), [
            'provider' => 'stripe_sandbox',
            'event_id' => 'evt_wrong_provider',
            'event' => 'payment.captured',
            'payment_id' => 'pay_shared_provider_id',
            'status' => 'captured',
        ])->assertOk();

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('created', $payment->fresh()->gateway_status);
        $this->assertDatabaseHas('admission_payment_gateway_events', [
            'provider' => 'stripe_sandbox',
            'event_id' => 'evt_wrong_provider',
        ]);
    }

    public function test_admin_can_configure_reusable_department_hierarchy(): void
    {
        $admin = $this->userWithRole('admin');
        $department = Department::where('code', 'ADM')->firstOrFail();
        $managerUser = User::factory()->create(['name' => 'Admission Manager']);

        $this->actingAs($admin)->post(route('department-hierarchy.roles.store'), [
            'department_id' => $department->id,
            'name' => 'Admission Manager',
            'code' => 'admission_manager',
            'level' => 30,
            'can_manage_lower_levels' => 1,
            'can_view_team_data' => 1,
            'can_assign_work' => 1,
            'permissions' => ['view_team', 'assign_work'],
        ])->assertRedirect();

        $role = DepartmentRole::firstOrFail();

        $this->actingAs($admin)->post(route('department-hierarchy.teams.store'), [
            'department_id' => $department->id,
            'name' => 'North Region',
            'type' => 'region',
        ])->assertRedirect();

        $team = DepartmentTeam::firstOrFail();

        $this->actingAs($admin)->post(route('department-hierarchy.members.store'), [
            'department_id' => $department->id,
            'department_role_id' => $role->id,
            'department_team_id' => $team->id,
            'user_id' => $managerUser->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('department_roles', [
            'department_id' => $department->id,
            'code' => 'admission_manager',
            'can_assign_work' => true,
        ]);
        $this->assertDatabaseHas('department_teams', [
            'department_id' => $department->id,
            'name' => 'North Region',
            'type' => 'region',
        ]);
        $this->assertDatabaseHas('department_members', [
            'department_id' => $department->id,
            'department_role_id' => $role->id,
            'department_team_id' => $team->id,
            'user_id' => $managerUser->id,
        ]);
    }

    public function test_department_head_can_configure_lower_hierarchy_levels(): void
    {
        $department = Department::where('code', 'ADM')->firstOrFail();
        $head = $this->userWithRole('admission_head');
        $counsellor = $this->userWithRole('admission_counsellor');
        $headRole = DepartmentRole::where('department_id', $department->id)->where('code', 'admission_head')->firstOrFail();
        $headMember = DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $headRole->id,
            'user_id' => $head->id,
        ]);

        $this->actingAs($head)
            ->get(route('department-hierarchy.index', ['department_id' => $department->id]))
            ->assertOk()
            ->assertSee('Department Hierarchy');

        $this->actingAs($head)->post(route('department-hierarchy.roles.store'), [
            'department_id' => $department->id,
            'name' => 'Admission Quality Lead',
            'code' => 'admission_quality_lead',
            'level' => 40,
            'can_manage_lower_levels' => 1,
            'can_view_team_data' => 1,
            'can_assign_work' => 1,
            'permissions' => ['view_team', 'assign_work'],
        ])->assertRedirect();

        $role = DepartmentRole::where('department_id', $department->id)
            ->where('code', 'admission_quality_lead')
            ->firstOrFail();

        $this->actingAs($head)->post(route('department-hierarchy.members.store'), [
            'department_id' => $department->id,
            'department_role_id' => $role->id,
            'user_id' => $counsellor->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('department_members', [
            'department_id' => $department->id,
            'department_role_id' => $role->id,
            'user_id' => $counsellor->id,
            'reports_to_member_id' => $headMember->id,
        ]);
        $this->assertDatabaseHas('department_activity_logs', [
            'department_id' => $department->id,
            'actor_user_id' => $head->id,
            'target_user_id' => $counsellor->id,
            'action' => 'department_member_saved',
        ]);
    }

    public function test_department_hierarchy_records_can_be_safely_deactivated(): void
    {
        $department = Department::where('code', 'ADM')->firstOrFail();
        $admin = $this->userWithRole('admin');
        $leafUser = $this->userWithRole('admission_counsellor');
        $managerUser = $this->userWithRole('admission_manager');
        $reportUser = $this->userWithRole('admission_counsellor');
        $unusedRole = DepartmentRole::create([
            'department_id' => $department->id,
            'name' => 'Unused Interview Panel',
            'code' => 'unused_interview_panel',
            'level' => 75,
            'can_manage_lower_levels' => false,
            'can_view_team_data' => false,
            'can_assign_work' => false,
            'permissions' => ['view_assigned'],
            'is_active' => true,
        ]);
        $unusedTeam = DepartmentTeam::create([
            'department_id' => $department->id,
            'name' => 'Temporary Outreach',
            'type' => 'custom',
            'is_active' => true,
        ]);
        $staffRole = DepartmentRole::where('department_id', $department->id)->where('code', 'admission_counsellor')->firstOrFail();
        $managerRole = DepartmentRole::where('department_id', $department->id)->where('code', 'admission_manager')->firstOrFail();
        $leafMember = DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $staffRole->id,
            'user_id' => $leafUser->id,
        ]);
        $managerMember = DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $managerRole->id,
            'user_id' => $managerUser->id,
        ]);
        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $staffRole->id,
            'department_team_id' => $unusedTeam->id,
            'user_id' => $reportUser->id,
            'reports_to_member_id' => $managerMember->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('department-hierarchy.roles.deactivate', $staffRole))
            ->assertStatus(422);
        $this->actingAs($admin)
            ->patch(route('department-hierarchy.teams.deactivate', $unusedTeam))
            ->assertStatus(422);
        $this->actingAs($admin)
            ->patch(route('department-hierarchy.members.deactivate', $managerMember))
            ->assertStatus(422);

        $this->actingAs($admin)
            ->patch(route('department-hierarchy.roles.deactivate', $unusedRole))
            ->assertRedirect();
        $this->actingAs($admin)
            ->patch(route('department-hierarchy.members.deactivate', $leafMember))
            ->assertRedirect();

        $unusedTeam->members()->update(['is_active' => false]);
        $this->actingAs($admin)
            ->patch(route('department-hierarchy.teams.deactivate', $unusedTeam))
            ->assertRedirect();

        $this->assertDatabaseHas('department_roles', [
            'id' => $unusedRole->id,
            'is_active' => false,
            'deactivated_by' => $admin->id,
        ]);
        $this->assertNotNull($unusedRole->fresh()->deactivated_at);
        $this->assertDatabaseHas('department_members', [
            'id' => $leafMember->id,
            'is_active' => false,
            'deactivated_by' => $admin->id,
        ]);
        $this->assertNotNull($leafMember->fresh()->deactivated_at);
        $this->assertDatabaseHas('department_teams', [
            'id' => $unusedTeam->id,
            'is_active' => false,
            'deactivated_by' => $admin->id,
        ]);
        $this->assertNotNull($unusedTeam->fresh()->deactivated_at);
        $this->assertDatabaseHas('department_activity_logs', [
            'department_id' => $department->id,
            'actor_user_id' => $admin->id,
            'action' => 'department_role_deactivated',
        ]);
        $this->assertDatabaseHas('department_activity_logs', [
            'department_id' => $department->id,
            'actor_user_id' => $admin->id,
            'target_user_id' => $leafUser->id,
            'action' => 'department_member_deactivated',
        ]);
    }

    public function test_deactivated_department_role_and_team_can_be_reactivated_without_duplicate_history(): void
    {
        $department = Department::where('code', 'ADM')->firstOrFail();
        $admin = $this->userWithRole('admin');
        $role = DepartmentRole::create([
            'department_id' => $department->id,
            'name' => 'Paused Outreach Lead',
            'code' => 'paused_outreach_lead',
            'level' => 60,
            'permissions' => ['view_team'],
            'is_active' => false,
            'deactivated_by' => $admin->id,
            'deactivated_at' => now()->subDay(),
        ]);
        $team = DepartmentTeam::create([
            'department_id' => $department->id,
            'name' => 'Paused Outreach Team',
            'type' => 'custom',
            'is_active' => false,
            'deactivated_by' => $admin->id,
            'deactivated_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)->post(route('department-hierarchy.roles.store'), [
            'department_id' => $department->id,
            'name' => 'Paused Outreach Lead',
            'code' => 'paused_outreach_lead',
            'level' => 60,
            'permissions' => ['view_team', 'assign_work'],
            'can_view_team_data' => 1,
        ])->assertRedirect()
            ->assertSessionHas('success', 'Department role saved.');

        $this->actingAs($admin)->post(route('department-hierarchy.teams.store'), [
            'department_id' => $department->id,
            'name' => 'Paused Outreach Team',
            'type' => 'custom',
        ])->assertRedirect()
            ->assertSessionHas('success', 'Department team created.');

        $role->refresh();
        $team->refresh();
        $this->assertTrue($role->is_active);
        $this->assertNull($role->deactivated_by);
        $this->assertNull($role->deactivated_at);
        $this->assertTrue($role->can_view_team_data);
        $this->assertSame(['view_team', 'assign_work'], $role->permissions);
        $this->assertTrue($team->is_active);
        $this->assertNull($team->deactivated_by);
        $this->assertNull($team->deactivated_at);
        $this->assertSame(1, DepartmentRole::where('department_id', $department->id)->where('code', 'paused_outreach_lead')->count());
        $this->assertSame(1, DepartmentTeam::where('department_id', $department->id)->where('name', 'Paused Outreach Team')->where('type', 'custom')->count());
    }

    public function test_deactivated_department_member_can_be_reactivated_without_duplicate_history(): void
    {
        $department = Department::where('code', 'ADM')->firstOrFail();
        $admin = $this->userWithRole('admin');
        $user = User::factory()->create();
        $role = DepartmentRole::where('department_id', $department->id)->where('code', 'admission_counsellor')->firstOrFail();
        $member = DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $role->id,
            'user_id' => $user->id,
            'is_active' => false,
            'deactivated_by' => $admin->id,
            'deactivated_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)->post(route('department-hierarchy.members.store'), [
            'department_id' => $department->id,
            'department_role_id' => $role->id,
            'user_id' => $user->id,
        ])->assertRedirect()
            ->assertSessionHas('success', 'Department member saved.');

        $member->refresh();
        $this->assertTrue($member->is_active);
        $this->assertNull($member->deactivated_by);
        $this->assertNull($member->deactivated_at);
        $this->assertSame(1, DepartmentMember::where('department_id', $department->id)
            ->where('department_role_id', $role->id)
            ->where('user_id', $user->id)
            ->count());
    }

    public function test_department_member_role_change_keeps_single_active_membership(): void
    {
        $department = Department::where('code', 'ADM')->firstOrFail();
        $admin = $this->userWithRole('admin');
        $user = User::factory()->create();
        $counsellorRole = DepartmentRole::where('department_id', $department->id)->where('code', 'admission_counsellor')->firstOrFail();
        $managerRole = DepartmentRole::where('department_id', $department->id)->where('code', 'admission_manager')->firstOrFail();

        $this->actingAs($admin)->post(route('department-hierarchy.members.store'), [
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $user->id,
        ])->assertRedirect();

        $memberId = DepartmentMember::where('department_id', $department->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->value('id');

        $this->actingAs($admin)->post(route('department-hierarchy.members.store'), [
            'department_id' => $department->id,
            'department_role_id' => $managerRole->id,
            'user_id' => $user->id,
        ])->assertRedirect();

        $this->assertSame(1, DepartmentMember::where('department_id', $department->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->count());
        $this->assertDatabaseHas('department_members', [
            'id' => $memberId,
            'department_id' => $department->id,
            'department_role_id' => $managerRole->id,
            'user_id' => $user->id,
            'is_active' => true,
        ]);
    }

    public function test_department_member_with_direct_reports_cannot_be_moved_below_reports(): void
    {
        $department = Department::where('code', 'ADM')->firstOrFail();
        $admin = $this->userWithRole('admin');
        $managerUser = User::factory()->create();
        $reportUser = User::factory()->create();
        $managerRole = DepartmentRole::where('department_id', $department->id)->where('code', 'admission_manager')->firstOrFail();
        $counsellorRole = DepartmentRole::where('department_id', $department->id)->where('code', 'admission_counsellor')->firstOrFail();
        $managerMember = DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $managerRole->id,
            'user_id' => $managerUser->id,
        ]);
        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $reportUser->id,
            'reports_to_member_id' => $managerMember->id,
        ]);

        $this->actingAs($admin)->post(route('department-hierarchy.members.store'), [
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $managerUser->id,
        ])->assertStatus(422);

        $this->assertDatabaseHas('department_members', [
            'id' => $managerMember->id,
            'department_role_id' => $managerRole->id,
            'is_active' => true,
        ]);
    }

    public function test_department_hierarchy_blocks_unprivileged_or_unsafe_changes(): void
    {
        $department = Department::where('code', 'ADM')->firstOrFail();
        $head = $this->userWithRole('admission_head');
        $counsellor = $this->userWithRole('admission_counsellor');
        $admin = $this->userWithRole('admin');
        $headRole = DepartmentRole::where('department_id', $department->id)->where('code', 'admission_head')->firstOrFail();
        $managerRole = DepartmentRole::where('department_id', $department->id)->where('code', 'admission_manager')->firstOrFail();
        $counsellorRole = DepartmentRole::where('department_id', $department->id)->where('code', 'admission_counsellor')->firstOrFail();
        $viewerRole = DepartmentRole::where('department_id', $department->id)->where('code', 'department_viewer')->firstOrFail();
        $headMember = DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $headRole->id,
            'user_id' => $head->id,
        ]);
        $counsellorMember = DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $counsellor->id,
            'reports_to_member_id' => $headMember->id,
        ]);
        $viewer = $this->userWithRole('admission_officer');
        $viewerMember = DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $viewerRole->id,
            'user_id' => $viewer->id,
            'reports_to_member_id' => $headMember->id,
        ]);

        $this->actingAs($counsellor)
            ->get(route('department-hierarchy.index', ['department_id' => $department->id]))
            ->assertForbidden();

        $this->actingAs($head)->post(route('department-hierarchy.roles.store'), [
            'department_id' => $department->id,
            'name' => 'Parallel Head',
            'code' => 'parallel_head',
            'level' => 10,
        ])->assertForbidden();

        $this->actingAs($head)->post(route('department-hierarchy.members.store'), [
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $admin->id,
        ])->assertForbidden();

        $managerCandidate = $this->userWithRole('admission_manager');
        $this->actingAs($head)->post(route('department-hierarchy.members.store'), [
            'department_id' => $department->id,
            'department_role_id' => $managerRole->id,
            'user_id' => $managerCandidate->id,
            'reports_to_member_id' => $counsellorMember->id,
        ])->assertStatus(422);

        $selfReportingCandidate = $this->userWithRole('admission_counsellor');
        $selfReportingExistingMember = DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $viewerRole->id,
            'user_id' => $selfReportingCandidate->id,
            'reports_to_member_id' => $headMember->id,
        ]);
        $this->actingAs($head)->post(route('department-hierarchy.members.store'), [
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $selfReportingCandidate->id,
            'reports_to_member_id' => $selfReportingExistingMember->id,
        ])->assertStatus(422);

        $staffCandidate = $this->userWithRole('admission_counsellor');
        $this->actingAs($head)->post(route('department-hierarchy.members.store'), [
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $staffCandidate->id,
            'reports_to_member_id' => $viewerMember->id,
        ])->assertStatus(422);
    }

    public function test_default_admission_hierarchy_roles_are_available(): void
    {
        $department = Department::where('code', 'ADM')->firstOrFail();

        $this->assertDatabaseHas('department_roles', [
            'department_id' => $department->id,
            'code' => 'admission_director',
            'level' => 5,
            'can_assign_work' => true,
        ]);
        $this->assertDatabaseHas('department_roles', [
            'department_id' => $department->id,
            'code' => 'admission_telecaller',
            'level' => 90,
            'can_assign_work' => false,
        ]);
    }

    public function test_operational_departments_receive_reusable_default_hierarchy_roles(): void
    {
        foreach (['ACAD', 'ACC', 'EXAM', 'CMC', 'HOSTEL', 'TRANSPORT', 'LIB'] as $code) {
            $department = Department::where('code', $code)->firstOrFail();

            $this->assertDatabaseHas('department_roles', [
                'department_id' => $department->id,
                'code' => 'department_owner',
                'level' => 10,
                'can_manage_lower_levels' => true,
                'can_assign_work' => true,
            ]);
            $this->assertDatabaseHas('department_roles', [
                'department_id' => $department->id,
                'code' => 'department_staff',
                'level' => 80,
                'can_manage_lower_levels' => false,
            ]);
        }
    }

    public function test_existing_app_role_user_gets_default_department_owner_membership_on_first_governance_access(): void
    {
        $department = Department::where('code', 'ACC')->firstOrFail();
        $owner = $this->userWithRole('accounts_officer');

        $this->assertDatabaseMissing('department_members', [
            'department_id' => $department->id,
            'user_id' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get(route('department-governance.index', ['department_id' => $department->id]))
            ->assertOk()
            ->assertSee('Finance Dashboard');

        $ownerRole = DepartmentRole::where('department_id', $department->id)->where('code', 'department_owner')->firstOrFail();
        $this->assertDatabaseHas('department_members', [
            'department_id' => $department->id,
            'department_role_id' => $ownerRole->id,
            'user_id' => $owner->id,
            'is_active' => true,
        ]);
    }

    public function test_admission_head_gets_default_department_membership_on_first_hierarchy_access(): void
    {
        $department = Department::where('code', 'ADM')->firstOrFail();
        $head = $this->userWithRole('admission_head');

        $this->assertDatabaseMissing('department_members', [
            'department_id' => $department->id,
            'user_id' => $head->id,
        ]);

        $this->actingAs($head)
            ->get(route('department-hierarchy.index', ['department_id' => $department->id]))
            ->assertOk()
            ->assertSee('Admissions');

        $headRole = DepartmentRole::where('department_id', $department->id)->where('code', 'admission_head')->firstOrFail();
        $this->assertDatabaseHas('department_members', [
            'department_id' => $department->id,
            'department_role_id' => $headRole->id,
            'user_id' => $head->id,
            'is_active' => true,
        ]);
    }

    public function test_default_department_membership_does_not_override_explicit_membership(): void
    {
        $department = Department::where('code', 'ACC')->firstOrFail();
        $user = $this->userWithRole('accounts_officer');
        $staffRole = DepartmentRole::where('department_id', $department->id)->where('code', 'department_staff')->firstOrFail();
        $explicitMember = DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $staffRole->id,
            'user_id' => $user->id,
        ]);

        $service = app(DepartmentHierarchyService::class);
        $member = $service->membershipFor($user, $department);
        $manageableDepartments = $service->manageableDepartments($user);

        $this->assertSame($explicitMember->id, $member?->id);
        $this->assertTrue($manageableDepartments->isEmpty());
        $this->assertDatabaseCount('department_members', 1);
        $this->assertDatabaseHas('department_members', [
            'id' => $explicitMember->id,
            'department_id' => $department->id,
            'department_role_id' => $staffRole->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_non_admission_department_owner_can_manage_features_and_impersonate_subordinate(): void
    {
        $department = Department::where('code', 'ACC')->firstOrFail();
        $owner = $this->userWithRole('accounts_officer');
        $staff = $this->userWithRole('accounts_officer');
        $ownerRole = DepartmentRole::where('department_id', $department->id)->where('code', 'department_owner')->firstOrFail();
        $staffRole = DepartmentRole::where('department_id', $department->id)->where('code', 'department_staff')->firstOrFail();
        $ownerMember = DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $ownerRole->id,
            'user_id' => $owner->id,
        ]);
        $staffMember = DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $staffRole->id,
            'user_id' => $staff->id,
            'reports_to_member_id' => $ownerMember->id,
        ]);

        $this->actingAs($owner)
            ->get(route('department-governance.index', ['department_id' => $department->id]))
            ->assertOk()
            ->assertSee('Finance Dashboard')
            ->assertSee('accounts.reconciliation');

        $this->actingAs($owner)
            ->get(route('accounts.dashboard'))
            ->assertOk()
            ->assertSee('Department Controls')
            ->assertSee('Department Hierarchy')
            ->assertSee('Department Governance');

        $this->actingAs($owner)->post(route('department-governance.features.update', $department), [
            'feature_key' => 'accounts.reconciliation',
            'feature_name' => 'Reconciliation',
        ])->assertRedirect();

        $this->assertDatabaseHas('department_feature_settings', [
            'department_id' => $department->id,
            'feature_key' => 'accounts.reconciliation',
            'is_enabled' => false,
            'updated_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->post(route('department-governance.impersonation.start', $staffMember), [
                'reason' => 'Accounts support review',
            ])->assertRedirect();

        $this->assertAuthenticatedAs($staff);
        $this->assertSame($owner->id, session('impersonation.original_user_id'));
        $this->assertDatabaseHas('department_impersonation_sessions', [
            'department_id' => $department->id,
            'actor_user_id' => $owner->id,
            'target_user_id' => $staff->id,
            'reason' => 'Accounts support review',
        ]);
    }

    public function test_department_manager_can_impersonate_subordinate_but_cannot_manage_settings_or_hierarchy(): void
    {
        $department = Department::where('code', 'ACC')->firstOrFail();
        $manager = $this->userWithRole('accounts_officer');
        $staff = $this->userWithRole('accounts_officer');
        $managerRole = DepartmentRole::where('department_id', $department->id)->where('code', 'department_manager')->firstOrFail();
        $staffRole = DepartmentRole::where('department_id', $department->id)->where('code', 'department_staff')->firstOrFail();
        $managerMember = DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $managerRole->id,
            'user_id' => $manager->id,
        ]);
        $staffMember = DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $staffRole->id,
            'user_id' => $staff->id,
            'reports_to_member_id' => $managerMember->id,
        ]);

        $this->actingAs($manager)
            ->get(route('department-governance.index', ['department_id' => $department->id]))
            ->assertOk()
            ->assertSeeText('Department feature settings are controlled by Admin or the department Head/Owner.')
            ->assertSeeText('Login As')
            ->assertDontSee('Save Feature Setting');

        $this->actingAs($manager)->post(route('department-governance.features.update', $department), [
            'feature_key' => 'accounts.reconciliation',
            'feature_name' => 'Reconciliation',
            'is_enabled' => 0,
        ])->assertForbidden();

        $this->actingAs($manager)
            ->get(route('department-hierarchy.index', ['department_id' => $department->id]))
            ->assertForbidden();

        $this->actingAs($manager)
            ->post(route('department-governance.impersonation.start', $staffMember), [
                'reason' => 'Manager support review',
            ])->assertRedirect();

        $this->assertAuthenticatedAs($staff);
        $this->assertDatabaseHas('department_impersonation_sessions', [
            'department_id' => $department->id,
            'actor_user_id' => $manager->id,
            'target_user_id' => $staff->id,
            'reason' => 'Manager support review',
        ]);
    }

    public function test_low_level_role_with_settings_permission_cannot_configure_department_or_features(): void
    {
        $department = Department::where('code', 'ACC')->firstOrFail();
        $user = $this->userWithRole('accounts_officer');
        $role = DepartmentRole::create([
            'department_id' => $department->id,
            'name' => 'Over Permissioned Staff',
            'code' => 'over_permissioned_staff',
            'level' => 80,
            'can_manage_lower_levels' => false,
            'can_view_team_data' => false,
            'can_assign_work' => false,
            'permissions' => ['manage_department_settings', 'configure_department'],
            'is_active' => true,
        ]);
        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $role->id,
            'user_id' => $user->id,
        ]);

        $service = app(DepartmentHierarchyService::class);
        $this->assertFalse($service->canConfigureDepartmentHierarchy($user, $department));
        $this->assertFalse($service->canManageDepartmentSettings($user, $department));

        $this->actingAs($user)
            ->get(route('department-hierarchy.index', ['department_id' => $department->id]))
            ->assertForbidden();

        $this->actingAs($user)->post(route('department-hierarchy.roles.store'), [
            'department_id' => $department->id,
            'name' => 'Unsafe Lower Role',
            'code' => 'unsafe_lower_role',
            'level' => 90,
            'permissions' => ['view_assigned'],
        ])->assertForbidden();

        $this->actingAs($user)->post(route('department-governance.features.update', $department), [
            'feature_key' => 'accounts.reconciliation',
            'feature_name' => 'Reconciliation',
            'is_enabled' => 0,
        ])->assertForbidden();
    }

    public function test_low_level_department_role_cannot_be_created_with_owner_permissions(): void
    {
        $department = Department::where('code', 'ACC')->firstOrFail();
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->post(route('department-hierarchy.roles.store'), [
            'department_id' => $department->id,
            'name' => 'Unsafe Operations Staff',
            'code' => 'unsafe_operations_staff',
            'level' => 80,
            'permissions' => ['view_assigned', 'manage_department_settings', 'configure_department'],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('department_roles', [
            'department_id' => $department->id,
            'code' => 'unsafe_operations_staff',
        ]);
    }

    public function test_department_feature_settings_must_use_registered_feature_keys(): void
    {
        $department = Department::where('code', 'ACC')->firstOrFail();
        $owner = $this->userWithRole('accounts_officer');

        $this->actingAs($owner)->post(route('department-governance.features.update', $department), [
            'feature_key' => 'accounts.unknown_custom_feature',
            'feature_name' => 'Unknown Custom Feature',
            'is_enabled' => 0,
        ])->assertStatus(422);

        $this->actingAs($owner)->post(route('department-governance.features.update', $department), [
            'feature_key' => 'admission.workbench',
            'feature_name' => 'Admission Workbench',
            'is_enabled' => 0,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('department_feature_settings', [
            'department_id' => $department->id,
            'feature_key' => 'accounts.unknown_custom_feature',
        ]);
        $this->assertDatabaseMissing('department_feature_settings', [
            'department_id' => $department->id,
            'feature_key' => 'admission.workbench',
        ]);
    }

    public function test_feature_enabled_checks_fail_closed_for_unknown_feature_keys(): void
    {
        $service = app(DepartmentHierarchyService::class);

        $this->assertTrue($service->isFeatureEnabled('ACC', 'accounts.reconciliation'));
        $this->assertFalse($service->isFeatureEnabled('ACC', 'accounts.not_registered'));
        $this->assertFalse($service->isFeatureEnabled('ACC', 'admission.workbench'));
        $this->assertFalse($service->isFeatureEnabled('UNKNOWN', 'accounts.reconciliation'));
    }

    public function test_accounts_exam_and_cmc_feature_toggles_gate_routes(): void
    {
        $accounts = Department::where('code', 'ACC')->firstOrFail();
        $exam = Department::where('code', 'EXAM')->firstOrFail();
        $cmc = Department::where('code', 'CMC')->firstOrFail();

        DepartmentFeatureSetting::create([
            'department_id' => $accounts->id,
            'feature_key' => 'accounts.fee_collection',
            'feature_name' => 'Fee Collection',
            'is_enabled' => false,
        ]);
        DepartmentFeatureSetting::create([
            'department_id' => $exam->id,
            'feature_key' => 'exam.scheduling',
            'feature_name' => 'Exam Scheduling',
            'is_enabled' => false,
        ]);
        DepartmentFeatureSetting::create([
            'department_id' => $cmc->id,
            'feature_key' => 'cmc.internships',
            'feature_name' => 'Internships',
            'is_enabled' => false,
        ]);

        $this->actingAs($this->userWithRole('accounts_officer'))
            ->get(route('accounts.fee-collections'))
            ->assertForbidden();

        $this->actingAs($this->userWithRole('exam_cell'))
            ->get(route('exam-cell.exams'))
            ->assertForbidden();

        $this->actingAs($this->userWithRole('cmc'))
            ->get(route('cmc.internships.index'))
            ->assertForbidden();
    }

    public function test_academic_program_chair_and_hod_feature_toggles_gate_routes(): void
    {
        $academic = Department::where('code', 'ACAD')->firstOrFail();

        foreach ([
            'academic.reports' => 'Academic Reports',
            'academic.timetable' => 'Timetable Operations',
            'academic.approvals' => 'Academic Approvals',
        ] as $key => $name) {
            DepartmentFeatureSetting::create([
                'department_id' => $academic->id,
                'feature_key' => $key,
                'feature_name' => $name,
                'is_enabled' => false,
            ]);
        }

        $this->actingAs($this->userWithRole('dean_academics'))
            ->get(route('academic.transcripts.index'))
            ->assertForbidden();

        $this->actingAs($this->userWithRole('program_chair'))
            ->get(route('chair.timetable.builder'))
            ->assertForbidden();

        $this->actingAs($this->userWithRole('hod'))
            ->get(route('hod.approvals'))
            ->assertForbidden();
    }

    public function test_hostel_transport_and_library_feature_toggles_gate_admin_and_student_routes(): void
    {
        $hostel = Department::where('code', 'HOSTEL')->firstOrFail();
        $transport = Department::where('code', 'TRANSPORT')->firstOrFail();
        $library = Department::where('code', 'LIB')->firstOrFail();

        DepartmentFeatureSetting::create([
            'department_id' => $hostel->id,
            'feature_key' => 'hostel.rooms_allocations',
            'feature_name' => 'Rooms And Allocations',
            'is_enabled' => false,
        ]);
        DepartmentFeatureSetting::create([
            'department_id' => $hostel->id,
            'feature_key' => 'hostel.outpasses',
            'feature_name' => 'Outpasses',
            'is_enabled' => false,
        ]);
        DepartmentFeatureSetting::create([
            'department_id' => $transport->id,
            'feature_key' => 'transport.routes_stops',
            'feature_name' => 'Routes And Stops',
            'is_enabled' => false,
        ]);
        DepartmentFeatureSetting::create([
            'department_id' => $transport->id,
            'feature_key' => 'transport.student_assignments',
            'feature_name' => 'Student Transport Assignments',
            'is_enabled' => false,
        ]);
        DepartmentFeatureSetting::create([
            'department_id' => $library->id,
            'feature_key' => 'library.catalog',
            'feature_name' => 'Library Catalog',
            'is_enabled' => false,
        ]);

        $admin = $this->userWithRole('admin');
        $student = $this->userWithRole('student');

        $this->actingAs($admin)->get(route('admin.hostel.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.transport.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.library.index'))->assertForbidden();

        $this->actingAs($student)->get(route('student.hostel.outpass'))->assertForbidden();
        $this->actingAs($student)->get(route('student.transport.index'))->assertForbidden();
        $this->actingAs($student)->get(route('student.library.index'))->assertForbidden();
    }

    public function test_admission_workbench_uses_department_reporting_lines_for_scope(): void
    {
        $department = Department::where('code', 'ADM')->firstOrFail();
        $manager = $this->userWithRole('admission_manager');
        $counsellor = $this->userWithRole('admission_counsellor');
        $peer = $this->userWithRole('admission_counsellor');

        $managerRole = DepartmentRole::where('department_id', $department->id)->where('code', 'admission_manager')->firstOrFail();
        $counsellorRole = DepartmentRole::where('department_id', $department->id)->where('code', 'admission_counsellor')->firstOrFail();

        $managerMember = DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $managerRole->id,
            'user_id' => $manager->id,
        ]);
        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $counsellor->id,
            'reports_to_member_id' => $managerMember->id,
        ]);
        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $peer->id,
        ]);

        $visibleApplicant = $this->applicant();
        $visibleApplicant->update(['assigned_to' => $counsellor->id, 'next_action' => 'Call visible applicant']);
        $hiddenApplicant = $this->applicant();
        $hiddenApplicant->update(['assigned_to' => $peer->id, 'next_action' => 'Call hidden applicant']);

        Lead::factory()->create([
            'program_id' => $visibleApplicant->program_id,
            'assigned_to' => $counsellor->id,
            'next_action' => 'Call visible lead',
        ]);
        Lead::factory()->create([
            'program_id' => $hiddenApplicant->program_id,
            'assigned_to' => $peer->id,
            'next_action' => 'Call hidden lead',
        ]);

        $this->actingAs($manager)
            ->get(route('admission.workbench'))
            ->assertOk()
            ->assertSee('Call visible lead')
            ->assertDontSee('Call hidden lead');
    }

    public function test_admission_assignment_respects_hierarchy_permission_scope(): void
    {
        $department = Department::where('code', 'ADM')->firstOrFail();
        $manager = $this->userWithRole('admission_manager');
        $directReport = $this->userWithRole('admission_counsellor');
        $outsideCounsellor = $this->userWithRole('admission_counsellor');
        $applicant = $this->applicant();
        $lead = Lead::factory()->create(['program_id' => $applicant->program_id]);

        $managerRole = DepartmentRole::where('department_id', $department->id)->where('code', 'admission_manager')->firstOrFail();
        $counsellorRole = DepartmentRole::where('department_id', $department->id)->where('code', 'admission_counsellor')->firstOrFail();

        $managerMember = DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $managerRole->id,
            'user_id' => $manager->id,
        ]);
        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $directReport->id,
            'reports_to_member_id' => $managerMember->id,
        ]);
        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $outsideCounsellor->id,
        ]);

        $this->actingAs($manager)->post(route('admission.applicants.assign', $applicant), [
            'assigned_to' => $directReport->id,
            'priority' => 'high',
        ])->assertRedirect();

        $this->actingAs($manager)->post(route('admission.leads.workbench-assign', $lead), [
            'assigned_to' => $outsideCounsellor->id,
            'priority' => 'normal',
        ])->assertForbidden();

        $this->assertDatabaseHas('applicants', [
            'id' => $applicant->id,
            'assigned_to' => $directReport->id,
            'priority' => 'high',
        ]);
        $this->assertDatabaseMissing('leads', [
            'id' => $lead->id,
            'assigned_to' => $outsideCounsellor->id,
        ]);
    }

    public function test_admission_core_queues_use_hierarchy_scope(): void
    {
        $department = Department::where('code', 'ADM')->firstOrFail();
        $manager = $this->userWithRole('admission_manager');
        $directReport = $this->userWithRole('admission_counsellor');
        $outsideCounsellor = $this->userWithRole('admission_counsellor');
        $managerRole = DepartmentRole::where('department_id', $department->id)->where('code', 'admission_manager')->firstOrFail();
        $counsellorRole = DepartmentRole::where('department_id', $department->id)->where('code', 'admission_counsellor')->firstOrFail();

        $managerMember = DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $managerRole->id,
            'user_id' => $manager->id,
        ]);
        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $directReport->id,
            'reports_to_member_id' => $managerMember->id,
        ]);
        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $outsideCounsellor->id,
        ]);

        $visibleApplicant = $this->applicant('selected');
        $visibleApplicant->update(['assigned_to' => $directReport->id]);
        $hiddenApplicant = $this->applicant('selected');
        $hiddenApplicant->update(['assigned_to' => $outsideCounsellor->id]);

        $visibleLead = Lead::factory()->create([
            'name' => 'Visible Lead Scope',
            'program_id' => $visibleApplicant->program_id,
            'assigned_to' => $directReport->id,
        ]);
        Lead::factory()->create([
            'name' => 'Hidden Lead Scope',
            'program_id' => $hiddenApplicant->program_id,
            'assigned_to' => $outsideCounsellor->id,
        ]);

        $visibleDocument = RequiredDocument::create([
            'program_id' => $visibleApplicant->program_id,
            'name' => 'Visible Scope Document',
            'is_mandatory' => true,
            'accepted_formats' => 'pdf',
            'is_active' => true,
        ]);
        $hiddenDocument = RequiredDocument::create([
            'program_id' => $hiddenApplicant->program_id,
            'name' => 'Hidden Scope Document',
            'is_mandatory' => true,
            'accepted_formats' => 'pdf',
            'is_active' => true,
        ]);
        ApplicantDocument::create([
            'applicant_id' => $visibleApplicant->id,
            'required_document_id' => $visibleDocument->id,
            'file_path' => 'documents/visible.pdf',
            'original_name' => 'visible.pdf',
            'file_size_kb' => 100,
            'status' => 'pending',
            'uploaded_at' => now(),
            'version' => 1,
        ]);
        ApplicantDocument::create([
            'applicant_id' => $hiddenApplicant->id,
            'required_document_id' => $hiddenDocument->id,
            'file_path' => 'documents/hidden.pdf',
            'original_name' => 'hidden.pdf',
            'file_size_kb' => 100,
            'status' => 'pending',
            'uploaded_at' => now(),
            'version' => 1,
        ]);

        $visibleInstallment = AdmissionFeeInstallment::create([
            'program_id' => $visibleApplicant->program_id,
            'batch_id' => $visibleApplicant->batch_id,
            'name' => 'Visible Payment Installment',
            'amount' => 10000,
            'installment_number' => 1,
            'is_active' => true,
        ]);
        $hiddenInstallment = AdmissionFeeInstallment::create([
            'program_id' => $hiddenApplicant->program_id,
            'batch_id' => $hiddenApplicant->batch_id,
            'name' => 'Hidden Payment Installment',
            'amount' => 10000,
            'installment_number' => 1,
            'is_active' => true,
        ]);
        AdmissionPayment::create([
            'applicant_id' => $visibleApplicant->id,
            'admission_fee_installment_id' => $visibleInstallment->id,
            'amount_paid' => 10000,
            'payment_date' => now()->toDateString(),
            'payment_mode' => 'upi',
            'status' => 'pending',
            'submitted_by' => $visibleApplicant->user_id,
        ]);
        AdmissionPayment::create([
            'applicant_id' => $hiddenApplicant->id,
            'admission_fee_installment_id' => $hiddenInstallment->id,
            'amount_paid' => 10000,
            'payment_date' => now()->toDateString(),
            'payment_mode' => 'upi',
            'status' => 'pending',
            'submitted_by' => $hiddenApplicant->user_id,
        ]);

        $this->actingAs($manager)
            ->get(route('admission.applicants.index'))
            ->assertOk()
            ->assertSee($visibleApplicant->application_number)
            ->assertDontSee($hiddenApplicant->application_number);

        $this->actingAs($manager)
            ->get(route('admission.leads.index'))
            ->assertOk()
            ->assertSee($visibleLead->name)
            ->assertDontSee('Hidden Lead Scope');

        $this->actingAs($manager)
            ->get(route('admission.documents.queue'))
            ->assertOk()
            ->assertSee('Visible Scope Document')
            ->assertDontSee('Hidden Scope Document');

        $this->actingAs($manager)
            ->get(route('admission.payments.queue'))
            ->assertOk()
            ->assertSee('Visible Payment Installment')
            ->assertDontSee('Hidden Payment Installment');
    }

    public function test_department_head_can_manage_feature_settings_and_activity_is_logged(): void
    {
        $department = Department::where('code', 'ADM')->firstOrFail();
        $head = $this->userWithRole('admission_head');
        $headRole = DepartmentRole::where('department_id', $department->id)->where('code', 'admission_head')->firstOrFail();
        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $headRole->id,
            'user_id' => $head->id,
        ]);

        $this->actingAs($head)
            ->get(route('department-governance.index', ['department_id' => $department->id]))
            ->assertOk()
            ->assertSee('Department Governance');

        $this->actingAs($head)
            ->post(route('department-governance.features.update', $department), [
                'feature_key' => 'admission.gateway_payments',
                'feature_name' => 'Gateway Payments',
                'is_enabled' => 0,
            ])->assertRedirect();

        $this->assertDatabaseHas('department_feature_settings', [
            'department_id' => $department->id,
            'feature_key' => 'admission.gateway_payments',
            'is_enabled' => false,
            'updated_by' => $head->id,
        ]);
        $this->assertDatabaseHas('department_activity_logs', [
            'department_id' => $department->id,
            'actor_user_id' => $head->id,
            'action' => 'feature_setting_updated',
        ]);
    }

    public function test_registered_department_features_are_listed_and_gate_routes(): void
    {
        $department = Department::where('code', 'ADM')->firstOrFail();
        $head = $this->userWithRole('admission_head');
        $headRole = DepartmentRole::where('department_id', $department->id)->where('code', 'admission_head')->firstOrFail();
        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $headRole->id,
            'user_id' => $head->id,
        ]);

        $this->actingAs($head)
            ->get(route('department-governance.index', ['department_id' => $department->id]))
            ->assertOk()
            ->assertSee('Admission Workbench')
            ->assertSee('admission.workbench')
            ->assertSee('Default');

        $this->actingAs($head)
            ->post(route('department-governance.features.update', $department), [
                'feature_key' => 'admission.workbench',
                'feature_name' => 'Admission Workbench',
            ])->assertRedirect();

        $this->assertDatabaseHas('department_feature_settings', [
            'department_id' => $department->id,
            'feature_key' => 'admission.workbench',
            'is_enabled' => false,
            'updated_by' => $head->id,
        ]);

        $this->actingAs($head)
            ->get(route('admission.workbench'))
            ->assertForbidden();
    }

    public function test_disabled_applicant_checklist_feature_blocks_applicant_route(): void
    {
        $department = Department::where('code', 'ADM')->firstOrFail();
        DepartmentFeatureSetting::create([
            'department_id' => $department->id,
            'feature_key' => 'admission.applicant_checklist',
            'feature_name' => 'Applicant Guided Checklist',
            'is_enabled' => false,
        ]);
        $applicant = $this->applicant('draft');

        $this->actingAs($applicant->user)
            ->get(route('applicant.checklist'))
            ->assertForbidden();
    }

    public function test_disabled_gateway_feature_blocks_applicant_gateway_initiation(): void
    {
        $department = Department::where('code', 'ADM')->firstOrFail();
        DepartmentFeatureSetting::create([
            'department_id' => $department->id,
            'feature_key' => 'admission.gateway_payments',
            'feature_name' => 'Gateway Payments',
            'is_enabled' => false,
        ]);

        $applicant = $this->applicant('selected');
        $installment = AdmissionFeeInstallment::create([
            'program_id' => $applicant->program_id,
            'batch_id' => $applicant->batch_id,
            'name' => 'Blocked Gateway Fee',
            'amount' => 5000,
            'installment_number' => 1,
            'is_active' => true,
        ]);
        $payment = AdmissionPayment::create([
            'applicant_id' => $applicant->id,
            'admission_fee_installment_id' => $installment->id,
            'amount_paid' => 5000,
            'payment_date' => now()->toDateString(),
            'payment_mode' => 'upi',
            'status' => 'pending',
            'submitted_by' => $applicant->user_id,
        ]);

        $this->actingAs($applicant->user)
            ->post(route('applicant.fees.gateway.initiate', $payment))
            ->assertForbidden();
        $this->assertDatabaseMissing('department_activity_logs', [
            'department_id' => $department->id,
            'actor_user_id' => $applicant->user_id,
            'action' => 'department_feature_action',
        ]);
    }

    public function test_department_director_can_impersonate_subordinate_and_stop(): void
    {
        $department = Department::where('code', 'ADM')->firstOrFail();
        $director = $this->userWithRole('admission_head');
        $manager = $this->userWithRole('admission_manager');
        $counsellor = $this->userWithRole('admission_counsellor');
        $directorRole = DepartmentRole::where('department_id', $department->id)->where('code', 'admission_director')->firstOrFail();
        $managerRole = DepartmentRole::where('department_id', $department->id)->where('code', 'admission_manager')->firstOrFail();
        $counsellorRole = DepartmentRole::where('department_id', $department->id)->where('code', 'admission_counsellor')->firstOrFail();

        $directorMember = DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $directorRole->id,
            'user_id' => $director->id,
        ]);
        $managerMember = DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $managerRole->id,
            'user_id' => $manager->id,
            'reports_to_member_id' => $directorMember->id,
        ]);
        $counsellorMember = DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $counsellor->id,
            'reports_to_member_id' => $managerMember->id,
        ]);

        $this->actingAs($director)
            ->post(route('department-governance.impersonation.start', $managerMember), [
                'reason' => 'Support request',
            ])->assertRedirect();

        $this->assertAuthenticatedAs($manager);
        $this->assertSame($director->id, session('impersonation.original_user_id'));
        $this->assertDatabaseHas('department_impersonation_sessions', [
            'department_id' => $department->id,
            'actor_user_id' => $director->id,
            'target_user_id' => $manager->id,
            'reason' => 'Support request',
        ]);
        $this->assertDatabaseHas('department_activity_logs', [
            'department_id' => $department->id,
            'actor_user_id' => $director->id,
            'target_user_id' => $manager->id,
            'action' => 'impersonation_started',
        ]);

        $lead = Lead::factory()->create([
            'program_id' => Program::factory()->create(['is_active' => true])->id,
            'assigned_to' => $manager->id,
        ]);

        $this->post(route('admission.leads.workbench-assign', $lead), [
            'assigned_to' => $counsellor->id,
            'priority' => 'high',
            'next_action' => 'Impersonated follow-up allocation',
        ])->assertRedirect();

        $assignmentLog = DepartmentActivityLog::where('department_id', $department->id)
            ->where('actor_user_id', $director->id)
            ->where('target_user_id', $counsellor->id)
            ->where('action', 'lead_assigned')
            ->latest()
            ->firstOrFail();
        $this->assertSame($manager->id, $assignmentLog->metadata['impersonation']['impersonated_user_id'] ?? null);
        $this->assertSame($director->id, $assignmentLog->metadata['impersonation']['original_user_id'] ?? null);

        $featureActionLog = DepartmentActivityLog::where('department_id', $department->id)
            ->where('actor_user_id', $director->id)
            ->where('target_user_id', $manager->id)
            ->where('action', 'department_feature_action')
            ->latest()
            ->firstOrFail();
        $this->assertSame($manager->id, $featureActionLog->metadata['impersonation']['impersonated_user_id'] ?? null);
        $this->assertSame('admission.leads.workbench-assign', $featureActionLog->metadata['route'] ?? null);

        $this->post(route('department-governance.impersonation.start', $counsellorMember), [
            'reason' => 'Nested support request',
        ])->assertForbidden();
        $this->assertAuthenticatedAs($manager);
        $this->assertSame($director->id, session('impersonation.original_user_id'));

        $this->post(route('department-governance.impersonation.stop'))->assertRedirect();
        $this->assertAuthenticatedAs($director);
        $this->assertNull(session('impersonation'));
        $this->assertNotNull(DepartmentImpersonationSession::first()->fresh()->ended_at);
    }
}
