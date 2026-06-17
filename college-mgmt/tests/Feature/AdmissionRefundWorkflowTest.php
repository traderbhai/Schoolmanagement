<?php

namespace Tests\Feature;

use App\Models\AdmissionFeeInstallment;
use App\Models\AdmissionPayment;
use App\Models\Applicant;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdmissionRefundWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function admissionUser(): User
    {
        Role::firstOrCreate(['name' => 'admission_head', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admission_head');

        return $user;
    }

    private function applicant(): Applicant
    {
        return Applicant::factory()->create(['status' => 'withdrawn']);
    }

    private function payment(Applicant $applicant, string $status = 'verified', float $amount = 10000): AdmissionPayment
    {
        $installment = AdmissionFeeInstallment::create([
            'program_id' => $applicant->program_id,
            'batch_id' => $applicant->batch_id,
            'name' => 'Admission Fee',
            'amount' => $amount,
            'installment_number' => 1,
            'due_date' => now()->addDays(7)->toDateString(),
            'is_active' => true,
        ]);

        return AdmissionPayment::create([
            'applicant_id' => $applicant->id,
            'admission_fee_installment_id' => $installment->id,
            'amount_paid' => $amount,
            'payment_date' => now()->toDateString(),
            'payment_mode' => 'cash',
            'transaction_reference' => 'PAY-' . uniqid(),
            'status' => $status,
            'verified_by' => $status === 'verified' ? $this->admissionUser()->id : null,
            'verified_at' => $status === 'verified' ? now() : null,
            'submitted_by' => $applicant->user_id,
        ]);
    }

    private function refundPayload(array $overrides = []): array
    {
        return array_merge([
            'requested_amount' => 5000,
            'reason' => 'withdrawal',
            'reason_detail' => 'Candidate withdrew before admission closure.',
            'bank_name' => 'Demo Bank',
            'account_number' => '1234567890',
            'ifsc_code' => 'DEMO0001234',
            'account_holder_name' => 'Demo Applicant',
        ], $overrides);
    }

    public function test_refund_request_requires_verified_payment_balance_for_applicant(): void
    {
        $staff = $this->admissionUser();
        $applicant = $this->applicant();
        $otherApplicant = $this->applicant();
        $pendingPayment = $this->payment($applicant, 'pending', 9000);
        $otherPayment = $this->payment($otherApplicant, 'verified', 9000);

        $this->actingAs($staff)
            ->post(route('admission.refunds.store', $applicant), $this->refundPayload([
                'admission_payment_id' => $otherPayment->id,
            ]))
            ->assertSessionHasErrors('requested_amount');

        $this->actingAs($staff)
            ->post(route('admission.refunds.store', $applicant), $this->refundPayload([
                'admission_payment_id' => $pendingPayment->id,
            ]))
            ->assertSessionHasErrors('requested_amount');

        $this->actingAs($staff)
            ->post(route('admission.refunds.store', $applicant), $this->refundPayload([
                'requested_amount' => 1000,
            ]))
            ->assertSessionHasErrors('requested_amount');

        $this->assertSame(0, RefundRequest::where('applicant_id', $applicant->id)->count());
    }

    public function test_active_duplicate_refund_request_is_blocked(): void
    {
        $staff = $this->admissionUser();
        $applicant = $this->applicant();
        $payment = $this->payment($applicant, 'verified', 9000);

        $this->actingAs($staff)
            ->post(route('admission.refunds.store', $applicant), $this->refundPayload([
                'admission_payment_id' => $payment->id,
                'requested_amount' => 4000,
            ]))
            ->assertRedirect(route('admission.refunds.index'));

        $this->actingAs($staff)
            ->post(route('admission.refunds.store', $applicant), $this->refundPayload([
                'admission_payment_id' => $payment->id,
                'requested_amount' => 3000,
            ]))
            ->assertSessionHasErrors('refund');

        $this->assertSame(1, RefundRequest::where('applicant_id', $applicant->id)->count());
    }

    public function test_refund_lifecycle_allows_only_valid_state_transitions(): void
    {
        $staff = $this->admissionUser();
        $applicant = $this->applicant();
        $payment = $this->payment($applicant, 'verified', 9000);
        $refund = RefundRequest::create(array_merge($this->refundPayload([
            'admission_payment_id' => $payment->id,
            'requested_amount' => 4000,
        ]), [
            'applicant_id' => $applicant->id,
            'status' => 'pending',
        ]));

        $this->actingAs($staff)
            ->patch(route('admission.refunds.process', $refund), ['utr_number' => 'UTR-PENDING'])
            ->assertSessionHas('error', 'Only approved refund requests can be processed.');

        $this->actingAs($staff)
            ->patch(route('admission.refunds.approve', $refund), ['approved_amount' => 3500])
            ->assertRedirect()
            ->assertSessionHas('success');

        $refund->refresh();
        $this->assertSame('approved', $refund->status);
        $this->assertSame(3500.0, $refund->approved_amount);

        $this->actingAs($staff)
            ->patch(route('admission.refunds.reject', $refund), ['rejection_reason' => 'Trying to reverse approved refund.'])
            ->assertSessionHas('error', 'Only pending refund requests can be rejected.');

        $this->actingAs($staff)
            ->patch(route('admission.refunds.approve', $refund), ['approved_amount' => 3000])
            ->assertSessionHas('error', 'Only pending refund requests can be approved.');

        $this->actingAs($staff)
            ->patch(route('admission.refunds.process', $refund), ['utr_number' => 'UTR-REFUND-1'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $refund->refresh();
        $this->assertSame('processed', $refund->status);
        $this->assertSame('UTR-REFUND-1', $refund->utr_number);
        $this->assertNotNull($refund->processed_at);

        $this->actingAs($staff)
            ->patch(route('admission.refunds.process', $refund), ['utr_number' => 'UTR-REFUND-2'])
            ->assertSessionHas('error', 'Only approved refund requests can be processed.');

        $this->actingAs($staff)
            ->patch(route('admission.refunds.reject', $refund), ['rejection_reason' => 'Trying to reverse processed refund.'])
            ->assertSessionHas('error', 'Only pending refund requests can be rejected.');
    }
}
