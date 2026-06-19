<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminOfficialReportAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_broad_scoped_roles_cannot_download_official_admin_academic_documents(): void
    {
        $student = Student::factory()->create();
        $semester = Semester::factory()->create();

        foreach (['program_chair', 'hod', 'accounts_officer', 'cmc'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('admin.reports.grade-card', [$student, $semester]))->assertForbidden();
            $this->actingAs($user)->get(route('admin.students.report', $student))->assertForbidden();
            $this->actingAs($user)->get(route('admin.reports.timetable', $semester))->assertForbidden();
        }
    }

    public function test_broad_non_finance_roles_cannot_download_admin_fee_receipts(): void
    {
        $payment = $this->paidFeePayment();

        foreach (['program_chair', 'hod', 'exam_cell', 'cmc'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('admin.reports.fee-receipt', $payment))->assertForbidden();
        }
    }

    public function test_accounts_can_still_download_paid_admin_fee_receipts(): void
    {
        $accounts = $this->userWithRole('accounts_officer');
        $payment = $this->paidFeePayment();

        $response = $this->actingAs($accounts)->get(route('admin.reports.fee-receipt', $payment));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    private function paidFeePayment(): FeePayment
    {
        $student = Student::factory()->create();
        $academicYear = AcademicYear::factory()->create();
        $course = Course::factory()->create(['is_active' => true]);
        $feeStructure = FeeStructure::create([
            'course_id' => $course->id,
            'academic_year_id' => $academicYear->id,
            'program_id' => $student->program_id,
            'batch_id' => $student->batch_id,
            'fee_type' => 'Tuition',
            'amount' => 25000,
            'semester_number' => 1,
            'description' => 'Access control receipt test',
        ]);

        return FeePayment::create([
            'student_id' => $student->id,
            'fee_structure_id' => $feeStructure->id,
            'amount_paid' => 25000,
            'payment_date' => now()->toDateString(),
            'receipt_number' => 'RCP-ACCESS-' . uniqid(),
            'payment_method' => 'cash',
            'status' => 'paid',
        ]);
    }
}
