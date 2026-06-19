<?php

namespace Tests\Feature;

use App\Models\{User, Student, Program, FeeDemand, Term, AcademicYear, FeeStructure, Course, FeePaymentRequest, FeePayment, HostelAllocation, HostelBlock, HostelFeeDemand, HostelRoom};
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

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
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

    public function test_student_fee_page_shows_only_paid_receipt_history(): void
    {
        [$user, $student] = $this->makeStudent();
        $feeStructure = $this->currentFeeStructureFor($student);

        FeePayment::create([
            'student_id' => $student->id,
            'fee_structure_id' => $feeStructure->id,
            'amount_paid' => 4000,
            'payment_date' => now()->toDateString(),
            'receipt_number' => 'STUDENT-PAID-001',
            'payment_method' => 'online',
            'status' => 'paid',
        ]);
        FeePayment::create([
            'student_id' => $student->id,
            'fee_structure_id' => $feeStructure->id,
            'amount_paid' => 6000,
            'payment_date' => now()->toDateString(),
            'receipt_number' => 'STUDENT-PENDING-001',
            'payment_method' => 'online',
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->get(route('student.fees'))
            ->assertOk()
            ->assertSee('STUDENT-PAID-001')
            ->assertDontSee('STUDENT-PENDING-001');
    }

    public function test_student_fee_page_falls_back_to_course_structures_when_no_current_academic_year_exists(): void
    {
        [$user, $student] = $this->makeStudent();
        $academicYear = AcademicYear::create([
            'name' => 'Historical Fee Year',
            'start_year' => 2025,
            'end_year' => 2026,
            'start_date' => now()->subYear()->startOfYear(),
            'end_date' => now()->subYear()->endOfYear(),
            'is_current' => false,
        ]);
        $structure = FeeStructure::create([
            'course_id' => $student->course_id,
            'program_id' => $student->program_id,
            'academic_year_id' => $academicYear->id,
            'fee_type' => 'Tuition',
            'amount' => 10000,
        ]);

        FeePayment::create([
            'student_id' => $student->id,
            'fee_structure_id' => $structure->id,
            'amount_paid' => 4000,
            'payment_date' => now()->toDateString(),
            'receipt_number' => 'STUDENT-NO-CURRENT-YEAR-PAID',
            'payment_method' => 'online',
            'status' => 'paid',
        ]);

        $this->actingAs($user)
            ->get(route('student.fees'))
            ->assertOk()
            ->assertSee('Total Fee Due')
            ->assertSee('Rs. 10,000')
            ->assertSee('Balance Due')
            ->assertSee('Rs. 6,000')
            ->assertSee('STUDENT-NO-CURRENT-YEAR-PAID')
            ->assertDontSee('No fee structures defined for your course yet.');
    }

    public function test_student_can_view_fee_payment_page(): void
    {
        [$user] = $this->makeStudent();
        $this->actingAs($user)->get(route('student.fee-payment.index'))->assertStatus(200);
    }

    public function test_student_payment_proof_action_requires_open_academic_fee_demands(): void
    {
        [$user, $student] = $this->makeStudent();
        $block = HostelBlock::create([
            'name' => 'Fee Proof Hostel',
            'gender' => 'mixed',
            'total_floors' => 1,
            'is_active' => true,
        ]);
        $room = HostelRoom::create([
            'hostel_block_id' => $block->id,
            'room_number' => '401',
            'floor' => 1,
            'room_type' => 'single',
            'capacity' => 1,
            'monthly_fee' => 6000,
            'status' => 'available',
        ]);
        $allocation = HostelAllocation::create([
            'hostel_room_id' => $room->id,
            'student_id' => $student->id,
            'bed_number' => 1,
            'allocated_from' => now()->subMonth()->toDateString(),
            'status' => 'active',
        ]);
        HostelFeeDemand::create([
            'hostel_allocation_id' => $allocation->id,
            'student_id' => $student->id,
            'month' => '2026-06',
            'amount' => 6000,
            'status' => 'pending',
            'due_date' => now()->addWeek()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('student.fee-payment.index'))
            ->assertOk()
            ->assertSee('There are no outstanding academic fee demands available for payment proof submission')
            ->assertSee('Hostel fee dues')
            ->assertDontSee('Submit Payment Proof');

        $this->actingAs($user)
            ->get(route('student.fee-payment.create'))
            ->assertRedirect(route('student.fee-payment.index'))
            ->assertSessionHas('error', 'There are no outstanding academic fee demands available for payment proof submission.');
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

    public function test_inactive_student_can_view_fee_payment_history_but_cannot_submit_new_proof(): void
    {
        [$user, $student] = $this->makeStudent();
        $student->update(['status' => 'inactive']);
        $demand = FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 10000,
            'penalty_amount' => 0,
            'status' => 'pending',
        ]);
        FeePaymentRequest::create([
            'student_id' => $student->id,
            'fee_demand_id' => $demand->id,
            'amount' => 4000,
            'payment_method' => 'online',
            'transaction_ref' => 'UTR-ARCHIVED-HISTORY',
            'submitted_at' => now(),
            'status' => 'rejected',
            'notes' => 'Historical rejected proof.',
        ]);

        $this->actingAs($user)
            ->get(route('student.fee-payment.index'))
            ->assertOk()
            ->assertSee('UTR-ARCHIVED-HISTORY')
            ->assertSee('New fee payment proofs are available only for active students.')
            ->assertDontSee('Submit Payment Proof');

        $this->actingAs($user)
            ->get(route('student.fee-payment.create'))
            ->assertRedirect(route('student.fee-payment.index'))
            ->assertSessionHas('error', 'New fee payment proofs are available only for active students. Contact accounts for archived records.');

        $this->actingAs($user)
            ->post(route('student.fee-payment.store'), [
                'fee_demand_id' => $demand->id,
                'amount' => 1000,
                'payment_method' => 'online',
                'transaction_ref' => 'UTR-INACTIVE-NEW',
            ])
            ->assertRedirect(route('student.fee-payment.index'))
            ->assertSessionHas('error', 'New fee payment proofs are available only for active students. Contact accounts for archived records.');

        $this->assertDatabaseMissing('fee_payment_requests', [
            'student_id' => $student->id,
            'transaction_ref' => 'UTR-INACTIVE-NEW',
        ]);
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

    public function test_student_non_cash_payment_proof_requires_transaction_reference(): void
    {
        [$user, $student] = $this->makeStudent();
        $demand = FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 5000,
            'penalty_amount' => 0,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->from(route('student.fee-payment.create'))
            ->post(route('student.fee-payment.store'), [
                'fee_demand_id' => $demand->id,
                'amount' => 1000,
                'payment_method' => 'neft',
                'transaction_ref' => '   ',
            ])
            ->assertRedirect(route('student.fee-payment.create'))
            ->assertSessionHasErrors('transaction_ref');

        $this->assertSame(0, FeePaymentRequest::where('student_id', $student->id)->count());

        $this->actingAs($user)
            ->from(route('student.fee-payment.create'))
            ->post(route('student.fee-payment.store'), [
                'fee_demand_id' => $demand->id,
                'amount' => 1000,
                'payment_method' => 'cash',
                'transaction_ref' => '',
            ])
            ->assertRedirect(route('student.fee-payment.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('fee_payment_requests', [
            'student_id' => $student->id,
            'fee_demand_id' => $demand->id,
            'amount' => 1000,
            'payment_method' => 'cash',
            'transaction_ref' => null,
            'status' => 'pending',
        ]);
    }

    public function test_student_cannot_submit_payment_proof_with_already_verified_transaction_reference(): void
    {
        [$user, $student] = $this->makeStudent();
        $feeStructure = $this->currentFeeStructureFor($student);
        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 5000,
            'penalty_amount' => 0,
            'status' => 'pending',
        ]);
        FeePayment::create([
            'student_id' => $student->id,
            'fee_structure_id' => $feeStructure->id,
            'amount_paid' => 2500,
            'payment_date' => now()->toDateString(),
            'receipt_number' => 'DUP-REF-RECEIPT',
            'payment_method' => 'online',
            'transaction_id' => 'DUPLICATE-UTR',
            'status' => 'paid',
        ]);

        $this->actingAs($user)
            ->from(route('student.fee-payment.create'))
            ->post(route('student.fee-payment.store'), [
                'amount' => 2500,
                'payment_method' => 'online',
                'transaction_ref' => 'DUPLICATE-UTR',
            ])
            ->assertRedirect(route('student.fee-payment.create'))
            ->assertSessionHasErrors('transaction_ref');

        $this->assertDatabaseMissing('fee_payment_requests', [
            'student_id' => $student->id,
            'transaction_ref' => 'DUPLICATE-UTR',
            'status' => 'pending',
        ]);
    }

    public function test_student_verified_transaction_reference_duplicate_check_is_case_insensitive(): void
    {
        [$user, $student] = $this->makeStudent();
        $feeStructure = $this->currentFeeStructureFor($student);
        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 5000,
            'penalty_amount' => 0,
            'status' => 'pending',
        ]);
        FeePayment::create([
            'student_id' => $student->id,
            'fee_structure_id' => $feeStructure->id,
            'amount_paid' => 2500,
            'payment_date' => now()->toDateString(),
            'receipt_number' => 'CASE-REF-RECEIPT',
            'payment_method' => 'online',
            'transaction_id' => 'Case-Duplicate-UTR',
            'status' => 'paid',
        ]);

        $this->actingAs($user)
            ->from(route('student.fee-payment.create'))
            ->post(route('student.fee-payment.store'), [
                'amount' => 1000,
                'payment_method' => 'neft',
                'transaction_ref' => 'case-duplicate-utr',
            ])
            ->assertRedirect(route('student.fee-payment.create'))
            ->assertSessionHasErrors('transaction_ref');

        $this->assertDatabaseMissing('fee_payment_requests', [
            'student_id' => $student->id,
            'transaction_ref' => 'case-duplicate-utr',
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

    public function test_student_cannot_submit_duplicate_pending_transaction_reference(): void
    {
        [$user, $student] = $this->makeStudent();
        $firstDemand = FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 7000,
            'penalty_amount' => 0,
            'status' => 'pending',
        ]);
        $secondDemand = FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 8000,
            'penalty_amount' => 0,
            'status' => 'pending',
        ]);
        FeePaymentRequest::create([
            'student_id' => $student->id,
            'fee_demand_id' => $firstDemand->id,
            'amount' => 3000,
            'payment_method' => 'online',
            'transaction_ref' => 'PENDING-DUP-UTR',
            'submitted_at' => now(),
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->from(route('student.fee-payment.create'))
            ->post(route('student.fee-payment.store'), [
                'fee_demand_id' => $secondDemand->id,
                'amount' => 3000,
                'payment_method' => 'online',
                'transaction_ref' => 'PENDING-DUP-UTR',
            ])
            ->assertRedirect(route('student.fee-payment.create'))
            ->assertSessionHasErrors('transaction_ref');

        $this->actingAs($user)
            ->from(route('student.fee-payment.create'))
            ->post(route('student.fee-payment.store'), [
                'amount' => 3000,
                'payment_method' => 'online',
                'transaction_ref' => 'PENDING-DUP-UTR',
            ])
            ->assertRedirect(route('student.fee-payment.create'))
            ->assertSessionHasErrors('transaction_ref');

        $this->assertSame(1, FeePaymentRequest::where('transaction_ref', 'PENDING-DUP-UTR')->count());
    }

    public function test_student_pending_transaction_reference_duplicate_check_is_case_insensitive(): void
    {
        [$user, $student] = $this->makeStudent();
        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 5000,
            'penalty_amount' => 0,
            'status' => 'pending',
        ]);
        FeePaymentRequest::create([
            'student_id' => $student->id,
            'amount' => 1000,
            'payment_method' => 'online',
            'transaction_ref' => 'Pending-Case-UTR',
            'submitted_at' => now(),
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->from(route('student.fee-payment.create'))
            ->post(route('student.fee-payment.store'), [
                'amount' => 1000,
                'payment_method' => 'online',
                'transaction_ref' => 'pending-case-utr',
            ])
            ->assertRedirect(route('student.fee-payment.create'))
            ->assertSessionHasErrors('transaction_ref');

        $this->assertSame(1, FeePaymentRequest::whereRaw('LOWER(transaction_ref) = ?', ['pending-case-utr'])->count());
    }

    public function test_student_pending_payment_proofs_reserve_open_balance_until_accounts_review(): void
    {
        [$user, $student] = $this->makeStudent();
        $firstDemand = FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 5000,
            'penalty_amount' => 0,
            'status' => 'pending',
        ]);
        $secondDemand = FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 3000,
            'penalty_amount' => 0,
            'status' => 'pending',
        ]);

        FeePaymentRequest::create([
            'student_id' => $student->id,
            'fee_demand_id' => $firstDemand->id,
            'amount' => 5000,
            'payment_method' => 'online',
            'transaction_ref' => 'PENDING-BALANCE-RESERVED',
            'submitted_at' => now(),
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->from(route('student.fee-payment.create'))
            ->post(route('student.fee-payment.store'), [
                'fee_demand_id' => $secondDemand->id,
                'amount' => 3001,
                'payment_method' => 'neft',
                'transaction_ref' => 'OVER-PENDING-LINKED',
            ])
            ->assertRedirect(route('student.fee-payment.create'))
            ->assertSessionHasErrors('amount');

        $this->actingAs($user)
            ->from(route('student.fee-payment.create'))
            ->post(route('student.fee-payment.store'), [
                'amount' => 3001,
                'payment_method' => 'neft',
                'transaction_ref' => 'OVER-PENDING-UNLINKED',
            ])
            ->assertRedirect(route('student.fee-payment.create'))
            ->assertSessionHasErrors('amount');

        $this->actingAs($user)
            ->post(route('student.fee-payment.store'), [
                'fee_demand_id' => $secondDemand->id,
                'amount' => 3000,
                'payment_method' => 'neft',
                'transaction_ref' => 'WITHIN-PENDING-BALANCE',
            ])
            ->assertRedirect(route('student.fee-payment.index'))
            ->assertSessionHas('success', 'Payment proof submitted. Accounts will verify within 1-2 working days.');

        $this->assertDatabaseMissing('fee_payment_requests', [
            'student_id' => $student->id,
            'transaction_ref' => 'OVER-PENDING-LINKED',
        ]);
        $this->assertDatabaseMissing('fee_payment_requests', [
            'student_id' => $student->id,
            'transaction_ref' => 'OVER-PENDING-UNLINKED',
        ]);
        $this->assertDatabaseHas('fee_payment_requests', [
            'student_id' => $student->id,
            'transaction_ref' => 'WITHIN-PENDING-BALANCE',
            'status' => 'pending',
        ]);
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

    public function test_program_chair_cannot_directly_view_or_mutate_admin_fee_surfaces(): void
    {
        [, $student] = $this->makeStudent();
        $chair = $this->userWithRole('program_chair');
        $this->currentFeeStructureFor($student);
        $demand = FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 5000,
            'penalty_amount' => 0,
            'status' => 'pending',
        ]);
        $request = FeePaymentRequest::create([
            'student_id' => $student->id,
            'fee_demand_id' => $demand->id,
            'amount' => 2000,
            'payment_method' => 'online',
            'transaction_ref' => 'CHAIR-FEE-PROOF',
            'submitted_at' => now(),
            'status' => 'pending',
            'proof_path' => 'fee-proofs/chair-proof.pdf',
        ]);

        $this->actingAs($chair)
            ->get(route('admin.fees.index'))
            ->assertForbidden();

        $this->actingAs($chair)
            ->get(route('admin.fees.collect'))
            ->assertForbidden();

        $this->actingAs($chair)
            ->post(route('admin.fees.payment'), [
                'student_id' => $student->id,
                'fee_structure_id' => FeeStructure::firstOrFail()->id,
                'amount_paid' => 500,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'cash',
            ])
            ->assertForbidden();

        $this->actingAs($chair)
            ->get(route('admin.fees.payment-requests.index'))
            ->assertForbidden();

        $this->actingAs($chair)
            ->get(route('admin.fees.payment-requests.proof', $request))
            ->assertForbidden();

        $this->actingAs($chair)
            ->patch(route('admin.fees.payment-requests.verify', $request), [
                'notes' => 'Unauthorized verification.',
            ])
            ->assertForbidden();

        $this->actingAs($chair)
            ->patch(route('admin.fees.payment-requests.reject', $request), [
                'notes' => 'Unauthorized rejection.',
            ])
            ->assertForbidden();

        $request->refresh();
        $demand->refresh();

        $this->assertSame('pending', $request->status);
        $this->assertNull($request->verified_by);
        $this->assertSame('pending', $demand->status);
        $this->assertSame('5000.00', $demand->final_amount);
        $this->assertSame(0, FeePayment::where('transaction_id', 'CHAIR-FEE-PROOF')->count());
    }

    public function test_accounts_officer_can_use_admin_fee_payment_request_queue(): void
    {
        [, $student] = $this->makeStudent();
        $accounts = $this->userWithRole('accounts_officer');
        $this->currentFeeStructureFor($student);
        $demand = FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 3000,
            'penalty_amount' => 0,
            'status' => 'pending',
        ]);
        $request = FeePaymentRequest::create([
            'student_id' => $student->id,
            'fee_demand_id' => $demand->id,
            'amount' => 3000,
            'payment_method' => 'online',
            'transaction_ref' => 'ACC-FEE-PROOF',
            'submitted_at' => now(),
            'status' => 'pending',
        ]);

        $this->actingAs($accounts)
            ->get(route('admin.fees.payment-requests.index'))
            ->assertOk()
            ->assertSee('ACC-FEE-PROOF');

        $this->actingAs($accounts)
            ->patch(route('admin.fees.payment-requests.verify', $request), [
                'notes' => 'Accounts verified.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Payment proof verified and fee receipt created.');

        $this->assertSame('verified', $request->fresh()->status);
        $this->assertSame($accounts->id, $request->fresh()->verified_by);
        $this->assertSame('fully_paid', $demand->fresh()->status);
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

    public function test_admin_cannot_verify_stale_non_cash_payment_proof_without_transaction_reference(): void
    {
        [, $student] = $this->makeStudent();
        $admin = $this->admin();
        $this->currentFeeStructureFor($student);
        $demand = FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 5000,
            'penalty_amount' => 0,
            'status' => 'pending',
        ]);
        $request = FeePaymentRequest::create([
            'student_id' => $student->id,
            'fee_demand_id' => $demand->id,
            'amount' => 2000,
            'payment_method' => 'online',
            'transaction_ref' => '   ',
            'submitted_at' => now(),
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.fees.payment-requests.verify', $request))
            ->assertSessionHasErrors('transaction_ref');

        $request->refresh();
        $demand->refresh();
        $this->assertSame('pending', $request->status);
        $this->assertSame('pending', $demand->status);
        $this->assertSame('5000.00', $demand->final_amount);
        $this->assertSame(0, FeePayment::where('student_id', $student->id)->where('amount_paid', 2000)->count());
    }

    public function test_admin_cannot_verify_payment_proof_with_already_used_transaction_reference(): void
    {
        [, $student] = $this->makeStudent();
        $admin = $this->admin();
        $feeStructure = $this->currentFeeStructureFor($student);
        $demand = FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 5000,
            'penalty_amount' => 0,
            'status' => 'pending',
        ]);
        FeePayment::create([
            'student_id' => $student->id,
            'fee_structure_id' => $feeStructure->id,
            'amount_paid' => 2000,
            'payment_date' => now()->toDateString(),
            'receipt_number' => 'USED-VERIFY-RECEIPT',
            'payment_method' => 'online',
            'transaction_id' => 'USED-VERIFY-REF',
            'status' => 'paid',
        ]);
        $request = FeePaymentRequest::create([
            'student_id' => $student->id,
            'fee_demand_id' => $demand->id,
            'amount' => 3000,
            'payment_method' => 'online',
            'transaction_ref' => 'USED-VERIFY-REF',
            'submitted_at' => now(),
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.fees.payment-requests.verify', $request))
            ->assertSessionHasErrors('transaction_ref');

        $this->assertSame('pending', $request->fresh()->status);
        $this->assertSame('pending', $demand->fresh()->status);
        $this->assertSame('5000.00', $demand->fresh()->final_amount);
        $this->assertSame(1, FeePayment::where('transaction_id', 'USED-VERIFY-REF')->count());
    }

    public function test_admin_verify_payment_proof_transaction_reference_duplicate_check_is_case_insensitive(): void
    {
        [, $student] = $this->makeStudent();
        $admin = $this->admin();
        $feeStructure = $this->currentFeeStructureFor($student);
        $demand = FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 5000,
            'penalty_amount' => 0,
            'status' => 'pending',
        ]);
        FeePayment::create([
            'student_id' => $student->id,
            'fee_structure_id' => $feeStructure->id,
            'amount_paid' => 2000,
            'payment_date' => now()->toDateString(),
            'receipt_number' => 'USED-VERIFY-CASE-RECEIPT',
            'payment_method' => 'online',
            'transaction_id' => 'Used-Verify-Case-Ref',
            'status' => 'paid',
        ]);
        $request = FeePaymentRequest::create([
            'student_id' => $student->id,
            'fee_demand_id' => $demand->id,
            'amount' => 3000,
            'payment_method' => 'online',
            'transaction_ref' => 'used-verify-case-ref',
            'submitted_at' => now(),
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.fees.payment-requests.verify', $request))
            ->assertSessionHasErrors('transaction_ref');

        $this->assertSame('pending', $request->fresh()->status);
        $this->assertSame('pending', $demand->fresh()->status);
        $this->assertSame('5000.00', $demand->fresh()->final_amount);
        $this->assertSame(1, FeePayment::whereRaw('LOWER(transaction_id) = ?', ['used-verify-case-ref'])->count());
    }

    public function test_admin_cannot_verify_payment_proof_for_inactive_student(): void
    {
        [, $student] = $this->makeStudent();
        $admin = $this->admin();
        $this->currentFeeStructureFor($student);

        $demand = FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 5000,
            'penalty_amount' => 0,
            'status' => 'pending',
        ]);
        $request = FeePaymentRequest::create([
            'student_id' => $student->id,
            'fee_demand_id' => $demand->id,
            'amount' => 2000,
            'payment_method' => 'online',
            'transaction_ref' => 'INACTIVE-STUDENT-PROOF',
            'submitted_at' => now(),
            'status' => 'pending',
        ]);

        $student->update(['status' => 'inactive']);

        $this->actingAs($admin)
            ->patch(route('admin.fees.payment-requests.verify', $request))
            ->assertRedirect()
            ->assertSessionHas('error', 'Payment proofs for inactive or archived students cannot be verified from the standard queue. Contact accounts for record correction.');

        $this->assertSame('pending', $request->fresh()->status);
        $this->assertSame('pending', $demand->fresh()->status);
        $this->assertSame('5000.00', $demand->fresh()->final_amount);
        $this->assertSame(0, FeePayment::where('transaction_id', 'INACTIVE-STUDENT-PROOF')->count());
    }

    public function test_admin_cannot_verify_payment_proof_against_closed_demand_or_over_total_open_balance(): void
    {
        [, $student] = $this->makeStudent();
        $admin = $this->admin();
        $this->currentFeeStructureFor($student);

        $closedDemand = FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 0,
            'penalty_amount' => 0,
            'status' => 'fully_paid',
        ]);
        $closedRequest = FeePaymentRequest::create([
            'student_id' => $student->id,
            'fee_demand_id' => $closedDemand->id,
            'amount' => 1000,
            'payment_method' => 'online',
            'transaction_ref' => 'CLOSED-DEMAND-PROOF',
            'submitted_at' => now(),
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.fees.payment-requests.verify', $closedRequest))
            ->assertSessionHasErrors('fee_demand_id');

        $this->assertSame('pending', $closedRequest->fresh()->status);
        $this->assertSame(0, FeePayment::where('transaction_id', 'CLOSED-DEMAND-PROOF')->count());

        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 2000,
            'penalty_amount' => 100,
            'status' => 'pending',
        ]);
        $unlinkedRequest = FeePaymentRequest::create([
            'student_id' => $student->id,
            'amount' => 2101,
            'payment_method' => 'neft',
            'transaction_ref' => 'UNLINKED-OVER-TOTAL',
            'submitted_at' => now(),
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.fees.payment-requests.verify', $unlinkedRequest))
            ->assertSessionHasErrors('amount');

        $this->assertSame('pending', $unlinkedRequest->fresh()->status);
        $this->assertSame(0, FeePayment::where('transaction_id', 'UNLINKED-OVER-TOTAL')->count());
    }

    public function test_admin_cannot_verify_payment_proof_linked_to_another_students_fee_demand(): void
    {
        [, $student] = $this->makeStudent();
        [, $otherStudent] = $this->makeStudent();
        $admin = $this->admin();
        $this->currentFeeStructureFor($student);
        $this->currentFeeStructureFor($otherStudent);

        $foreignDemand = FeeDemand::factory()->create([
            'student_id' => $otherStudent->id,
            'final_amount' => 5000,
            'penalty_amount' => 250,
            'status' => 'pending',
        ]);
        $request = FeePaymentRequest::create([
            'student_id' => $student->id,
            'fee_demand_id' => $foreignDemand->id,
            'amount' => 1000,
            'payment_method' => 'online',
            'transaction_ref' => 'CROSS-STUDENT-DEMAND-PROOF',
            'submitted_at' => now(),
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.fees.payment-requests.verify', $request))
            ->assertSessionHasErrors('fee_demand_id');

        $this->assertSame('pending', $request->fresh()->status);
        $this->assertSame('pending', $foreignDemand->fresh()->status);
        $this->assertSame('5000.00', $foreignDemand->fresh()->final_amount);
        $this->assertSame('250.00', $foreignDemand->fresh()->penalty_amount);
        $this->assertSame(0, FeePayment::where('transaction_id', 'CROSS-STUDENT-DEMAND-PROOF')->count());
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

    public function test_manual_admin_payment_reduces_active_fee_demands_oldest_first(): void
    {
        $admin = $this->admin();
        [, $student] = $this->makeStudent();
        $feeStructure = $this->currentFeeStructureFor($student);
        $term = Term::factory()->create(['name' => 'Manual Payment Term']);
        $olderDemand = FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'final_amount' => 5000,
            'penalty_amount' => 500,
            'due_date' => now()->subDays(10)->toDateString(),
            'status' => 'overdue',
        ]);
        $newerDemand = FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'final_amount' => 4000,
            'penalty_amount' => 0,
            'due_date' => now()->addDays(10)->toDateString(),
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.fees.payment'), [
                'student_id' => $student->id,
                'fee_structure_id' => $feeStructure->id,
                'amount_paid' => 7000,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'cash',
                'transaction_id' => 'MANUAL-LEDGER-1',
            ])
            ->assertRedirect();

        $olderDemand->refresh();
        $newerDemand->refresh();

        $this->assertSame('fully_paid', $olderDemand->status);
        $this->assertSame('0.00', $olderDemand->final_amount);
        $this->assertSame('0.00', $olderDemand->penalty_amount);
        $this->assertSame('partially_paid', $newerDemand->status);
        $this->assertSame('2500.00', $newerDemand->final_amount);
        $this->assertDatabaseHas('fee_payments', [
            'student_id' => $student->id,
            'transaction_id' => 'MANUAL-LEDGER-1',
            'amount_paid' => 7000,
            'status' => 'paid',
        ]);
    }

    public function test_verified_unlinked_payment_proof_reduces_active_fee_demands_oldest_first(): void
    {
        $admin = $this->admin();
        [, $student] = $this->makeStudent();
        $this->currentFeeStructureFor($student);

        $olderDemand = FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 5000,
            'penalty_amount' => 500,
            'due_date' => now()->subDays(10)->toDateString(),
            'status' => 'overdue',
        ]);
        $newerDemand = FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 4000,
            'penalty_amount' => 0,
            'due_date' => now()->addDays(10)->toDateString(),
            'status' => 'pending',
        ]);
        $proof = FeePaymentRequest::create([
            'student_id' => $student->id,
            'amount' => 7000,
            'payment_method' => 'online',
            'transaction_ref' => 'UNLINKED-PROOF-LEDGER-1',
            'submitted_at' => now(),
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.fees.payment-requests.verify', $proof), [
                'notes' => 'Matched to open demand ledger.',
            ])
            ->assertRedirect();

        $olderDemand->refresh();
        $newerDemand->refresh();

        $this->assertSame('verified', $proof->fresh()->status);
        $this->assertSame('fully_paid', $olderDemand->status);
        $this->assertSame('0.00', $olderDemand->final_amount);
        $this->assertSame('0.00', $olderDemand->penalty_amount);
        $this->assertSame('partially_paid', $newerDemand->status);
        $this->assertSame('2500.00', $newerDemand->final_amount);
        $this->assertDatabaseHas('fee_payments', [
            'student_id' => $student->id,
            'transaction_id' => 'UNLINKED-PROOF-LEDGER-1',
            'amount_paid' => 7000,
            'status' => 'paid',
        ]);
    }

    public function test_manual_admin_payment_cannot_exceed_active_fee_demand_balance(): void
    {
        $admin = $this->admin();
        [, $student] = $this->makeStudent();
        $feeStructure = $this->currentFeeStructureFor($student);
        $demand = FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 2000,
            'penalty_amount' => 100,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.fees.collect', ['student_id' => $student->id]))
            ->post(route('admin.fees.payment'), [
                'student_id' => $student->id,
                'fee_structure_id' => $feeStructure->id,
                'amount_paid' => 2101,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'cash',
                'transaction_id' => 'MANUAL-OVERPAY',
            ])
            ->assertRedirect(route('admin.fees.collect', ['student_id' => $student->id]))
            ->assertSessionHasErrors('amount_paid');

        $this->assertSame('pending', $demand->fresh()->status);
        $this->assertSame('2000.00', $demand->fresh()->final_amount);
        $this->assertSame(0, FeePayment::where('transaction_id', 'MANUAL-OVERPAY')->count());
    }

    public function test_manual_admin_payment_respects_pending_student_payment_proof_reservations(): void
    {
        $admin = $this->admin();
        [, $student] = $this->makeStudent();
        $feeStructure = $this->currentFeeStructureFor($student);
        $demand = FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 5000,
            'penalty_amount' => 0,
            'status' => 'pending',
        ]);

        FeePaymentRequest::create([
            'student_id' => $student->id,
            'fee_demand_id' => $demand->id,
            'amount' => 4000,
            'payment_method' => 'online',
            'transaction_ref' => 'PENDING-PROOF-RESERVES-MANUAL-BALANCE',
            'submitted_at' => now(),
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.fees.collect', ['student_id' => $student->id]))
            ->post(route('admin.fees.payment'), [
                'student_id' => $student->id,
                'fee_structure_id' => $feeStructure->id,
                'amount_paid' => 1001,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'cash',
                'transaction_id' => 'MANUAL-PENDING-PROOF-OVERPAY',
            ])
            ->assertRedirect(route('admin.fees.collect', ['student_id' => $student->id]))
            ->assertSessionHasErrors('amount_paid');

        $this->assertSame('pending', $demand->fresh()->status);
        $this->assertSame('5000.00', $demand->fresh()->final_amount);
        $this->assertSame(0, FeePayment::where('transaction_id', 'MANUAL-PENDING-PROOF-OVERPAY')->count());

        $this->actingAs($admin)
            ->from(route('admin.fees.collect', ['student_id' => $student->id]))
            ->post(route('admin.fees.payment'), [
                'student_id' => $student->id,
                'fee_structure_id' => $feeStructure->id,
                'amount_paid' => 1000,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'cash',
                'transaction_id' => 'MANUAL-PENDING-PROOF-AVAILABLE-BALANCE',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('fee_payments', [
            'student_id' => $student->id,
            'transaction_id' => 'MANUAL-PENDING-PROOF-AVAILABLE-BALANCE',
            'amount_paid' => 1000,
            'status' => 'paid',
        ]);
    }

    public function test_manual_admin_payment_without_demands_cannot_exceed_remaining_fee_structure_balance(): void
    {
        $admin = $this->admin();
        [, $student] = $this->makeStudent();
        $feeStructure = $this->currentFeeStructureFor($student);

        FeePayment::create([
            'student_id' => $student->id,
            'fee_structure_id' => $feeStructure->id,
            'amount_paid' => 49000,
            'payment_date' => now()->subDay()->toDateString(),
            'receipt_number' => 'RCP-LEGACY-BALANCE-PAID',
            'payment_method' => 'cash',
            'transaction_id' => 'LEGACY-BALANCE-PAID',
            'status' => 'paid',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.fees.collect', ['student_id' => $student->id]))
            ->post(route('admin.fees.payment'), [
                'student_id' => $student->id,
                'fee_structure_id' => $feeStructure->id,
                'amount_paid' => 1001,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'cash',
                'transaction_id' => 'MANUAL-LEGACY-OVERPAY',
            ])
            ->assertRedirect(route('admin.fees.collect', ['student_id' => $student->id]))
            ->assertSessionHasErrors('amount_paid');

        $this->assertSame(0, FeePayment::where('transaction_id', 'MANUAL-LEGACY-OVERPAY')->count());

        $this->actingAs($admin)
            ->from(route('admin.fees.collect', ['student_id' => $student->id]))
            ->post(route('admin.fees.payment'), [
                'student_id' => $student->id,
                'fee_structure_id' => $feeStructure->id,
                'amount_paid' => 1000,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'cash',
                'transaction_id' => 'MANUAL-LEGACY-BALANCE-CLOSE',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('fee_payments', [
            'student_id' => $student->id,
            'fee_structure_id' => $feeStructure->id,
            'amount_paid' => 1000,
            'transaction_id' => 'MANUAL-LEGACY-BALANCE-CLOSE',
            'status' => 'paid',
        ]);
    }

    public function test_manual_admin_payment_cannot_reuse_paid_transaction_reference(): void
    {
        $admin = $this->admin();
        [, $student] = $this->makeStudent();
        $feeStructure = $this->currentFeeStructureFor($student);
        $demand = FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 5000,
            'penalty_amount' => 0,
            'status' => 'pending',
        ]);

        FeePayment::create([
            'student_id' => $student->id,
            'fee_structure_id' => $feeStructure->id,
            'amount_paid' => 1000,
            'payment_date' => now()->toDateString(),
            'receipt_number' => 'RCP-MANUAL-DUP-REF',
            'payment_method' => 'online',
            'transaction_id' => 'MANUAL-DUP-UTR',
            'status' => 'paid',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.fees.collect', ['student_id' => $student->id]))
            ->post(route('admin.fees.payment'), [
                'student_id' => $student->id,
                'fee_structure_id' => $feeStructure->id,
                'amount_paid' => 2500,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'upi',
                'transaction_id' => ' manual-dup-utr ',
            ])
            ->assertRedirect(route('admin.fees.collect', ['student_id' => $student->id]))
            ->assertSessionHasErrors('transaction_id');

        $demand->refresh();
        $this->assertSame('pending', $demand->status);
        $this->assertSame('5000.00', $demand->final_amount);
        $this->assertSame(1, FeePayment::where('transaction_id', 'MANUAL-DUP-UTR')->count());
        $this->assertDatabaseMissing('fee_payments', [
            'student_id' => $student->id,
            'amount_paid' => 2500,
            'transaction_id' => ' manual-dup-utr ',
            'status' => 'paid',
        ]);
    }

    public function test_manual_admin_payment_requires_active_student_active_course_structure_and_positive_amount(): void
    {
        $admin = $this->admin();
        [, $student] = $this->makeStudent();
        $feeStructure = $this->currentFeeStructureFor($student);

        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 5000,
            'penalty_amount' => 0,
            'status' => 'pending',
        ]);

        $student->update(['status' => 'inactive']);

        $this->actingAs($admin)
            ->from(route('admin.fees.collect', ['student_id' => $student->id]))
            ->post(route('admin.fees.payment'), [
                'student_id' => $student->id,
                'fee_structure_id' => $feeStructure->id,
                'amount_paid' => 1000,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'cash',
                'transaction_id' => 'MANUAL-INACTIVE-STUDENT',
            ])
            ->assertRedirect(route('admin.fees.collect', ['student_id' => $student->id]))
            ->assertSessionHasErrors('student_id');

        $this->assertSame(0, FeePayment::where('transaction_id', 'MANUAL-INACTIVE-STUDENT')->count());

        $student->update(['status' => 'active']);
        $student->course->update(['is_active' => false]);

        $this->actingAs($admin)
            ->from(route('admin.fees.collect', ['student_id' => $student->id]))
            ->post(route('admin.fees.payment'), [
                'student_id' => $student->id,
                'fee_structure_id' => $feeStructure->id,
                'amount_paid' => 1000,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'cash',
                'transaction_id' => 'MANUAL-INACTIVE-COURSE',
            ])
            ->assertRedirect(route('admin.fees.collect', ['student_id' => $student->id]))
            ->assertSessionHasErrors('fee_structure_id');

        $this->assertSame(0, FeePayment::where('transaction_id', 'MANUAL-INACTIVE-COURSE')->count());

        $student->course->update(['is_active' => true]);

        $this->actingAs($admin)
            ->from(route('admin.fees.collect', ['student_id' => $student->id]))
            ->post(route('admin.fees.payment'), [
                'student_id' => $student->id,
                'fee_structure_id' => $feeStructure->id,
                'amount_paid' => 0,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'cash',
                'transaction_id' => 'MANUAL-ZERO',
            ])
            ->assertRedirect(route('admin.fees.collect', ['student_id' => $student->id]))
            ->assertSessionHasErrors('amount_paid');

        $this->assertSame(0, FeePayment::where('transaction_id', 'MANUAL-ZERO')->count());
    }

    public function test_fee_structure_create_and_update_require_active_course(): void
    {
        $admin = $this->admin();
        $activeCourse = Course::factory()->create(['is_active' => true]);
        $inactiveCourse = Course::factory()->create(['is_active' => false]);
        $academicYear = AcademicYear::create([
            'name' => '2028-29',
            'start_year' => 2028,
            'end_year' => 2029,
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
            'is_current' => false,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.fees.create'))
            ->post(route('admin.fees.store'), [
                'course_id' => $inactiveCourse->id,
                'academic_year_id' => $academicYear->id,
                'fee_type' => 'Archived Course Fee',
                'amount' => 10000,
                'semester_number' => 1,
            ])
            ->assertRedirect(route('admin.fees.create'))
            ->assertSessionHasErrors('course_id');

        $this->assertDatabaseMissing('fee_structures', [
            'course_id' => $inactiveCourse->id,
            'fee_type' => 'Archived Course Fee',
        ]);

        $feeStructure = FeeStructure::create([
            'course_id' => $activeCourse->id,
            'academic_year_id' => $academicYear->id,
            'fee_type' => 'Active Course Fee',
            'amount' => 12000,
            'semester_number' => 1,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.fees.edit', $feeStructure))
            ->put(route('admin.fees.update', $feeStructure), [
                'course_id' => $inactiveCourse->id,
                'academic_year_id' => $academicYear->id,
                'fee_type' => 'Moved To Archived Course',
                'amount' => 15000,
                'semester_number' => 1,
            ])
            ->assertRedirect(route('admin.fees.edit', $feeStructure))
            ->assertSessionHasErrors('course_id');

        $feeStructure->refresh();
        $this->assertSame($activeCourse->id, $feeStructure->course_id);
        $this->assertSame('Active Course Fee', $feeStructure->fee_type);
        $this->assertSame('12000.00', $feeStructure->amount);
    }

    public function test_fee_structure_linked_to_receipts_cannot_be_deleted_or_financially_changed(): void
    {
        $admin = $this->admin();
        [, $student] = $this->makeStudent();
        $feeStructure = $this->currentFeeStructureFor($student);
        $otherCourse = Course::factory()->create();

        FeePayment::create([
            'student_id' => $student->id,
            'fee_structure_id' => $feeStructure->id,
            'amount_paid' => 5000,
            'payment_date' => now()->toDateString(),
            'receipt_number' => 'RCP-LOCKED-STRUCTURE',
            'payment_method' => 'cash',
            'status' => 'paid',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.fees.destroy', $feeStructure))
            ->assertRedirect(route('admin.fees.index'))
            ->assertSessionHas('error', 'This fee structure is linked to fee receipt history and cannot be deleted.');

        $this->assertDatabaseHas('fee_structures', ['id' => $feeStructure->id]);
        $this->assertDatabaseHas('fee_payments', ['receipt_number' => 'RCP-LOCKED-STRUCTURE']);

        $this->actingAs($admin)
            ->put(route('admin.fees.update', $feeStructure), [
                'course_id' => $otherCourse->id,
                'academic_year_id' => $feeStructure->academic_year_id,
                'fee_type' => 'Changed Tuition',
                'amount' => 75000,
                'semester_number' => 2,
            ])
            ->assertSessionHasErrors('fee_structure');

        $feeStructure->refresh();
        $this->assertSame($student->course_id, $feeStructure->course_id);
        $this->assertSame('Tuition', $feeStructure->fee_type);
        $this->assertSame('50000.00', $feeStructure->amount);
        $this->assertNull($feeStructure->semester_number);
    }

    public function test_fee_receipt_pdf_is_available_only_for_paid_fee_payments(): void
    {
        [$studentUser, $student] = $this->makeStudent();
        $admin = $this->admin();
        $feeStructure = $this->currentFeeStructureFor($student);

        $paid = FeePayment::create([
            'student_id' => $student->id,
            'fee_structure_id' => $feeStructure->id,
            'amount_paid' => 12345,
            'payment_date' => now()->toDateString(),
            'receipt_number' => 'RCP-PAID-PDF',
            'payment_method' => 'cash',
            'status' => 'paid',
        ]);
        $pending = FeePayment::create([
            'student_id' => $student->id,
            'fee_structure_id' => $feeStructure->id,
            'amount_paid' => 9999,
            'payment_date' => now()->toDateString(),
            'receipt_number' => 'RCP-PENDING-PDF',
            'payment_method' => 'cash',
            'status' => 'pending',
        ]);

        $adminReceipt = $this->actingAs($admin)
            ->get(route('admin.reports.fee-receipt', $paid))
            ->assertOk();
        $this->assertSame('application/pdf', $adminReceipt->headers->get('content-type'));

        $this->actingAs($admin)
            ->get(route('admin.reports.fee-receipt', $pending))
            ->assertNotFound();

        $this->actingAs($admin)
            ->get(route('admin.fees.receipt', $paid))
            ->assertOk()
            ->assertSee('RCP-PAID-PDF');

        $this->actingAs($admin)
            ->get(route('admin.fees.receipt', $pending))
            ->assertNotFound();

        $studentReceipt = $this->actingAs($studentUser)
            ->get(route('student.reports.fee-receipt', $paid))
            ->assertOk();
        $this->assertSame('application/pdf', $studentReceipt->headers->get('content-type'));

        $this->actingAs($studentUser)
            ->get(route('student.reports.fee-receipt', $pending))
            ->assertNotFound();
    }

    public function test_unused_fee_structure_remains_editable_and_deletable(): void
    {
        $admin = $this->admin();
        $academicYear = AcademicYear::create([
            'name' => '2027-28',
            'start_year' => 2027,
            'end_year' => 2028,
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
            'is_current' => false,
        ]);
        $course = Course::factory()->create();
        $feeStructure = FeeStructure::create([
            'course_id' => $course->id,
            'academic_year_id' => $academicYear->id,
            'fee_type' => 'Activity Fee',
            'amount' => 3000,
            'semester_number' => 1,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.fees.update', $feeStructure), [
                'course_id' => $course->id,
                'academic_year_id' => $academicYear->id,
                'fee_type' => 'Student Activity Fee',
                'amount' => 3500,
                'semester_number' => 1,
            ])
            ->assertRedirect(route('admin.fees.index'));

        $this->assertSame('Student Activity Fee', $feeStructure->fresh()->fee_type);
        $this->assertSame('3500.00', $feeStructure->fresh()->amount);

        $this->actingAs($admin)
            ->delete(route('admin.fees.destroy', $feeStructure))
            ->assertRedirect(route('admin.fees.index'))
            ->assertSessionHas('success', 'Deleted.');

        $this->assertDatabaseMissing('fee_structures', ['id' => $feeStructure->id]);
    }
}
