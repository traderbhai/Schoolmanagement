<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\ParentProfile;
use App\Models\Program;
use App\Models\ScholarshipScheme;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentScholarshipApplication;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentScholarshipWorkflowGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private static int $studentSequence = 1;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function student(?Program $program = null): Student
    {
        $user = $this->userWithRole('student');

        return Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => ($program ?? Program::factory()->create())->id,
            'enrollment_number' => 'ENR-SCH-' . self::$studentSequence++,
            'status' => 'active',
        ]);
    }

    private function scheme(array $overrides = []): ScholarshipScheme
    {
        return ScholarshipScheme::create(array_merge([
            'program_id' => null,
            'name' => 'Merit Support Scheme',
            'scheme_code' => 'MSS-' . uniqid(),
            'type' => 'merit',
            'criteria' => 'Good academic standing and clear need statement.',
            'max_amount' => 25000,
            'available_seats' => 2,
            'is_active' => true,
        ], $overrides));
    }

    private function resultForCgpa(Student $student, float $marks): void
    {
        $semester = Semester::factory()->create(['number' => 1]);
        $subject = Subject::factory()->create(['program_id' => $student->program_id, 'credits' => 4]);
        Enrollment::create([
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'subject_id' => $subject->id,
            'status' => 'active',
        ]);
        $exam = Exam::factory()->create([
            'program_id' => $student->program_id,
            'semester_id' => $semester->id,
            'subject_id' => $subject->id,
            'total_marks' => 10,
            'passing_marks' => 4,
        ]);
        ExamResult::factory()->create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'marks_obtained' => $marks,
            'is_absent' => false,
        ]);
    }

    private function parentIncome(Student $student, string $income): void
    {
        $parent = ParentProfile::create([
            'user_id' => User::factory()->create()->id,
            'relation' => 'father',
            'annual_income' => $income,
        ]);
        $student->parents()->attach($parent->id);
    }

    public function test_student_can_apply_with_reason_and_track_application(): void
    {
        $program = Program::factory()->create();
        $student = $this->student($program);
        $scheme = $this->scheme(['program_id' => $program->id]);

        $this->actingAs($student->user)
            ->get(route('student.scholarships.index'))
            ->assertStatus(200)
            ->assertSee('Why should you be considered?')
            ->assertSee('Submit Application')
            ->assertSee('Seats left: 2');

        $reason = str_repeat('I need scholarship support for academic continuity. ', 2);

        $this->actingAs($student->user)
            ->post(route('student.scholarships.apply', $scheme), [
                'reason' => $reason,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Application for "' . $scheme->name . '" submitted successfully.');

        $this->assertDatabaseHas('student_scholarship_applications', [
            'student_id' => $student->id,
            'scholarship_scheme_id' => $scheme->id,
            'status' => 'pending',
        ]);
    }

    public function test_student_cannot_apply_to_another_programs_scheme(): void
    {
        $student = $this->student(Program::factory()->create());
        $otherProgram = Program::factory()->create();
        $scheme = $this->scheme(['program_id' => $otherProgram->id]);

        $this->actingAs($student->user)
            ->post(route('student.scholarships.apply', $scheme), [
                'reason' => str_repeat('This reason is long enough for validation. ', 2),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('student_scholarship_applications', 0);
    }

    public function test_student_cannot_apply_when_structured_scholarship_eligibility_is_not_met(): void
    {
        $program = Program::factory()->create();
        $student = $this->student($program);
        $this->resultForCgpa($student, 6.5);
        $this->parentIncome($student, '300000');
        $scheme = $this->scheme([
            'program_id' => $program->id,
            'min_cgpa' => 7.5,
            'max_family_income' => 500000,
        ]);

        $this->actingAs($student->user)
            ->get(route('student.scholarships.index'))
            ->assertOk()
            ->assertSee('Not Eligible')
            ->assertSee('Minimum CGPA requirement not met');

        $this->actingAs($student->user)
            ->post(route('student.scholarships.apply', $scheme), [
                'reason' => str_repeat('This reason is long enough for validation. ', 2),
            ])
            ->assertSessionHasErrors('eligibility');

        $this->assertDatabaseCount('student_scholarship_applications', 0);
    }

    public function test_student_cannot_apply_when_family_income_exceeds_scheme_limit(): void
    {
        $program = Program::factory()->create();
        $student = $this->student($program);
        $this->resultForCgpa($student, 8.5);
        $this->parentIncome($student, '800000');
        $scheme = $this->scheme([
            'program_id' => $program->id,
            'min_cgpa' => 7.5,
            'max_family_income' => 500000,
        ]);

        $this->actingAs($student->user)
            ->post(route('student.scholarships.apply', $scheme), [
                'reason' => str_repeat('This reason is long enough for validation. ', 2),
            ])
            ->assertSessionHasErrors('eligibility');

        $this->assertDatabaseCount('student_scholarship_applications', 0);
    }

    public function test_required_scholarship_proof_is_stored_and_available_to_admin(): void
    {
        Storage::fake('local');

        $admin = $this->userWithRole('admin');
        $program = Program::factory()->create();
        $student = $this->student($program);
        $this->resultForCgpa($student, 8.5);
        $this->parentIncome($student, '300000');
        $scheme = $this->scheme([
            'program_id' => $program->id,
            'min_cgpa' => 7.5,
            'max_family_income' => 500000,
            'requires_document' => true,
        ]);

        $this->actingAs($student->user)
            ->post(route('student.scholarships.apply', $scheme), [
                'reason' => str_repeat('This reason is long enough for validation. ', 2),
            ])
            ->assertSessionHasErrors('proof_document');

        $this->actingAs($student->user)
            ->post(route('student.scholarships.apply', $scheme), [
                'reason' => str_repeat('This reason is long enough for validation. ', 2),
                'proof_document' => UploadedFile::fake()->create('income-proof.pdf', 64, 'application/pdf'),
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Application for "' . $scheme->name . '" submitted successfully.');

        $application = StudentScholarshipApplication::firstOrFail();
        $this->assertNotNull($application->documents_path);
        Storage::disk('local')->assertExists($application->documents_path);

        $this->actingAs($admin)
            ->get(route('admin.student-scholarships.index'))
            ->assertOk()
            ->assertSee('Download proof');

        $this->actingAs($admin)
            ->get(route('admin.student-scholarships.proof', $application))
            ->assertOk();
    }

    public function test_admin_can_review_approve_and_disburse_student_scholarship(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student();
        $scheme = $this->scheme(['available_seats' => 1]);

        $application = StudentScholarshipApplication::create([
            'student_id' => $student->id,
            'scholarship_scheme_id' => $scheme->id,
            'reason' => str_repeat('Strong academic performance and financial need. ', 2),
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.student-scholarships.index'))
            ->assertStatus(200)
            ->assertSee('Student Scholarship Applications')
            ->assertSee('Merit Support Scheme')
            ->assertSee('Shortlist')
            ->assertSee('Approve');

        $this->actingAs($admin)
            ->patch(route('admin.student-scholarships.shortlist', $application), [
                'review_note' => 'Eligible for review.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Scholarship application shortlisted.');

        $this->assertSame('shortlisted', $application->fresh()->status);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $student->user_id,
            'title' => 'Scholarship application shortlisted',
            'type' => 'scholarship',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.student-scholarships.approve', $application), [
                'disbursed_amount' => 12000,
                'review_note' => 'Approved by committee.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Scholarship application approved.');

        $application->refresh();
        $this->assertSame('approved', $application->status);
        $this->assertSame('12000.00', $application->disbursed_amount);
        $this->assertSame(0, $scheme->fresh()->seatsRemaining());

        $this->actingAs($admin)
            ->patch(route('admin.student-scholarships.disburse', $application), [
                'disbursement_ref' => 'UTR12345',
                'review_note' => 'Paid through bank transfer.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Scholarship marked as disbursed.');

        $application->refresh();
        $this->assertSame('disbursed', $application->status);
        $this->assertNotNull($application->disbursed_at);
        $this->assertStringContainsString('UTR12345', $application->review_note);
        $this->assertSame(3, Notification::where('user_id', $student->user_id)->where('type', 'scholarship')->count());
    }

    public function test_admin_cannot_approve_when_scheme_seats_are_full(): void
    {
        $admin = $this->userWithRole('admin');
        $scheme = $this->scheme(['available_seats' => 1]);
        $approvedStudent = $this->student();
        $newStudent = $this->student();

        StudentScholarshipApplication::create([
            'student_id' => $approvedStudent->id,
            'scholarship_scheme_id' => $scheme->id,
            'reason' => str_repeat('Already approved student reason. ', 2),
            'status' => 'approved',
            'disbursed_amount' => 10000,
        ]);

        $application = StudentScholarshipApplication::create([
            'student_id' => $newStudent->id,
            'scholarship_scheme_id' => $scheme->id,
            'reason' => str_repeat('New student reason for scholarship review. ', 2),
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.student-scholarships.approve', $application), [
                'disbursed_amount' => 5000,
            ])
            ->assertSessionHasErrors('scholarship');

        $this->assertSame('pending', $application->fresh()->status);
    }
}
