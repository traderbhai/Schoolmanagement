<?php

namespace Tests\Feature;

use App\Models\{User, Student, Program, FeeDemand, Term, AcademicYear, FeeStructure, Course, FeePaymentRequest, FeePayment};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FeePaymentTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(): array
    {
        $program = Program::factory()->create(['is_active' => true]);
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $user    = User::factory()->create();
        $user->assignRole('student');
        $student = Student::factory()->create(['user_id' => $user->id, 'program_id' => $program->id]);
        return [$user, $student, $program];
    }

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    private function currentFeeStructureFor(Student $student): FeeStructure
    {
        $academicYear = AcademicYear::create([
            'name' => '2026-27',
            'start_year' => 2026,
            'end_year' => 2027,
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
            'is_current' => true,
        ]);

        return FeeStructure::create([
            'course_id' => $student->course_id,
            'academic_year_id' => $academicYear->id,
            'fee_type' => 'Tuition',
            'amount' => 50000,
        ]);
    }

    public function test_student_can_view_fee_demands(): void
    {
        [$user] = $this->makeStudent();
        $this->actingAs($user)->get(route('student.fees'))->assertStatus(200);
    }

    public function test_student_fee_status_uses_active_fee_demands_for_outstanding_total(): void
    {
        [$user, $student] = $this->makeStudent();
        $term = Term::factory()->create(['name' => 'Fee Term']);

        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'final_amount' => 10000,
            'penalty_amount' => 1000,
            'status' => 'pending',
        ]);
        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'final_amount' => 4000,
            'penalty_amount' => 500,
            'status' => 'partially_paid',
        ]);
        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'final_amount' => 2000,
            'penalty_amount' => 250,
            'status' => 'overdue',
        ]);
        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'final_amount' => 6000,
            'penalty_amount' => 900,
            'status' => 'fully_paid',
        ]);

        $this->actingAs($user)
            ->get(route('student.fees'))
            ->assertStatus(200)
            ->assertSee('Outstanding: Rs. 17,750.00')
            ->assertSee('Balance Due')
            ->assertSee('Rs. 17,750')
            ->assertSee('Partial')
            ->assertSee('Overdue')
            ->assertDontSee('Fully paid - No dues')
            ->assertDontSee('Outstanding: Rs. 24,650.00');
    }

    public function test_student_can_view_fee_payment_page(): void
    {
        [$user] = $this->makeStudent();
        $this->actingAs($user)->get(route('student.fee-payment.index'))->assertStatus(200);
    }

    public function test_fee_payment_pages_show_only_outstanding_own_demands(): void
    {
        [$user, $student] = $this->makeStudent();
        $term = Term::factory()->create(['name' => 'Term 1']);
        $outstanding = FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'final_amount' => 25000,
            'penalty_amount' => 500,
            'status' => 'pending',
        ]);
        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 15000,
            'status' => 'fully_paid',
        ]);
        FeeDemand::factory()->create([
            'final_amount' => 33000,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->get(route('student.fee-payment.index'))
            ->assertStatus(200)
            ->assertSee('Term 1')
            ->assertSee('INR 25,500')
            ->assertDontSee('15,000')
            ->assertDontSee('33,000');

        $this->actingAs($user)
            ->get(route('student.fee-payment.create'))
            ->assertStatus(200)
            ->assertSee('value="' . $outstanding->id . '"', false)
            ->assertSee('Term 1 - INR 25,500')
            ->assertDontSee('15,000')
            ->assertDontSee('33,000');
    }

    public function test_student_cannot_submit_payment_against_paid_or_foreign_demand(): void
    {
        [$user, $student] = $this->makeStudent();
        $paidDemand = FeeDemand::factory()->create([
            'student_id' => $student->id,
            'status' => 'fully_paid',
        ]);
        $foreignDemand = FeeDemand::factory()->create(['status' => 'pending']);

        foreach ([$paidDemand, $foreignDemand] as $demand) {
            $this->actingAs($user)
                ->from(route('student.fee-payment.create'))
                ->post(route('student.fee-payment.store'), [
                    'fee_demand_id' => $demand->id,
                    'amount' => 1000,
                    'payment_method' => 'online',
                    'transaction_ref' => 'UTR' . $demand->id,
                ])
                ->assertRedirect(route('student.fee-payment.create'))
                ->assertSessionHasErrors('fee_demand_id');
        }
    }

    public function test_student_payment_proof_amount_cannot_exceed_selected_demand_balance(): void
    {
        [$user, $student] = $this->makeStudent();
        $demand = FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 10000,
            'penalty_amount' => 500,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->from(route('student.fee-payment.create'))
            ->post(route('student.fee-payment.store'), [
                'fee_demand_id' => $demand->id,
                'amount' => 10501,
                'payment_method' => 'online',
                'transaction_ref' => 'UTR-OVER-DEMAND',
            ])
            ->assertRedirect(route('student.fee-payment.create'))
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseMissing('fee_payment_requests', [
            'student_id' => $student->id,
            'transaction_ref' => 'UTR-OVER-DEMAND',
        ]);
    }

    public function test_student_payment_proof_amount_without_demand_cannot_exceed_total_open_balance(): void
    {
        [$user, $student] = $this->makeStudent();
        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 8000,
            'penalty_amount' => 200,
            'status' => 'pending',
        ]);
        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 3000,
            'penalty_amount' => 100,
            'status' => 'partially_paid',
        ]);

        $this->actingAs($user)
            ->from(route('student.fee-payment.create'))
            ->post(route('student.fee-payment.store'), [
                'amount' => 11301,
                'payment_method' => 'neft',
                'transaction_ref' => 'UTR-OVER-TOTAL',
            ])
            ->assertRedirect(route('student.fee-payment.create'))
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseMissing('fee_payment_requests', [
            'student_id' => $student->id,
            'transaction_ref' => 'UTR-OVER-TOTAL',
        ]);
    }

    public function test_student_cannot_submit_duplicate_pending_payment_proof_for_same_demand(): void
    {
        [$user, $student] = $this->makeStudent();
        $demand = FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 12000,
            'penalty_amount' => 0,
            'status' => 'pending',
        ]);
        FeePaymentRequest::create([
            'student_id' => $student->id,
            'fee_demand_id' => $demand->id,
            'amount' => 5000,
            'payment_method' => 'online',
            'transaction_ref' => 'UTR-PENDING',
            'submitted_at' => now(),
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->from(route('student.fee-payment.create'))
            ->post(route('student.fee-payment.store'), [
                'fee_demand_id' => $demand->id,
                'amount' => 3000,
                'payment_method' => 'online',
                'transaction_ref' => 'UTR-DUPLICATE',
            ])
            ->assertRedirect(route('student.fee-payment.create'))
            ->assertSessionHasErrors('fee_demand_id');

        $this->assertSame(1, FeePaymentRequest::where('student_id', $student->id)->where('fee_demand_id', $demand->id)->count());
    }

    public function test_accounts_can_view_outstanding(): void
    {
        Role::firstOrCreate(['name' => 'accounts_officer', 'guard_name' => 'web']);
        $accountsUser = User::factory()->create();
        $accountsUser->assignRole('accounts_officer');

        $this->actingAs($accountsUser)->get(route('accounts.outstanding'))->assertStatus(200);
    }

    public function test_accounts_can_view_fee_collections(): void
    {
        Role::firstOrCreate(['name' => 'accounts_officer', 'guard_name' => 'web']);
        $accountsUser = User::factory()->create();
        $accountsUser->assignRole('accounts_officer');

        $this->actingAs($accountsUser)->get(route('accounts.fee-collections'))->assertStatus(200);
    }

    public function test_admin_can_verify_student_payment_proof_and_close_fee_demand(): void
    {
        [$user, $student] = $this->makeStudent();
        $admin = $this->admin();
        $feeStructure = $this->currentFeeStructureFor($student);
        $demand = FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 10000,
            'penalty_amount' => 500,
            'status' => 'pending',
        ]);
        $request = FeePaymentRequest::create([
            'student_id' => $student->id,
            'fee_demand_id' => $demand->id,
            'amount' => 10500,
            'payment_method' => 'neft',
            'transaction_ref' => 'NEFT-VERIFY',
            'submitted_at' => now(),
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.fees.payment-requests.index'))
            ->assertOk()
            ->assertSee('Fee Payment Proofs')
            ->assertSee('NEFT-VERIFY');

        $this->actingAs($admin)
            ->patch(route('admin.fees.payment-requests.verify', $request), [
                'notes' => 'Bank credit confirmed.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Payment proof verified and fee receipt created.');

        $request->refresh();
        $demand->refresh();

        $this->assertSame('verified', $request->status);
        $this->assertSame($admin->id, $request->verified_by);
        $this->assertNotNull($request->verified_at);
        $this->assertSame('fully_paid', $demand->status);
        $this->assertSame('0.00', $demand->final_amount);
        $this->assertSame('0.00', $demand->penalty_amount);
        $this->assertDatabaseHas('fee_payments', [
            'student_id' => $student->id,
            'fee_structure_id' => $feeStructure->id,
            'amount_paid' => 10500,
            'payment_method' => 'online',
            'transaction_id' => 'NEFT-VERIFY',
            'status' => 'paid',
        ]);
    }

    public function test_admin_verifying_partial_student_payment_proof_reduces_open_demand_balance(): void
    {
        [, $student] = $this->makeStudent();
        $admin = $this->admin();
        $this->currentFeeStructureFor($student);
        $demand = FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 10000,
            'penalty_amount' => 500,
            'status' => 'overdue',
        ]);
        $request = FeePaymentRequest::create([
            'student_id' => $student->id,
            'fee_demand_id' => $demand->id,
            'amount' => 3000,
            'payment_method' => 'online',
            'transaction_ref' => 'PARTIAL-VERIFY',
            'submitted_at' => now(),
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.fees.payment-requests.verify', $request))
            ->assertRedirect();

        $demand->refresh();
        $this->assertSame('partially_paid', $demand->status);
        $this->assertSame('7500.00', $demand->final_amount);
        $this->assertSame('0.00', $demand->penalty_amount);
    }

    public function test_admin_can_reject_student_payment_proof_with_notes(): void
    {
        [, $student] = $this->makeStudent();
        $admin = $this->admin();
        $request = FeePaymentRequest::create([
            'student_id' => $student->id,
            'amount' => 2000,
            'payment_method' => 'online',
            'transaction_ref' => 'BAD-REF',
            'submitted_at' => now(),
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.fees.payment-requests.reject', $request), [
                'notes' => 'Transaction reference not found in bank statement.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Payment proof rejected with staff notes.');

        $request->refresh();
        $this->assertSame('rejected', $request->status);
        $this->assertSame($admin->id, $request->verified_by);
        $this->assertSame('Transaction reference not found in bank statement.', $request->notes);
        $this->assertSame(0, FeePayment::where('transaction_id', 'BAD-REF')->count());
    }

    public function test_admin_cannot_verify_payment_proof_above_current_demand_balance(): void
    {
        [, $student] = $this->makeStudent();
        $admin = $this->admin();
        $this->currentFeeStructureFor($student);
        $demand = FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 1000,
            'penalty_amount' => 0,
            'status' => 'pending',
        ]);
        $request = FeePaymentRequest::create([
            'student_id' => $student->id,
            'fee_demand_id' => $demand->id,
            'amount' => 1500,
            'payment_method' => 'online',
            'transaction_ref' => 'OVER-VERIFY',
            'submitted_at' => now(),
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.fees.payment-requests.verify', $request))
            ->assertSessionHasErrors('amount');

        $this->assertSame('pending', $request->fresh()->status);
        $this->assertSame('pending', $demand->fresh()->status);
        $this->assertSame(0, FeePayment::where('transaction_id', 'OVER-VERIFY')->count());
    }

    public function test_admin_fee_collection_prefers_demand_balance_and_blocks_wrong_course_structure(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        [, $student] = $this->makeStudent();
        $academicYear = AcademicYear::create([
            'name' => '2026-27',
            'start_year' => 2026,
            'end_year' => 2027,
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
            'is_current' => true,
        ]);
        $ownStructure = FeeStructure::create([
            'course_id' => $student->course_id,
            'academic_year_id' => $academicYear->id,
            'fee_type' => 'Tuition',
            'amount' => 99999,
        ]);
        $otherStructure = FeeStructure::create([
            'course_id' => Course::factory()->create()->id,
            'academic_year_id' => $academicYear->id,
            'fee_type' => 'Other Course Tuition',
            'amount' => 1000,
        ]);
        $term = Term::factory()->create(['name' => 'Demand Term']);

        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'final_amount' => 10000,
            'penalty_amount' => 500,
            'status' => 'pending',
        ]);
        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'final_amount' => 4000,
            'penalty_amount' => 250,
            'status' => 'overdue',
        ]);
        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'final_amount' => 12000,
            'penalty_amount' => 800,
            'status' => 'fully_paid',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.fees.collect', ['student_id' => $student->id]))
            ->assertStatus(200)
            ->assertSee('14,750.00')
            ->assertSee('Demand Term')
            ->assertSee('99,999.00')
            ->assertDontSee('Other Course Tuition');

        $this->actingAs($admin)
            ->from(route('admin.fees.collect', ['student_id' => $student->id]))
            ->post(route('admin.fees.payment'), [
                'student_id' => $student->id,
                'fee_structure_id' => $otherStructure->id,
                'amount_paid' => 1000,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'cash',
            ])
            ->assertRedirect(route('admin.fees.collect', ['student_id' => $student->id]))
            ->assertSessionHasErrors('fee_structure_id');

        $this->actingAs($admin)
            ->post(route('admin.fees.payment'), [
                'student_id' => $student->id,
                'fee_structure_id' => $ownStructure->id,
                'amount_paid' => 1000,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'cash',
            ])
            ->assertRedirect();
    }
}
