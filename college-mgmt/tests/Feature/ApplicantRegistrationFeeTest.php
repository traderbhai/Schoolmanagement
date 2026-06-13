<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Batch;
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
}
