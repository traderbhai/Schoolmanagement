<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Batch;
use App\Models\Department;
use App\Models\DepartmentMember;
use App\Models\DepartmentRole;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApplicantRegistrationFeeTest extends TestCase
{
    use RefreshDatabase;

    private function makeApplicant(string $status = 'draft'): Applicant
    {
        Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);

        $program = Program::factory()->create(['name' => 'Registration Fee MBA']);
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $user = User::factory()->create(['name' => 'Fee Applicant']);
        $user->assignRole('applicant');

        return Applicant::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => $status,
            'registration_fee_amount' => null,
            'registration_fee_paid_at' => null,
            'registration_fee_receipt' => null,
        ]);
    }

    public function test_applicant_can_open_registration_fee_form_from_own_portal(): void
    {
        $applicant = $this->makeApplicant();

        $this->actingAs($applicant->user)
            ->get(route('applicant.registration-fee.show'))
            ->assertStatus(200)
            ->assertSee('Submit Registration Fee Details')
            ->assertSee('Registration Fee MBA')
            ->assertDontSee('Record Registration Fee Payment');
    }

    public function test_applicant_can_save_registration_fee_details(): void
    {
        $applicant = $this->makeApplicant();

        $this->actingAs($applicant->user)
            ->post(route('applicant.registration-fee.store'), [
                'amount_paid' => 1500,
                'payment_method' => 'online',
                'reference_number' => 'REG-FEE-UTR-001',
            ])
            ->assertRedirect(route('applicant.dashboard'));

        $this->assertDatabaseHas('applicants', [
            'id' => $applicant->id,
            'registration_fee_amount' => 1500,
            'registration_fee_receipt' => 'REG-FEE-UTR-001',
        ]);
        $this->assertNotNull($applicant->fresh()->registration_fee_paid_at);
    }

    public function test_applicant_registration_fee_reference_must_be_unique_case_insensitively(): void
    {
        $existing = $this->makeApplicant('submitted');
        $existing->update([
            'registration_fee_amount' => 1500,
            'registration_fee_paid_at' => now(),
            'registration_fee_receipt' => 'REG-FEE-CASE-001',
        ]);
        $applicant = $this->makeApplicant();

        $this->actingAs($applicant->user)
            ->post(route('applicant.registration-fee.store'), [
                'amount_paid' => 1500,
                'payment_method' => 'online',
                'reference_number' => 'reg-fee-case-001',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('reference_number');

        $applicant->refresh();
        $this->assertNull($applicant->registration_fee_paid_at);
        $this->assertNull($applicant->registration_fee_receipt);
        $this->assertSame(1, Applicant::whereRaw('LOWER(registration_fee_receipt) = ?', ['reg-fee-case-001'])->count());
    }

    public function test_applicant_dashboard_uses_applicant_fee_route(): void
    {
        $applicant = $this->makeApplicant();

        $this->actingAs($applicant->user)
            ->get(route('applicant.dashboard'))
            ->assertStatus(200)
            ->assertSee(route('applicant.registration-fee.show'), false)
            ->assertDontSee(route('admission.applicants.registration-fee.show', $applicant), false)
            ->assertSee('Submit Fee Details');
    }

    public function test_submitted_applicant_dashboard_does_not_show_dead_end_fee_submission_cta(): void
    {
        $applicant = $this->makeApplicant('submitted');

        $this->actingAs($applicant->user)
            ->get(route('applicant.dashboard'))
            ->assertStatus(200)
            ->assertSee('Registration Fee Not Recorded')
            ->assertSee('Your application has already been submitted')
            ->assertDontSee('Your application cannot be submitted until the registration fee details are saved.')
            ->assertDontSee('Submit Fee Details')
            ->assertDontSee('Submit Details');
    }

    public function test_admission_staff_can_record_registration_fee_without_invalid_payment_columns(): void
    {
        Role::firstOrCreate(['name' => 'admission_officer', 'guard_name' => 'web']);
        $applicant = $this->makeApplicant();
        $officer = User::factory()->create();
        $officer->assignRole('admission_officer');

        $this->actingAs($officer)
            ->post(route('admission.applicants.registration-fee.store', $applicant), [
                'amount_paid' => 2500,
                'payment_method' => 'bank_transfer',
                'reference_number' => 'STAFF-REG-FEE-001',
            ])
            ->assertRedirect(route('admission.applicants.show', $applicant));

        $this->assertDatabaseHas('applicants', [
            'id' => $applicant->id,
            'registration_fee_amount' => 2500,
            'registration_fee_receipt' => 'STAFF-REG-FEE-001',
        ]);
    }

    public function test_admission_staff_registration_fee_reference_must_be_unique_case_insensitively(): void
    {
        Role::firstOrCreate(['name' => 'admission_officer', 'guard_name' => 'web']);
        $existing = $this->makeApplicant('submitted');
        $existing->update([
            'registration_fee_amount' => 2500,
            'registration_fee_paid_at' => now(),
            'registration_fee_receipt' => 'STAFF-REG-FEE-CASE',
        ]);
        $applicant = $this->makeApplicant();
        $officer = User::factory()->create();
        $officer->assignRole('admission_officer');

        $this->actingAs($officer)
            ->post(route('admission.applicants.registration-fee.store', $applicant), [
                'amount_paid' => 2500,
                'payment_method' => 'bank_transfer',
                'reference_number' => 'staff-reg-fee-case',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('reference_number');

        $applicant->refresh();
        $this->assertNull($applicant->registration_fee_paid_at);
        $this->assertNull($applicant->registration_fee_receipt);
        $this->assertSame(1, Applicant::whereRaw('LOWER(registration_fee_receipt) = ?', ['staff-reg-fee-case'])->count());
    }

    public function test_admission_staff_cannot_record_registration_fee_for_final_state_applicant(): void
    {
        Role::firstOrCreate(['name' => 'admission_officer', 'guard_name' => 'web']);
        $applicant = $this->makeApplicant('rejected');
        $officer = User::factory()->create();
        $officer->assignRole('admission_officer');

        $this->actingAs($officer)
            ->get(route('admission.applicants.registration-fee.show', $applicant))
            ->assertOk()
            ->assertSee('Registration fee recording is locked')
            ->assertDontSee('Record Registration Fee Payment');

        $this->actingAs($officer)
            ->post(route('admission.applicants.registration-fee.store', $applicant), [
                'amount_paid' => 2500,
                'payment_method' => 'bank_transfer',
                'reference_number' => 'FINAL-STATE-REG-FEE',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Registration fee cannot be recorded because this application is already in a final admission state.');

        $applicant->refresh();
        $this->assertNull($applicant->registration_fee_paid_at);
        $this->assertNull($applicant->registration_fee_receipt);
    }

    public function test_admission_staff_registration_fee_routes_respect_hierarchy_scope(): void
    {
        foreach (['admission_manager', 'admission_counsellor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $department = Department::firstOrCreate(
            ['code' => 'ADM'],
            ['name' => 'Admissions', 'is_active' => true]
        );
        $managerRole = DepartmentRole::firstOrCreate(
            ['department_id' => $department->id, 'code' => 'admission_manager'],
            ['name' => 'Admission Manager', 'level' => 30, 'can_manage_lower_levels' => true, 'can_view_team_data' => true, 'is_active' => true]
        );
        $counsellorRole = DepartmentRole::firstOrCreate(
            ['department_id' => $department->id, 'code' => 'admission_counsellor'],
            ['name' => 'Admission Counsellor', 'level' => 50, 'can_manage_lower_levels' => false, 'can_view_team_data' => false, 'is_active' => true]
        );

        $manager = User::factory()->create();
        $manager->assignRole('admission_manager');
        $directReport = User::factory()->create();
        $directReport->assignRole('admission_counsellor');
        $outsideCounsellor = User::factory()->create();
        $outsideCounsellor->assignRole('admission_counsellor');

        $managerMember = DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $managerRole->id,
            'user_id' => $manager->id,
            'is_active' => true,
        ]);
        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $directReport->id,
            'reports_to_member_id' => $managerMember->id,
            'is_active' => true,
        ]);
        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $outsideCounsellor->id,
            'is_active' => true,
        ]);

        $visibleApplicant = $this->makeApplicant();
        $visibleApplicant->update(['assigned_to' => $directReport->id]);
        $hiddenApplicant = $this->makeApplicant();
        $hiddenApplicant->update(['assigned_to' => $outsideCounsellor->id]);

        $this->actingAs($manager)
            ->get(route('admission.applicants.registration-fee.show', $visibleApplicant))
            ->assertOk();

        $this->actingAs($manager)
            ->get(route('admission.applicants.registration-fee.show', $hiddenApplicant))
            ->assertForbidden();

        $this->actingAs($manager)
            ->post(route('admission.applicants.registration-fee.store', $hiddenApplicant), [
                'amount_paid' => 2500,
                'payment_method' => 'bank_transfer',
                'reference_number' => 'HIDDEN-SCOPE-REG-FEE',
            ])
            ->assertForbidden();

        $this->assertNull($hiddenApplicant->fresh()->registration_fee_paid_at);
    }
}
