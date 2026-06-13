<?php

namespace Tests\Feature;

use App\Models\AdmissionFeeInstallment;
use App\Models\AdmissionPayment;
use App\Models\Applicant;
use App\Models\Batch;
use App\Models\EnrollmentConfirmation;
use App\Models\OfferLetter;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApplicantStatusGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private function makeApplicant(string $status = 'draft'): Applicant
    {
        Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);

        $program = Program::factory()->create(['name' => 'Status Guidance MBA']);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'name' => '2026 Intake']);
        $user = User::factory()->create(['name' => 'Status Applicant']);
        $user->assignRole('applicant');

        return Applicant::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => $status,
        ]);
    }

    public function test_draft_applicant_status_prompts_registration_fee_details(): void
    {
        $applicant = $this->makeApplicant('draft');

        $this->actingAs($applicant->user)
            ->get(route('applicant.status'))
            ->assertStatus(200)
            ->assertSee('Complete your application')
            ->assertSee('Submit Fee Details')
            ->assertSee(route('applicant.registration-fee.show'), false)
            ->assertSee('Status Guidance MBA - 2026 Intake')
            ->assertDontSee('â', false);
    }

    public function test_shortlisted_applicant_with_pending_payment_sees_payment_verification_guidance(): void
    {
        $applicant = $this->makeApplicant('shortlisted');
        $installment = AdmissionFeeInstallment::create([
            'program_id' => $applicant->program_id,
            'batch_id' => $applicant->batch_id,
            'name' => 'Admission Confirmation Fee',
            'amount' => 25000,
            'installment_number' => 1,
            'is_active' => true,
        ]);
        AdmissionPayment::create([
            'applicant_id' => $applicant->id,
            'admission_fee_installment_id' => $installment->id,
            'amount_paid' => 25000,
            'payment_date' => now()->toDateString(),
            'payment_mode' => 'upi',
            'transaction_reference' => 'UPI-STATUS-001',
            'status' => 'pending',
            'submitted_by' => $applicant->user_id,
        ]);

        $this->actingAs($applicant->user)
            ->get(route('applicant.status'))
            ->assertStatus(200)
            ->assertSee('Payment submitted for verification')
            ->assertSee('View Payment Status')
            ->assertSee(route('applicant.fees.index'), false);
    }

    public function test_selected_applicant_with_issued_offer_sees_offer_review_action(): void
    {
        $applicant = $this->makeApplicant('selected');
        $offer = OfferLetter::create([
            'applicant_id' => $applicant->id,
            'program_id' => $applicant->program_id,
            'batch_id' => $applicant->batch_id,
            'status' => 'issued',
            'acceptance_deadline' => now()->addDays(10)->toDateString(),
            'issued_at' => now(),
        ]);

        $this->actingAs($applicant->user)
            ->get(route('applicant.status'))
            ->assertStatus(200)
            ->assertSee('Complete your offer and enrollment steps')
            ->assertSee('Review Offer Letter')
            ->assertSee(route('applicant.offer-letters.show', $offer), false);
    }

    public function test_enrolled_applicant_status_shows_enrollment_identifiers(): void
    {
        $applicant = $this->makeApplicant('selected');
        $officer = User::factory()->create();

        EnrollmentConfirmation::create([
            'applicant_id' => $applicant->id,
            'confirmed_by' => $officer->id,
            'confirmed_at' => now(),
            'enrollment_number' => 'ENR-2026-0001',
            'roll_number' => 'MBA-2026-001',
            'batch_id' => $applicant->batch_id,
            'status' => 'completed',
        ]);

        $this->actingAs($applicant->user)
            ->get(route('applicant.status'))
            ->assertStatus(200)
            ->assertSee('Enrollment completed')
            ->assertSee('ENR-2026-0001')
            ->assertSee('MBA-2026-001');
    }
}
