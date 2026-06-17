<?php

namespace Tests\Feature;

use App\Models\AdmissionFeeInstallment;
use App\Models\AdmissionPayment;
use App\Models\AdmissionPaymentGatewayEvent;
use App\Models\Applicant;
use App\Models\Department;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdmissionPaymentVerificationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function payment(string $status = 'pending'): AdmissionPayment
    {
        $applicant = Applicant::factory()->create(['status' => 'shortlisted']);
        $installment = AdmissionFeeInstallment::create([
            'program_id' => $applicant->program_id,
            'batch_id' => $applicant->batch_id,
            'name' => 'Admission Confirmation Fee',
            'amount' => 15000,
            'installment_number' => 1,
            'due_date' => now()->addDays(5)->toDateString(),
            'is_active' => true,
        ]);

        return AdmissionPayment::create([
            'applicant_id' => $applicant->id,
            'admission_fee_installment_id' => $installment->id,
            'amount_paid' => 15000,
            'payment_date' => now()->toDateString(),
            'payment_mode' => 'cash',
            'transaction_reference' => 'ADM-PAY-' . uniqid(),
            'status' => $status,
            'verified_by' => in_array($status, ['verified', 'rejected'], true) ? $this->admin()->id : null,
            'verified_at' => in_array($status, ['verified', 'rejected'], true) ? now() : null,
            'verification_notes' => $status === 'pending' ? null : 'Existing final decision.',
            'submitted_by' => $applicant->user_id,
        ]);
    }

    private function applicantUser(Applicant $applicant): User
    {
        Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);
        $applicant->user->assignRole('applicant');

        return $applicant->user;
    }

    private function paymentPayload(array $overrides = []): array
    {
        return array_merge([
            'amount_paid' => 15000,
            'payment_date' => now()->toDateString(),
            'payment_mode' => 'upi',
            'transaction_reference' => 'UPI-ADM-' . uniqid(),
            'bank_name' => 'Demo Bank',
        ], $overrides);
    }

    public function test_verified_admission_payment_cannot_be_rejected_or_reverified(): void
    {
        $admin = $this->admin();
        $payment = $this->payment('verified');
        $verifiedAt = $payment->verified_at;

        $this->actingAs($admin)
            ->post(route('admission.payments.reject', $payment), [
                'verification_notes' => 'Trying to reverse verified payment.',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Only pending admission payments can be rejected.');

        $payment->refresh();
        $this->assertSame('verified', $payment->status);
        $this->assertSame('Existing final decision.', $payment->verification_notes);
        $this->assertTrue($payment->verified_at->equalTo($verifiedAt));

        $this->actingAs($admin)
            ->post(route('admission.payments.verify', $payment), [
                'verification_notes' => 'Trying to reverify payment.',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Only pending admission payments can be verified.');

        $this->assertSame('verified', $payment->fresh()->status);
    }

    public function test_rejected_admission_payment_cannot_be_verified_later(): void
    {
        $admin = $this->admin();
        $payment = $this->payment('rejected');

        $this->actingAs($admin)
            ->post(route('admission.payments.verify', $payment), [
                'verification_notes' => 'Trying to revive rejected payment.',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Only pending admission payments can be verified.');

        $payment->refresh();
        $this->assertSame('rejected', $payment->status);
        $this->assertSame('Existing final decision.', $payment->verification_notes);
    }

    public function test_reject_pending_admission_payment_uses_staff_note_in_notification(): void
    {
        $admin = $this->admin();
        $payment = $this->payment('pending');
        $note = 'UPI reference does not match bank statement.';

        $this->actingAs($admin)
            ->post(route('admission.payments.reject', $payment), [
                'verification_notes' => $note,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Payment rejected with notes.');

        $payment->refresh();
        $this->assertSame('rejected', $payment->status);
        $this->assertSame($note, $payment->verification_notes);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $payment->applicant->user_id,
            'type' => 'error',
        ]);
        $this->assertStringContainsString($note, Notification::where('user_id', $payment->applicant->user_id)->latest()->first()->message);
    }

    public function test_applicant_cannot_submit_duplicate_pending_admission_payment_proof(): void
    {
        $payment = $this->payment('pending');
        $applicant = $payment->applicant;

        $this->actingAs($this->applicantUser($applicant))
            ->post(route('applicant.fees.store', $payment->installment), $this->paymentPayload())
            ->assertRedirect()
            ->assertSessionHas('error', 'A payment proof for this fee installment is already pending verification.');

        $this->assertSame(1, AdmissionPayment::where('applicant_id', $applicant->id)
            ->where('admission_fee_installment_id', $payment->admission_fee_installment_id)
            ->count());
    }

    public function test_applicant_cannot_submit_duplicate_verified_admission_payment_proof(): void
    {
        $payment = $this->payment('verified');
        $applicant = $payment->applicant;
        $verifiedAt = $payment->verified_at;

        $this->actingAs($this->applicantUser($applicant))
            ->post(route('applicant.fees.store', $payment->installment), $this->paymentPayload([
                'transaction_reference' => 'UPI-DUPLICATE-VERIFIED',
            ]))
            ->assertRedirect()
            ->assertSessionHas('error', 'This fee installment has already been verified. Contact admissions for corrections.');

        $payment->refresh();
        $this->assertSame('verified', $payment->status);
        $this->assertTrue($payment->verified_at->equalTo($verifiedAt));
        $this->assertSame(1, AdmissionPayment::where('applicant_id', $applicant->id)
            ->where('admission_fee_installment_id', $payment->admission_fee_installment_id)
            ->count());
    }

    public function test_rejected_admission_payment_proof_can_be_resubmitted_as_new_pending_proof(): void
    {
        $payment = $this->payment('rejected');
        $applicant = $payment->applicant;

        $this->actingAs($this->applicantUser($applicant))
            ->post(route('applicant.fees.store', $payment->installment), $this->paymentPayload([
                'transaction_reference' => 'UPI-CORRECTED-ADM',
            ]))
            ->assertRedirect(route('applicant.fees.index'))
            ->assertSessionHas('success', 'Payment submitted successfully. It will be verified shortly.');

        $this->assertSame(2, AdmissionPayment::where('applicant_id', $applicant->id)
            ->where('admission_fee_installment_id', $payment->admission_fee_installment_id)
            ->count());

        $this->assertDatabaseHas('admission_payments', [
            'applicant_id' => $applicant->id,
            'admission_fee_installment_id' => $payment->admission_fee_installment_id,
            'transaction_reference' => 'UPI-CORRECTED-ADM',
            'status' => 'pending',
        ]);
    }

    public function test_applicant_cannot_submit_payment_against_unavailable_installment(): void
    {
        $applicant = Applicant::factory()->create(['status' => 'selected']);
        $otherApplicant = Applicant::factory()->create(['status' => 'selected']);
        $installment = AdmissionFeeInstallment::create([
            'program_id' => $otherApplicant->program_id,
            'batch_id' => $otherApplicant->batch_id,
            'name' => 'Other Program Fee',
            'amount' => 15000,
            'installment_number' => 1,
            'due_date' => now()->addDays(5)->toDateString(),
            'is_active' => true,
        ]);

        $this->actingAs($this->applicantUser($applicant))
            ->post(route('applicant.fees.store', $installment), $this->paymentPayload())
            ->assertRedirect()
            ->assertSessionHas('error', 'This fee installment is not available for your application.');

        $this->assertDatabaseMissing('admission_payments', [
            'applicant_id' => $applicant->id,
            'admission_fee_installment_id' => $installment->id,
        ]);
    }

    public function test_applicant_cannot_initiate_gateway_order_for_finalized_admission_payment(): void
    {
        Department::firstOrCreate(['code' => 'ADM'], ['name' => 'Admissions']);
        $payment = $this->payment('verified');
        $applicant = $payment->applicant;

        $this->actingAs($this->applicantUser($applicant))
            ->post(route('applicant.fees.gateway.initiate', $payment))
            ->assertRedirect()
            ->assertSessionHas('error', 'Only pending admission payments can be paid online.');

        $this->assertNull($payment->fresh()->gateway_order_id);
    }

    public function test_gateway_webhook_does_not_mutate_finalized_admission_payment_records(): void
    {
        $verifiedPayment = $this->payment('verified');
        $verifiedPayment->update([
            'provider' => 'razorpay_mock',
            'gateway_order_id' => 'order_verified_final',
            'gateway_status' => 'created',
        ]);
        $verifiedAt = $verifiedPayment->verified_at;

        $this->postJson(route('admission.gateway.webhook'), [
            'provider' => 'razorpay_mock',
            'event_id' => 'evt_verified_final_capture',
            'event' => 'payment.captured',
            'order_id' => 'order_verified_final',
            'payment_id' => 'pay_should_not_attach',
            'status' => 'captured',
        ])->assertOk();

        $verifiedPayment->refresh();
        $this->assertSame('verified', $verifiedPayment->status);
        $this->assertSame('created', $verifiedPayment->gateway_status);
        $this->assertNull($verifiedPayment->gateway_payment_id);
        $this->assertTrue($verifiedPayment->verified_at->equalTo($verifiedAt));

        $rejectedPayment = $this->payment('rejected');
        $rejectedPayment->update([
            'provider' => 'razorpay_mock',
            'gateway_order_id' => 'order_rejected_final',
            'gateway_status' => 'created',
        ]);

        $this->postJson(route('admission.gateway.webhook'), [
            'provider' => 'razorpay_mock',
            'event_id' => 'evt_rejected_final_capture',
            'event' => 'payment.captured',
            'order_id' => 'order_rejected_final',
            'payment_id' => 'pay_rejected_should_not_attach',
            'status' => 'captured',
        ])->assertOk();

        $rejectedPayment->refresh();
        $this->assertSame('rejected', $rejectedPayment->status);
        $this->assertSame('created', $rejectedPayment->gateway_status);
        $this->assertNull($rejectedPayment->gateway_payment_id);
        $this->assertNotNull(AdmissionPaymentGatewayEvent::where('event_id', 'evt_verified_final_capture')->firstOrFail()->processed_at);
        $this->assertNotNull(AdmissionPaymentGatewayEvent::where('event_id', 'evt_rejected_final_capture')->firstOrFail()->processed_at);
    }
}
