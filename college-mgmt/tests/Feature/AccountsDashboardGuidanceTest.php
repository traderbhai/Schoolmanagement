<?php

namespace Tests\Feature;

use App\Models\AdmissionFeeInstallment;
use App\Models\AdmissionPayment;
use App\Models\Applicant;
use App\Models\ActivityLog;
use App\Models\FeeDemand;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\Program;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccountsDashboardGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private function accountsUser(): User
    {
        Role::firstOrCreate(['name' => 'accounts_officer', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('accounts_officer');

        return $user;
    }

    public function test_accounts_dashboard_uses_fee_demands_for_outstanding_totals(): void
    {
        $user = $this->accountsUser();
        $student = Student::factory()->create();
        $term = Term::factory()->create(['program_id' => $student->program_id]);

        FeeStructure::create([
            'course_id' => $student->course_id,
            'academic_year_id' => \App\Models\AcademicYear::factory()->create()->id,
            'fee_type' => 'Legacy structure',
            'amount' => 999999,
        ]);

        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'total_amount' => 50000,
            'scholarship_deduction' => 0,
            'final_amount' => 50000,
            'penalty_amount' => 5000,
            'status' => 'overdue',
        ]);
        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'total_amount' => 20000,
            'scholarship_deduction' => 0,
            'final_amount' => 20000,
            'penalty_amount' => 0,
            'status' => 'fully_paid',
        ]);

        $this->actingAs($user)
            ->get(route('accounts.dashboard'))
            ->assertStatus(200)
            ->assertSee('Follow up on 1 overdue fee demand')
            ->assertSee('Review Overdue Demands')
            ->assertSee(route('accounts.outstanding', ['mode' => 'overdue_demands']), false)
            ->assertSee('Rs. 55,000')
            ->assertDontSee('999,999');
    }

    public function test_accounts_dashboard_primary_kpis_link_to_source_finance_lists(): void
    {
        $user = $this->accountsUser();
        $student = Student::factory()->create();
        $term = Term::factory()->create(['program_id' => $student->program_id]);
        $feeStructure = FeeStructure::create([
            'course_id' => $student->course_id,
            'program_id' => $student->program_id,
            'academic_year_id' => \App\Models\AcademicYear::factory()->create()->id,
            'fee_type' => 'Tuition',
            'amount' => 25000,
        ]);

        FeePayment::create([
            'student_id' => $student->id,
            'fee_structure_id' => $feeStructure->id,
            'amount_paid' => 12000,
            'payment_date' => now()->toDateString(),
            'receipt_number' => 'ACC-DASH-LINK',
            'payment_method' => 'cash',
            'status' => 'paid',
        ]);
        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'total_amount' => 25000,
            'scholarship_deduction' => 0,
            'final_amount' => 25000,
            'penalty_amount' => 500,
            'due_date' => now()->subDays(2)->toDateString(),
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->get(route('accounts.dashboard'))
            ->assertOk()
            ->assertSee('Open demand report')
            ->assertSee(route('accounts.reports'), false)
            ->assertSee('Open paid collections')
            ->assertSee(route('accounts.fee-collections', ['status' => 'paid']), false)
            ->assertSee('Open outstanding list')
            ->assertSee(route('accounts.outstanding'), false)
            ->assertSee('Open overdue queue')
            ->assertSee(route('accounts.outstanding', ['mode' => 'overdue_demands']), false);
    }

    public function test_accounts_dashboard_prioritizes_pending_admission_payment_verification(): void
    {
        $user = $this->accountsUser();
        $applicantUser = User::factory()->create();
        $program = Program::factory()->create(['is_active' => true]);
        $applicant = Applicant::factory()->create([
            'user_id' => $applicantUser->id,
            'program_id' => $program->id,
        ]);
        $installment = AdmissionFeeInstallment::create([
            'program_id' => $program->id,
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
            'transaction_reference' => 'ACC-PENDING-001',
            'status' => 'pending',
            'submitted_by' => $applicantUser->id,
        ]);

        $this->actingAs($user)
            ->get(route('accounts.dashboard'))
            ->assertStatus(200)
            ->assertSee('Verify 1 admission payment')
            ->assertSee('Verify Payments')
            ->assertSee(route('accounts.admission-payments'), false);
    }

    public function test_accounts_dashboard_has_clear_empty_priority_state(): void
    {
        $user = $this->accountsUser();

        $this->actingAs($user)
            ->get(route('accounts.dashboard'))
            ->assertStatus(200)
            ->assertSee('No urgent finance action due today')
            ->assertSee('Reconcile Receipts')
            ->assertSee('Financial Reports');
    }

    public function test_accounts_outstanding_page_uses_active_fee_demands(): void
    {
        $user = $this->accountsUser();
        $student = Student::factory()->create();
        $term = Term::factory()->create(['program_id' => $student->program_id]);

        FeeStructure::create([
            'course_id' => $student->course_id,
            'program_id' => $student->program_id,
            'academic_year_id' => \App\Models\AcademicYear::factory()->create()->id,
            'fee_type' => 'Legacy structure',
            'amount' => 999999,
        ]);

        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'total_amount' => 30000,
            'scholarship_deduction' => 0,
            'final_amount' => 30000,
            'penalty_amount' => 2500,
            'due_date' => now()->subDays(5)->toDateString(),
            'status' => 'pending',
        ]);
        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'total_amount' => 12000,
            'scholarship_deduction' => 0,
            'final_amount' => 12000,
            'penalty_amount' => 0,
            'status' => 'fully_paid',
        ]);

        $this->actingAs($user)
            ->get(route('accounts.outstanding'))
            ->assertStatus(200)
            ->assertSee('Export Current View')
            ->assertSee(route('accounts.export-outstanding'), false)
            ->assertSee($student->user->name)
            ->assertSee('Rs. 32,500.00')
            ->assertSee('1 open')
            ->assertSee('1 overdue')
            ->assertDontSee('999,999');
    }

    public function test_accounts_outstanding_export_uses_active_fee_demands(): void
    {
        $user = $this->accountsUser();
        $student = Student::factory()->create();
        $term = Term::factory()->create(['program_id' => $student->program_id]);

        FeeStructure::create([
            'course_id' => $student->course_id,
            'program_id' => $student->program_id,
            'academic_year_id' => \App\Models\AcademicYear::factory()->create()->id,
            'fee_type' => 'Legacy structure',
            'amount' => 999999,
        ]);

        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'total_amount' => 45000,
            'scholarship_deduction' => 5000,
            'final_amount' => 40000,
            'penalty_amount' => 1000,
            'status' => 'overdue',
        ]);

        $response = $this->actingAs($user)
            ->get(route('accounts.export-outstanding'))
            ->assertStatus(200);

        $content = $response->streamedContent();

        $this->assertStringContainsString('Outstanding Amount (Rs.)', $content);
        $this->assertStringContainsString($student->user->name, $content);
        $this->assertStringContainsString('41,000.00', $content);
        $this->assertStringNotContainsString('999,999', $content);
    }

    public function test_accounts_overdue_demand_export_matches_overdue_drilldown_view(): void
    {
        $user = $this->accountsUser();
        $overdueStudent = Student::factory()->create();
        $currentStudent = Student::factory()->create();
        $term = Term::factory()->create(['program_id' => $overdueStudent->program_id]);

        FeeDemand::factory()->create([
            'student_id' => $overdueStudent->id,
            'term_id' => $term->id,
            'total_amount' => 45000,
            'scholarship_deduction' => 5000,
            'final_amount' => 40000,
            'penalty_amount' => 1000,
            'due_date' => now()->subDays(3)->toDateString(),
            'status' => 'pending',
        ]);
        FeeDemand::factory()->create([
            'student_id' => $currentStudent->id,
            'term_id' => $term->id,
            'total_amount' => 20000,
            'scholarship_deduction' => 0,
            'final_amount' => 20000,
            'penalty_amount' => 0,
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->get(route('accounts.outstanding', ['mode' => 'overdue_demands']))
            ->assertOk()
            ->assertSee('Filtered Source List (1)')
            ->assertSee('Export Current View')
            ->assertSee(route('accounts.export-outstanding', ['mode' => 'overdue_demands']), false)
            ->assertSee($overdueStudent->user->name)
            ->assertDontSee($currentStudent->user->name);

        $response = $this->actingAs($user)
            ->get(route('accounts.export-outstanding', ['mode' => 'overdue_demands']))
            ->assertOk();

        $content = $response->streamedContent();
        $this->assertStringContainsString('Amount Due (Rs.)', $content);
        $this->assertStringContainsString($overdueStudent->user->name, $content);
        $this->assertStringContainsString('40,000.00', $content);
        $this->assertStringNotContainsString($currentStudent->user->name, $content);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'export',
            'description' => 'Accounts overdue fee demands exported: 1 rows; filters={"mode":"overdue_demands"}',
        ]);
    }

    public function test_accounts_reconciliation_page_exposes_filtered_export_link(): void
    {
        $user = $this->accountsUser();
        $program = Program::factory()->create(['is_active' => true]);
        $applicant = Applicant::factory()->create(['program_id' => $program->id]);
        $installment = AdmissionFeeInstallment::create([
            'program_id' => $program->id,
            'name' => 'Reconciliation Fee',
            'amount' => 10000,
            'installment_number' => 1,
            'is_active' => true,
        ]);
        AdmissionPayment::create([
            'applicant_id' => $applicant->id,
            'admission_fee_installment_id' => $installment->id,
            'amount_paid' => 10000,
            'payment_date' => now()->toDateString(),
            'payment_mode' => 'upi',
            'transaction_reference' => 'RECON-LINK',
            'status' => 'verified',
            'verified_by' => $user->id,
            'verified_at' => now(),
            'submitted_by' => $applicant->user_id,
        ]);

        $this->actingAs($user)
            ->get(route('accounts.reconciliation', ['program_id' => $program->id]))
            ->assertOk()
            ->assertSee('Export Current View')
            ->assertSee(route('accounts.export-admission-payments', ['program_id' => $program->id]), false)
            ->assertSee($applicant->application_number);
    }

    public function test_accounts_demand_letter_is_limited_to_active_open_demands(): void
    {
        $user = $this->accountsUser();
        $student = Student::factory()->create(['status' => 'active']);
        $inactiveStudent = Student::factory()->create(['status' => 'inactive']);
        $term = Term::factory()->create(['program_id' => $student->program_id]);
        $inactiveTerm = Term::factory()->create(['program_id' => $inactiveStudent->program_id]);

        $openDemand = FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'final_amount' => 25000,
            'penalty_amount' => 0,
            'status' => 'pending',
        ]);
        $paidDemand = FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'final_amount' => 25000,
            'penalty_amount' => 0,
            'status' => 'fully_paid',
        ]);
        $inactiveStudentDemand = FeeDemand::factory()->create([
            'student_id' => $inactiveStudent->id,
            'term_id' => $inactiveTerm->id,
            'final_amount' => 25000,
            'penalty_amount' => 0,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->get(route('accounts.fee-demands.demand-letter', $openDemand))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($user)
            ->from(route('accounts.outstanding'))
            ->get(route('accounts.fee-demands.demand-letter', $paidDemand))
            ->assertRedirect(route('accounts.outstanding'))
            ->assertSessionHas('error', 'Demand letters are available only for open outstanding fee demands.');

        $this->actingAs($user)
            ->from(route('accounts.outstanding'))
            ->get(route('accounts.fee-demands.demand-letter', $inactiveStudentDemand))
            ->assertRedirect(route('accounts.outstanding'))
            ->assertSessionHas('error', 'Demand letters are available only for active students.');
    }

    public function test_accounts_exports_write_activity_logs_with_row_counts_and_filters(): void
    {
        $user = $this->accountsUser();
        $student = Student::factory()->create();
        $term = Term::factory()->create(['program_id' => $student->program_id]);
        $feeStructure = FeeStructure::create([
            'course_id' => $student->course_id,
            'program_id' => $student->program_id,
            'academic_year_id' => \App\Models\AcademicYear::factory()->create()->id,
            'fee_type' => 'Audit Tuition',
            'amount' => 25000,
        ]);
        FeePayment::create([
            'student_id' => $student->id,
            'fee_structure_id' => $feeStructure->id,
            'amount_paid' => 12000,
            'payment_date' => now()->toDateString(),
            'receipt_number' => 'RCPT-EXPORT-AUDIT',
            'payment_method' => 'cash',
            'status' => 'paid',
        ]);
        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'final_amount' => 5000,
            'penalty_amount' => 100,
            'status' => 'pending',
        ]);

        $applicant = Applicant::factory()->create([
            'program_id' => $student->program_id,
        ]);
        $installment = AdmissionFeeInstallment::create([
            'program_id' => $student->program_id,
            'name' => 'Export Audit Admission Fee',
            'amount' => 10000,
            'installment_number' => 1,
            'is_active' => true,
        ]);
        AdmissionPayment::create([
            'applicant_id' => $applicant->id,
            'admission_fee_installment_id' => $installment->id,
            'amount_paid' => 10000,
            'payment_date' => now()->toDateString(),
            'payment_mode' => 'upi',
            'transaction_reference' => 'ADM-EXPORT-AUDIT',
            'status' => 'verified',
            'verified_by' => $user->id,
            'verified_at' => now(),
            'submitted_by' => $applicant->user_id,
        ]);

        $this->actingAs($user)
            ->get(route('accounts.export-fee-collections', ['program_id' => $student->program_id]))
            ->assertOk()
            ->streamedContent();
        $this->actingAs($user)
            ->get(route('accounts.export-admission-payments', ['program_id' => $student->program_id]))
            ->assertOk()
            ->streamedContent();
        $this->actingAs($user)
            ->get(route('accounts.export-outstanding'))
            ->assertOk()
            ->streamedContent();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'export',
            'description' => 'Accounts fee collections exported: 1 rows; filters={"program_id":"' . $student->program_id . '"}',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'export',
            'description' => 'Accounts admission payments exported: 1 rows; filters={"program_id":"' . $student->program_id . '"}',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'export',
            'description' => 'Accounts outstanding fees exported: 1 rows; filters=none',
        ]);
        $this->assertSame(3, ActivityLog::where('user_id', $user->id)->where('action', 'export')->count());
    }

    public function test_accounts_reports_use_fee_demands_for_program_and_batch_totals(): void
    {
        $user = $this->accountsUser();
        $student = Student::factory()->create();
        $term = Term::factory()->create(['program_id' => $student->program_id]);
        $legacyFeeStructure = FeeStructure::create([
            'course_id' => $student->course_id,
            'program_id' => $student->program_id,
            'academic_year_id' => \App\Models\AcademicYear::factory()->create()->id,
            'fee_type' => 'Legacy structure',
            'amount' => 999999,
        ]);

        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'total_amount' => 30000,
            'scholarship_deduction' => 0,
            'final_amount' => 30000,
            'penalty_amount' => 2500,
            'status' => 'overdue',
        ]);
        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'total_amount' => 12000,
            'scholarship_deduction' => 0,
            'final_amount' => 12000,
            'penalty_amount' => 0,
            'status' => 'fully_paid',
        ]);
        FeePayment::create([
            'student_id' => $student->id,
            'fee_structure_id' => $legacyFeeStructure->id,
            'amount_paid' => 12000,
            'payment_date' => now()->toDateString(),
            'receipt_number' => 'RCPT-REPORT-001',
            'payment_method' => 'cash',
            'status' => 'paid',
        ]);

        $this->actingAs($user)
            ->get(route('accounts.reports'))
            ->assertStatus(200)
            ->assertSee('Demand-based billed, collected, and outstanding totals')
            ->assertSee($student->program->name)
            ->assertSee($student->batch->name)
            ->assertSee('Rs. 44,500')
            ->assertSee('Rs. 12,000')
            ->assertSee('Rs. 32,500')
            ->assertSee('27%')
            ->assertDontSee('999,999');
    }

    public function test_accounts_reports_empty_state_explains_required_setup(): void
    {
        $user = $this->accountsUser();

        $this->actingAs($user)
            ->get(route('accounts.reports'))
            ->assertStatus(200)
            ->assertSee('No active programs are available for fee reporting yet')
            ->assertSee('Create programs and fee demands before using the program-wise finance report')
            ->assertSee('No batches are available for fee reporting yet')
            ->assertSee('Add batches, active students, and fee demands before using the batch-wise finance report')
            ->assertDontSee('No data.')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('SERVICE ERROR', false);
    }
}
