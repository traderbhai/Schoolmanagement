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

    public function test_applicant_cannot_submit_duplicate_pending_admission_payment_reference_across_installments(): void
    {
        $payment = $this->payment('pending');
        $applicant = $payment->applicant;
        $secondInstallment = AdmissionFeeInstallment::create([
            'program_id' => $applicant->program_id,
            'batch_id' => $applicant->batch_id,
            'name' => 'Admission Second Installment',
            'amount' => 12000,
            'installment_number' => 2,
            'due_date' => now()->addDays(15)->toDateString(),
            'is_active' => true,
        ]);

        $payment->update(['transaction_reference' => 'UPI-PENDING-ADM-DUP']);

        $this->actingAs($this->applicantUser($applicant))
            ->post(route('applicant.fees.store', $secondInstallment), $this->paymentPayload([
                'amount_paid' => 12000,
                'transaction_reference' => 'UPI-PENDING-ADM-DUP',
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('transaction_reference');

        $this->assertSame(1, AdmissionPayment::where('transaction_reference', 'UPI-PENDING-ADM-DUP')->count());
        $this->assertDatabaseMissing('admission_payments', [
            'applicant_id' => $applicant->id,
            'admission_fee_installment_id' => $secondInstallment->id,
            'transaction_reference' => 'UPI-PENDING-ADM-DUP',
        ]);
    }

    public function test_applicant_pending_admission_payment_reference_duplicate_check_is_case_insensitive(): void
    {
        $payment = $this->payment('pending');
        $applicant = $payment->applicant;
        $secondInstallment = AdmissionFeeInstallment::create([
            'program_id' => $applicant->program_id,
            'batch_id' => $applicant->batch_id,
            'name' => 'Admission Case Check Installment',
            'amount' => 12000,
            'installment_number' => 2,
            'due_date' => now()->addDays(15)->toDateString(),
            'is_active' => true,
        ]);

        $payment->update(['transaction_reference' => 'UPI-PENDING-ADM-CASE']);

        $this->actingAs($this->applicantUser($applicant))
            ->post(route('applicant.fees.store', $secondInstallment), $this->paymentPayload([
                'amount_paid' => 12000,
                'transaction_reference' => 'upi-pending-adm-case',
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('transaction_reference');

        $this->assertSame(1, AdmissionPayment::whereRaw('LOWER(transaction_reference) = ?', ['upi-pending-adm-case'])->count());
    }

    public function test_applicant_cannot_submit_admission_payment_over_installment_or_with_verified_reference(): void
    {
        $verified = $this->payment('verified');
        $applicant = $verified->applicant;
        $installment = $verified->installment;

        $this->actingAs($this->applicantUser($applicant))
            ->post(route('applicant.fees.store', $installment), $this->paymentPayload([
                'transaction_reference' => 'UPI-OVERPAY-ADM',
                'amount_paid' => 15001,
            ]))
            ->assertRedirect()
            ->assertSessionHas('error', 'This fee installment has already been verified. Contact admissions for corrections.');

        $rejected = $this->payment('rejected');
        $rejectedApplicant = $rejected->applicant;
        $rejectedInstallment = $rejected->installment;

        $this->actingAs($this->applicantUser($rejectedApplicant))
            ->post(route('applicant.fees.store', $rejectedInstallment), $this->paymentPayload([
                'transaction_reference' => 'UPI-OVERPAY-ADM',
                'amount_paid' => 15001,
            ]))
            ->assertSessionHasErrors('amount_paid');

        $this->actingAs($this->applicantUser($rejectedApplicant))
            ->post(route('applicant.fees.store', $rejectedInstallment), $this->paymentPayload([
                'transaction_reference' => $verified->transaction_reference,
                'amount_paid' => 15000,
            ]))
            ->assertSessionHasErrors('transaction_reference');

        $this->assertDatabaseMissing('admission_payments', [
            'applicant_id' => $rejectedApplicant->id,
            'transaction_reference' => $verified->transaction_reference,
            'status' => 'pending',
        ]);
    }

    public function test_applicant_verified_admission_payment_reference_duplicate_check_is_case_insensitive(): void
    {
        $verified = $this->payment('verified');
        $verified->update(['transaction_reference' => 'UPI-VERIFIED-ADM-CASE']);
        $rejected = $this->payment('rejected');

        $this->actingAs($this->applicantUser($rejected->applicant))
            ->post(route('applicant.fees.store', $rejected->installment), $this->paymentPayload([
                'transaction_reference' => 'upi-verified-adm-case',
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('transaction_reference');

        $this->assertSame(1, AdmissionPayment::whereRaw('LOWER(transaction_reference) = ?', ['upi-verified-adm-case'])->count());
    }

    public function test_admin_cannot_verify_stale_admission_payment_over_installment_or_duplicate_reference(): void
    {
        $admin = $this->admin();
        $verified = $this->payment('verified');

        $overpay = $this->payment('pending');
        $overpay->update(['amount_paid' => 15001]);

        $this->actingAs($admin)
            ->post(route('admission.payments.verify', $overpay))
            ->assertSessionHasErrors('amount_paid');

        $overpay->refresh();
        $this->assertSame('pending', $overpay->status);
        $this->assertNull($overpay->verified_at);

        $duplicate = $this->payment('pending');
        $duplicate->update(['transaction_reference' => $verified->transaction_reference]);

        $this->actingAs($admin)
            ->post(route('admission.payments.verify', $duplicate))
            ->assertSessionHasErrors('transaction_reference');

        $duplicate->refresh();
        $this->assertSame('pending', $duplicate->status);
        $this->assertNull($duplicate->verified_at);
    }

    public function test_admin_verify_admission_payment_duplicate_reference_check_is_case_insensitive(): void
    {
        $admin = $this->admin();
        $verified = $this->payment('verified');
        $verified->update(['transaction_reference' => 'UPI-ADMIN-ADM-CASE']);
        $duplicate = $this->payment('pending');
        $duplicate->update(['transaction_reference' => 'upi-admin-adm-case']);

        $this->actingAs($admin)
            ->post(route('admission.payments.verify', $duplicate))
            ->assertSessionHasErrors('transaction_reference');

        $duplicate->refresh();
        $this->assertSame('pending', $duplicate->status);
        $this->assertNull($duplicate->verified_at);
        $this->assertSame(1, AdmissionPayment::whereRaw('LOWER(transaction_reference) = ?', ['upi-admin-adm-case'])->where('status', 'verified')->count());
    }

    public function test_admin_cannot_verify_stale_admission_payment_after_applicant_admission_window_closed(): void
    {
        $admin = $this->admin();

        foreach (['rejected', 'withdrawn', 'enrolled'] as $status) {
            $payment = $this->payment('pending');
            $payment->applicant->update(['status' => $status]);

            $this->actingAs($admin)
                ->post(route('admission.payments.verify', $payment), [
                    'verification_notes' => 'Trying to verify stale admission payment.',
                ])
                ->assertRedirect()
                ->assertSessionHas('error', 'Admission payment verification is closed for the applicant current status. Use the audited correction or refund workflow instead.');

            $payment->refresh();
            $this->assertSame('pending', $payment->status);
            $this->assertNull($payment->verified_at);
            $this->assertNull($payment->verified_by);
        }
    }

    public function test_admin_cannot_verify_stale_admission_payment_for_inactive_or_mismatched_installment(): void
    {
        $admin = $this->admin();

        $inactive = $this->payment('pending');
        $inactive->installment->update(['is_active' => false]);

        $this->actingAs($admin)
            ->post(route('admission.payments.verify', $inactive), [
                'verification_notes' => 'Trying to verify inactive installment.',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Admission payment verification is closed because the linked fee installment is no longer active.');

        $inactive->refresh();
        $this->assertSame('pending', $inactive->status);
        $this->assertNull($inactive->verified_at);

        $mismatched = $this->payment('pending');
        $mismatched->installment->update(['program_id' => Applicant::factory()->create()->program_id]);

        $this->actingAs($admin)
            ->post(route('admission.payments.verify', $mismatched), [
                'verification_notes' => 'Trying to verify wrong program installment.',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Admission payment verification is closed because the linked fee installment is not available for the applicant program.');

        $mismatched->refresh();
        $this->assertSame('pending', $mismatched->status);
        $this->assertNull($mismatched->verified_at);
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

    public function test_enrolled_applicant_cannot_initiate_gateway_order_for_stale_pending_payment(): void
    {
        Department::firstOrCreate(['code' => 'ADM'], ['name' => 'Admissions']);
        $payment = $this->payment('pending');
        $applicant = $payment->applicant;
        $applicant->update(['status' => 'enrolled']);

        $this->actingAs($this->applicantUser($applicant))
            ->get(route('applicant.fees.show', $payment))
            ->assertOk()
            ->assertSee('Online payment actions are closed for your current application status.')
            ->assertDontSee('Pay Online / Create Gateway Order');

        $this->actingAs($this->applicantUser($applicant))
            ->post(route('applicant.fees.gateway.initiate', $payment))
            ->assertRedirect()
            ->assertSessionHas('error', 'Online payment is available only while your admission fee payment window is active.');

        $payment->refresh();
        $this->assertSame('pending', $payment->status);
        $this->assertNull($payment->gateway_order_id);
        $this->assertNull($payment->gateway_payment_id);
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
