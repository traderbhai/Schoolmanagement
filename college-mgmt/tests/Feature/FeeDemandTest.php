<?php

namespace Tests\Feature;

use App\Models\FeeDemand;
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

    public function test_can_update_fee_demand()
    {
        $feeDemand = FeeDemand::factory()->create();

        $data = [
            'total_amount' => 150000,
            'scholarship_deduction' => 15000,
            'due_date' => now()->addMonth(),
            'status' => 'partially_paid',
        ];

        $response = $this->put("/academic/fee-demands/{$feeDemand->id}", $data);

        $this->assertEquals(150000, $feeDemand->fresh()->total_amount);
    }

    public function test_can_mark_as_paid()
    {
        $feeDemand = FeeDemand::factory()->create(['status' => 'pending']);

        $response = $this->post("/academic/fee-demands/{$feeDemand->id}/mark-paid");

        $this->assertEquals('fully_paid', $feeDemand->fresh()->status);
    }

    public function test_can_delete_fee_demand()
    {
        $feeDemand = FeeDemand::factory()->create();

        $response = $this->delete("/academic/fee-demands/{$feeDemand->id}");

        $this->assertDatabaseMissing('fee_demands', ['id' => $feeDemand->id]);
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
