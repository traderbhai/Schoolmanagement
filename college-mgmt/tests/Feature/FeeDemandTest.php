<?php

namespace Tests\Feature;

use App\Models\FeeDemand;
use App\Models\FeePaymentRequest;
use App\Models\Applicant;
use App\Models\ApplicantScholarship;
use App\Models\AcademicYear;
use App\Models\Batch;
use App\Models\Course;
use App\Models\EnrollmentConfirmation;
use App\Models\FeeStructure;
use App\Models\Program;
use App\Models\ScholarshipScheme;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FeeDemandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);
    }

    public function test_can_view_fee_demands_list()
    {
        FeeDemand::factory()->count(5)->create();

        $response = $this->get('/academic/fee-demands');

        $response->assertStatus(200);
    }

    public function test_can_create_fee_demand()
    {
        $student = Student::factory()->create();
        $term = Term::factory()->create();

        $data = [
            'student_id' => $student->id,
            'term_id' => $term->id,
            'total_amount' => 100000,
            'scholarship_deduction' => 10000,
            'due_date' => now()->addMonth(),
            'status' => 'pending',
        ];

        $response = $this->post('/academic/fee-demands', $data);

        $this->assertDatabaseHas('fee_demands', ['student_id' => $student->id]);
    }

    public function test_can_view_fee_demand_details()
    {
        $feeDemand = FeeDemand::factory()->create();

        $response = $this->get("/academic/fee-demands/{$feeDemand->id}");

        $response->assertStatus(200);
    }

    public function test_fee_demand_pages_use_readable_finance_fallbacks(): void
    {
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());

        $student = Student::factory()->make(['enrollment_number' => null]);
        $student->setRelation('user', null);
        $student->setRelation('program', null);

        $feeDemand = FeeDemand::factory()->make([
            'total_amount' => 50000,
            'scholarship_deduction' => 5000,
            'final_amount' => 45000,
            'penalty_amount' => 0,
            'due_date' => null,
            'status' => 'pending',
        ]);
        $feeDemand->id = 9876;
        $feeDemand->setRelation('student', $student);
        $feeDemand->setRelation('term', null);

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(collect([$feeDemand]), 1, 15);
        $listHtml = view('academic.fee-demands.index', [
            'feeDemands' => $paginator,
            'batches' => collect(),
            'terms' => collect(),
        ])->render();

        $detailHtml = view('academic.fee-demands.show', [
            'feeDemand' => $feeDemand,
            'payments' => collect([
                (object) [
                    'payment_date' => null,
                    'amount_paid' => 2500,
                    'payment_method' => null,
                    'status' => 'pending',
                ],
            ]),
        ])->render();

        foreach ([$listHtml, $detailHtml] as $html) {
            $this->assertStringContainsString('Rs.', $html);
            $this->assertStringContainsString('Due date not published', $html);
            $this->assertStringContainsString('No penalty', $html);
            $this->assertStringNotContainsString('N/A', $html);
            $this->assertStringNotContainsString('â', $html);
            $this->assertStringNotContainsString('&mdash;', $html);
            $this->assertStringNotContainsString('&ndash;', $html);
        }

        $this->assertStringContainsString('Enrollment number pending', $listHtml);
        $this->assertStringContainsString('Term not linked', $listHtml);
        $this->assertStringContainsString('Student name missing', $detailHtml);
        $this->assertStringContainsString('Program not linked', $detailHtml);
        $this->assertStringContainsString('Payment date pending', $detailHtml);
        $this->assertStringContainsString('Payment method pending', $detailHtml);
    }

    public function test_can_update_fee_demand()
    {
        $feeDemand = FeeDemand::factory()->create(['status' => 'pending']);

        $data = [
            'total_amount' => 150000,
            'scholarship_deduction' => 15000,
            'due_date' => now()->addMonth(),
            'status' => 'pending',
        ];

        $response = $this->put("/academic/fee-demands/{$feeDemand->id}", $data);

        $this->assertEquals(150000, $feeDemand->fresh()->total_amount);
    }

    public function test_can_mark_zero_balance_demand_as_paid()
    {
        $feeDemand = FeeDemand::factory()->create([
            'status' => 'pending',
            'total_amount' => 10000,
            'scholarship_deduction' => 10000,
            'final_amount' => 0,
            'penalty_amount' => 0,
        ]);

        $response = $this->post("/academic/fee-demands/{$feeDemand->id}/mark-paid");

        $this->assertEquals('fully_paid', $feeDemand->fresh()->status);
    }

    public function test_cannot_manually_mark_non_zero_demand_as_paid()
    {
        $feeDemand = FeeDemand::factory()->create([
            'status' => 'pending',
            'final_amount' => 10000,
            'penalty_amount' => 0,
        ]);

        $this->post("/academic/fee-demands/{$feeDemand->id}/mark-paid")
            ->assertSessionHasErrors('fee_demand');

        $this->assertEquals('pending', $feeDemand->fresh()->status);
    }

    public function test_cannot_create_or_update_fee_demand_as_partially_paid_without_payment_activity(): void
    {
        $student = Student::factory()->create(['status' => 'active']);
        $term = Term::factory()->create();

        $this->post('/academic/fee-demands', [
            'student_id' => $student->id,
            'term_id' => $term->id,
            'total_amount' => 10000,
            'scholarship_deduction' => 0,
            'due_date' => now()->addMonth()->toDateString(),
            'status' => 'partially_paid',
        ])->assertSessionHasErrors('status');

        $this->assertDatabaseMissing('fee_demands', [
            'student_id' => $student->id,
            'term_id' => $term->id,
        ]);

        $feeDemand = FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'status' => 'pending',
            'total_amount' => 10000,
            'scholarship_deduction' => 0,
            'final_amount' => 10000,
            'penalty_amount' => 0,
        ]);

        $this->put("/academic/fee-demands/{$feeDemand->id}", [
            'total_amount' => 10000,
            'scholarship_deduction' => 0,
            'due_date' => now()->addMonth()->toDateString(),
            'status' => 'partially_paid',
        ])->assertSessionHasErrors('status');

        $this->assertSame('pending', $feeDemand->fresh()->status);
    }

    public function test_inactive_student_zero_balance_fee_demand_cannot_be_manually_marked_paid(): void
    {
        $student = Student::factory()->create(['status' => 'inactive']);
        $feeDemand = FeeDemand::factory()->create([
            'student_id' => $student->id,
            'status' => 'pending',
            'total_amount' => 10000,
            'scholarship_deduction' => 10000,
            'final_amount' => 0,
            'penalty_amount' => 0,
        ]);

        $this->post("/academic/fee-demands/{$feeDemand->id}/mark-paid")
            ->assertRedirect()
            ->assertSessionHas('error', 'Fee demands for inactive or archived students cannot be manually marked paid from the standard fee-demand workflow.');

        $this->assertSame('pending', $feeDemand->fresh()->status);
    }

    public function test_cannot_update_non_zero_demand_directly_to_fully_paid()
    {
        $feeDemand = FeeDemand::factory()->create(['status' => 'pending']);

        $this->put("/academic/fee-demands/{$feeDemand->id}", [
            'total_amount' => 150000,
            'scholarship_deduction' => 15000,
            'due_date' => now()->addMonth()->toDateString(),
            'status' => 'fully_paid',
        ])->assertSessionHasErrors('status');

        $this->assertNotEquals('fully_paid', $feeDemand->fresh()->status);
    }

    public function test_pending_fee_demand_delete_preserves_cancellation_history()
    {
        $feeDemand = FeeDemand::factory()->create(['status' => 'pending']);

        $this->delete("/academic/fee-demands/{$feeDemand->id}")
            ->assertRedirect('/academic/fee-demands')
            ->assertSessionHas('success', 'Fee demand cancelled and retained for audit.');

        $this->assertSoftDeleted('fee_demands', ['id' => $feeDemand->id]);
        $this->assertDatabaseHas('fee_demands', [
            'id' => $feeDemand->id,
            'cancelled_by' => auth()->id(),
            'cancellation_reason' => 'Cancelled before payment activity.',
        ]);
        $this->assertNotNull(FeeDemand::withTrashed()->find($feeDemand->id)->cancelled_at);
    }

    public function test_cannot_delete_paid_or_linked_fee_demand_history()
    {
        $paid = FeeDemand::factory()->create(['status' => 'fully_paid']);
        $linked = FeeDemand::factory()->create(['status' => 'pending']);
        FeePaymentRequest::create([
            'student_id' => $linked->student_id,
            'fee_demand_id' => $linked->id,
            'amount' => 1000,
            'payment_method' => 'online',
            'transaction_ref' => 'LINKED-FEE-DEMAND',
            'submitted_at' => now(),
            'status' => 'pending',
        ]);

        $this->delete("/academic/fee-demands/{$paid->id}")
            ->assertSessionHas('error', 'Only untouched pending fee demands can be deleted. Paid, partial, overdue, or closed demands are retained for financial audit.');
        $this->delete("/academic/fee-demands/{$linked->id}")
            ->assertSessionHas('error', 'Cannot delete this fee demand because payment requests or installment records are linked to it.');

        $this->assertDatabaseHas('fee_demands', ['id' => $paid->id]);
        $this->assertDatabaseHas('fee_demands', ['id' => $linked->id]);
    }

    public function test_apply_penalties_marks_active_overdue_pending_and_partial_demands_overdue(): void
    {
        $activeStudent = Student::factory()->create(['status' => 'active']);
        $pending = FeeDemand::factory()->create([
            'student_id' => $activeStudent->id,
            'status' => 'pending',
            'final_amount' => 10000,
            'penalty_amount' => 0,
            'due_date' => now()->subDays(45)->toDateString(),
        ]);
        $partial = FeeDemand::factory()->create([
            'student_id' => $activeStudent->id,
            'status' => 'partially_paid',
            'final_amount' => 5000,
            'penalty_amount' => 0,
            'due_date' => now()->subDays(10)->toDateString(),
        ]);

        $this->post(route('academic.fee-demands.apply-penalties'))
            ->assertRedirect()
            ->assertSessionHas('success', 'Penalties applied to 2 overdue demands.');

        $pending->refresh();
        $partial->refresh();

        $this->assertSame('overdue', $pending->status);
        $this->assertSame('200.00', $pending->penalty_amount);
        $this->assertSame('overdue', $partial->status);
        $this->assertSame('100.00', $partial->penalty_amount);
    }

    public function test_apply_penalties_skips_inactive_zero_balance_paid_and_already_penalized_demands(): void
    {
        $activeStudent = Student::factory()->create(['status' => 'active']);
        $inactiveStudent = Student::factory()->create(['status' => 'inactive']);
        $inactive = FeeDemand::factory()->create([
            'student_id' => $inactiveStudent->id,
            'status' => 'pending',
            'final_amount' => 10000,
            'penalty_amount' => 0,
            'due_date' => now()->subDays(35)->toDateString(),
        ]);
        $zeroBalance = FeeDemand::factory()->create([
            'student_id' => $activeStudent->id,
            'status' => 'pending',
            'final_amount' => 0,
            'penalty_amount' => 0,
            'due_date' => now()->subDays(35)->toDateString(),
        ]);
        $paid = FeeDemand::factory()->create([
            'student_id' => $activeStudent->id,
            'status' => 'fully_paid',
            'final_amount' => 10000,
            'penalty_amount' => 0,
            'due_date' => now()->subDays(35)->toDateString(),
        ]);
        $alreadyPenalized = FeeDemand::factory()->create([
            'student_id' => $activeStudent->id,
            'status' => 'pending',
            'final_amount' => 10000,
            'penalty_amount' => 250,
            'due_date' => now()->subDays(35)->toDateString(),
        ]);

        $this->post(route('academic.fee-demands.apply-penalties'))
            ->assertRedirect()
            ->assertSessionHas('success', 'Penalties applied to 0 overdue demands.');

        $this->assertSame('pending', $inactive->fresh()->status);
        $this->assertSame('0.00', $inactive->fresh()->penalty_amount);
        $this->assertSame('pending', $zeroBalance->fresh()->status);
        $this->assertSame('0.00', $zeroBalance->fresh()->penalty_amount);
        $this->assertSame('fully_paid', $paid->fresh()->status);
        $this->assertSame('0.00', $paid->fresh()->penalty_amount);
        $this->assertSame('pending', $alreadyPenalized->fresh()->status);
        $this->assertSame('250.00', $alreadyPenalized->fresh()->penalty_amount);
    }

    public function test_fee_demand_is_overdue()
    {
        $feeDemand = FeeDemand::factory()->create([
            'due_date' => now()->subDay(),
            'status' => 'pending',
        ]);

        $this->assertTrue($feeDemand->isOverdue());
    }

    public function test_fee_demand_not_overdue_when_paid()
    {
        $feeDemand = FeeDemand::factory()->create([
            'due_date' => now()->subDay(),
            'status' => 'fully_paid',
        ]);

        $this->assertFalse($feeDemand->isOverdue());
    }

    public function test_final_amount_calculated_correctly()
    {
        $feeDemand = FeeDemand::factory()->create([
            'total_amount' => 100000,
            'scholarship_deduction' => 20000,
            'final_amount' => 80000,
        ]);

        $this->assertEquals('80000.00', $feeDemand->fresh()->final_amount);
    }

    public function test_manual_fee_demand_rejects_scholarship_deduction_above_total_amount(): void
    {
        $student = Student::factory()->create();
        $term = Term::factory()->create();

        $this->post('/academic/fee-demands', [
            'student_id' => $student->id,
            'term_id' => $term->id,
            'total_amount' => 10000,
            'scholarship_deduction' => 12000,
            'due_date' => now()->addMonth()->toDateString(),
            'status' => 'pending',
        ])->assertSessionHasErrors('scholarship_deduction');

        $this->assertDatabaseMissing('fee_demands', [
            'student_id' => $student->id,
            'term_id' => $term->id,
        ]);
    }

    public function test_manual_fee_demand_cannot_be_created_as_fully_paid_with_open_balance(): void
    {
        $student = Student::factory()->create();
        $term = Term::factory()->create();

        $this->post('/academic/fee-demands', [
            'student_id' => $student->id,
            'term_id' => $term->id,
            'total_amount' => 10000,
            'scholarship_deduction' => 1000,
            'due_date' => now()->addMonth()->toDateString(),
            'status' => 'fully_paid',
        ])->assertSessionHasErrors('status');

        $this->assertDatabaseMissing('fee_demands', [
            'student_id' => $student->id,
            'term_id' => $term->id,
        ]);
    }

    public function test_manual_fee_demand_requires_active_student(): void
    {
        $student = Student::factory()->create(['status' => 'inactive']);
        $term = Term::factory()->create();

        $this->post('/academic/fee-demands', [
            'student_id' => $student->id,
            'term_id' => $term->id,
            'total_amount' => 10000,
            'scholarship_deduction' => 0,
            'due_date' => now()->addMonth()->toDateString(),
            'status' => 'pending',
        ])->assertSessionHasErrors('student_id');

        $this->assertDatabaseMissing('fee_demands', [
            'student_id' => $student->id,
            'term_id' => $term->id,
        ]);
    }

    public function test_manual_fee_demand_cannot_duplicate_student_term(): void
    {
        $student = Student::factory()->create(['status' => 'active']);
        $term = Term::factory()->create();
        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'status' => 'pending',
        ]);

        $this->post('/academic/fee-demands', [
            'student_id' => $student->id,
            'term_id' => $term->id,
            'total_amount' => 12000,
            'scholarship_deduction' => 0,
            'due_date' => now()->addMonth()->toDateString(),
            'status' => 'pending',
        ])->assertSessionHasErrors('term_id');

        $this->assertSame(1, FeeDemand::where('student_id', $student->id)->where('term_id', $term->id)->count());
    }

    public function test_fee_demand_with_financial_activity_cannot_have_ledger_fields_rewritten(): void
    {
        $feeDemand = FeeDemand::factory()->create([
            'status' => 'pending',
            'total_amount' => 10000,
            'scholarship_deduction' => 0,
            'final_amount' => 10000,
            'penalty_amount' => 0,
            'due_date' => now()->addMonth()->toDateString(),
        ]);
        FeePaymentRequest::create([
            'student_id' => $feeDemand->student_id,
            'fee_demand_id' => $feeDemand->id,
            'amount' => 5000,
            'payment_method' => 'online',
            'transaction_ref' => 'LOCK-DEMAND-EDIT',
            'submitted_at' => now(),
            'status' => 'pending',
        ]);

        $this->put("/academic/fee-demands/{$feeDemand->id}", [
            'total_amount' => 6000,
            'scholarship_deduction' => 0,
            'due_date' => now()->addMonths(2)->toDateString(),
            'status' => 'pending',
        ])->assertSessionHasErrors('fee_demand');

        $feeDemand->refresh();
        $this->assertSame('10000.00', $feeDemand->total_amount);
        $this->assertSame('10000.00', $feeDemand->final_amount);
        $this->assertSame('pending', $feeDemand->status);
    }

    public function test_generated_fee_demand_applies_admission_scholarship_from_completed_handoff(): void
    {
        $program = Program::factory()->create(['is_active' => true]);
        $course = Course::factory()->create();
        $year = AcademicYear::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id, 'status' => 'active']);
        $term = Term::factory()->create();
        $student = Student::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
        FeeStructure::create([
            'course_id' => $course->id,
            'academic_year_id' => $year->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'fee_type' => 'tuition',
            'amount' => 60000,
            'semester_number' => 1,
        ]);
        FeeStructure::create([
            'course_id' => $course->id,
            'academic_year_id' => $year->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'fee_type' => 'library',
            'amount' => 10000,
            'semester_number' => 1,
        ]);

        $applicant = Applicant::factory()->create([
            'user_id' => $student->user_id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'selected',
        ]);
        EnrollmentConfirmation::create([
            'applicant_id' => $applicant->id,
            'student_id' => $student->id,
            'confirmed_by' => auth()->id(),
            'confirmed_at' => now(),
            'enrollment_number' => $student->enrollment_number,
            'roll_number' => $student->roll_number,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'status' => 'completed',
        ]);
        $scheme = ScholarshipScheme::create([
            'name' => 'Admission Award',
            'scheme_code' => 'ADM-AWARD',
            'type' => 'merit',
            'max_amount' => 25000,
            'available_seats' => 1,
            'is_active' => true,
        ]);
        ApplicantScholarship::create([
            'applicant_id' => $applicant->id,
            'scheme_id' => $scheme->id,
            'awarded_amount' => 25000,
            'status' => 'awarded',
            'awarded_by' => auth()->id(),
            'awarded_at' => now(),
        ]);

        $this->post(route('academic.fee-demands.generate'), [
            'batch_id' => $batch->id,
            'term_id' => $term->id,
        ])->assertRedirect()
            ->assertSessionHas('success', 'Fee demands generated: 1 created, 0 already existed.');

        $this->assertDatabaseHas('fee_demands', [
            'student_id' => $student->id,
            'term_id' => $term->id,
            'total_amount' => 70000,
            'scholarship_deduction' => 25000,
            'final_amount' => 45000,
            'status' => 'pending',
        ]);
    }

    public function test_generated_fee_demand_caps_admission_scholarship_at_total_fee(): void
    {
        $program = Program::factory()->create(['is_active' => true]);
        $course = Course::factory()->create();
        $year = AcademicYear::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id, 'status' => 'active']);
        $term = Term::factory()->create();
        $student = Student::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
        FeeStructure::create([
            'course_id' => $course->id,
            'academic_year_id' => $year->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'fee_type' => 'tuition',
            'amount' => 30000,
            'semester_number' => 1,
        ]);
        $applicant = Applicant::factory()->create([
            'user_id' => $student->user_id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
        ]);
        EnrollmentConfirmation::create([
            'applicant_id' => $applicant->id,
            'student_id' => $student->id,
            'confirmed_by' => auth()->id(),
            'confirmed_at' => now(),
            'enrollment_number' => $student->enrollment_number,
            'roll_number' => $student->roll_number,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'status' => 'completed',
        ]);
        $scheme = ScholarshipScheme::create([
            'name' => 'Full Award',
            'scheme_code' => 'FULL-AWARD',
            'type' => 'merit',
            'max_amount' => 50000,
            'is_active' => true,
        ]);
        ApplicantScholarship::create([
            'applicant_id' => $applicant->id,
            'scheme_id' => $scheme->id,
            'awarded_amount' => 50000,
            'status' => 'disbursed',
            'awarded_by' => auth()->id(),
            'awarded_at' => now(),
            'disbursed_at' => now(),
        ]);

        $this->post(route('academic.fee-demands.generate'), [
            'batch_id' => $batch->id,
            'term_id' => $term->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('fee_demands', [
            'student_id' => $student->id,
            'term_id' => $term->id,
            'total_amount' => 30000,
            'scholarship_deduction' => 30000,
            'final_amount' => 0,
        ]);
    }
}
