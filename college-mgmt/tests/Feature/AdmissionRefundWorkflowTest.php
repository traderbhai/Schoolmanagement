<?php

namespace Tests\Feature;

use App\Models\AdmissionFeeInstallment;
use App\Models\AdmissionPayment;
use App\Models\Applicant;
use App\Models\Department;
use App\Models\DepartmentMember;
use App\Models\DepartmentRole;
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

    private function admissionUserWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

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

    public function test_refund_reason_must_match_applicant_status_and_excess_balance(): void
    {
        $staff = $this->admissionUser();
        $selected = Applicant::factory()->create(['status' => 'selected']);
        $this->payment($selected, 'verified', 9000);

        $this->actingAs($staff)
            ->post(route('admission.refunds.store', $selected), $this->refundPayload([
                'requested_amount' => 3000,
                'reason' => 'withdrawal',
            ]))
            ->assertSessionHasErrors('requested_amount');

        $this->actingAs($staff)
            ->post(route('admission.refunds.store', $selected), $this->refundPayload([
                'requested_amount' => 3000,
                'reason' => 'rejection',
            ]))
            ->assertSessionHasErrors('requested_amount');

        $this->actingAs($staff)
            ->post(route('admission.refunds.store', $selected), $this->refundPayload([
                'requested_amount' => 1000,
                'reason' => 'excess_payment',
            ]))
            ->assertSessionHasErrors('requested_amount');

        $this->assertSame(0, RefundRequest::where('applicant_id', $selected->id)->count());
    }

    public function test_refund_approval_revalidates_stale_reason_status(): void
    {
        $staff = $this->admissionUser();
        $applicant = $this->applicant();
        $payment = $this->payment($applicant, 'verified', 9000);
        $refund = RefundRequest::create(array_merge($this->refundPayload([
            'admission_payment_id' => $payment->id,
            'requested_amount' => 4000,
            'reason' => 'withdrawal',
        ]), [
            'applicant_id' => $applicant->id,
            'status' => 'pending',
        ]));

        $applicant->update(['status' => 'selected']);

        $this->actingAs($staff)
            ->patch(route('admission.refunds.approve', $refund), ['approved_amount' => 3500])
            ->assertSessionHasErrors('approved_amount');

        $refund->refresh();
        $this->assertSame('pending', $refund->status);
        $this->assertNull($refund->approved_amount);
        $this->assertNull($refund->reviewed_at);
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

    public function test_refund_processing_revalidates_stale_applicant_and_payment_state(): void
    {
        $staff = $this->admissionUser();
        $applicant = $this->applicant();
        $payment = $this->payment($applicant, 'verified', 9000);
        $refund = RefundRequest::create(array_merge($this->refundPayload([
            'admission_payment_id' => $payment->id,
            'requested_amount' => 4000,
            'reason' => 'withdrawal',
        ]), [
            'applicant_id' => $applicant->id,
            'status' => 'approved',
            'approved_amount' => 3500,
            'reviewed_by' => $staff->id,
            'reviewed_at' => now(),
        ]));

        $applicant->update(['status' => 'selected']);

        $this->actingAs($staff)
            ->patch(route('admission.refunds.process', $refund), ['utr_number' => 'UTR-STALE-REFUND'])
            ->assertSessionHasErrors('refund');

        $refund->refresh();
        $this->assertSame('approved', $refund->status);
        $this->assertNull($refund->utr_number);
        $this->assertNull($refund->processed_at);
    }

    public function test_refund_routes_respect_admission_hierarchy_scope(): void
    {
        $department = Department::where('code', 'ADM')->firstOrFail();
        $manager = $this->admissionUserWithRole('admission_manager');
        $directReport = $this->admissionUserWithRole('admission_counsellor');
        $outsideCounsellor = $this->admissionUserWithRole('admission_counsellor');
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

        $visibleApplicant = $this->applicant();
        $visibleApplicant->update(['assigned_to' => $directReport->id]);
        $visibleApplicant->user->update(['name' => 'Visible Refund Applicant']);
        $visiblePayment = $this->payment($visibleApplicant, 'verified', 9000);
        $visibleRefund = RefundRequest::create(array_merge($this->refundPayload([
            'admission_payment_id' => $visiblePayment->id,
            'requested_amount' => 2000,
            'reason_detail' => 'Visible refund request.',
        ]), [
            'applicant_id' => $visibleApplicant->id,
            'status' => 'pending',
        ]));

        $hiddenApplicant = $this->applicant();
        $hiddenApplicant->update(['assigned_to' => $outsideCounsellor->id]);
        $hiddenApplicant->user->update(['name' => 'Hidden Refund Applicant']);
        $hiddenPayment = $this->payment($hiddenApplicant, 'verified', 9000);
        $hiddenRefund = RefundRequest::create(array_merge($this->refundPayload([
            'admission_payment_id' => $hiddenPayment->id,
            'requested_amount' => 3000,
            'reason_detail' => 'Hidden sibling refund request.',
        ]), [
            'applicant_id' => $hiddenApplicant->id,
            'status' => 'pending',
        ]));

        $this->actingAs($manager)
            ->get(route('admission.refunds.index'))
            ->assertOk()
            ->assertSee('Visible Refund Applicant')
            ->assertDontSee('Hidden Refund Applicant');

        $this->actingAs($manager)
            ->get(route('admission.refunds.show', $hiddenRefund))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('admission.refunds.create', $hiddenApplicant))
            ->assertForbidden();

        $this->actingAs($manager)
            ->post(route('admission.refunds.store', $hiddenApplicant), $this->refundPayload([
                'admission_payment_id' => $hiddenPayment->id,
                'requested_amount' => 1000,
            ]))
            ->assertForbidden();

        $this->actingAs($manager)
            ->patch(route('admission.refunds.approve', $hiddenRefund), ['approved_amount' => 1000])
            ->assertForbidden();

        $this->assertSame('pending', $hiddenRefund->fresh()->status);
        $this->assertNull($hiddenRefund->fresh()->approved_amount);

        $this->actingAs($manager)
            ->patch(route('admission.refunds.approve', $visibleRefund), ['approved_amount' => 1500])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('approved', $visibleRefund->fresh()->status);
    }
}
