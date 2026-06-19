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
            'published_at' => now(),
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

    public function test_inactive_student_cannot_submit_new_scholarship_application(): void
    {
        $student = $this->student();
        $student->update(['status' => 'inactive']);
        $scheme = $this->scheme();

        $this->actingAs($student->user)
            ->post(route('student.scholarships.apply', $scheme), [
                'reason' => str_repeat('This reason is long enough for validation. ', 2),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('student');

        $this->assertDatabaseCount('student_scholarship_applications', 0);
    }

    public function test_inactive_student_scholarship_page_hides_application_controls(): void
    {
        $student = $this->student();
        $student->update(['status' => 'inactive']);
        $this->scheme();

        $this->actingAs($student->user)
            ->get(route('student.scholarships.index'))
            ->assertOk()
            ->assertSee('Scholarship application submission is locked')
            ->assertSee('Locked')
            ->assertDontSee('Why should you be considered?')
            ->assertDontSee('Submit Application')
            ->assertDontSee('data-bs-target="#applyScholarship', false);
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
        $this->assertSame('UTR12345', $application->disbursement_ref);
        $this->assertStringContainsString('UTR12345', $application->review_note);
        $this->assertSame(3, Notification::where('user_id', $student->user_id)->where('type', 'scholarship')->count());
    }

    public function test_program_chair_cannot_directly_view_or_mutate_student_scholarship_queue(): void
    {
        Storage::fake('local');

        $chair = $this->userWithRole('program_chair');
        $student = $this->student();
        $scheme = $this->scheme(['available_seats' => 2]);
        $proofPath = UploadedFile::fake()
            ->create('income-proof.pdf', 64, 'application/pdf')
            ->store('student-scholarships/'.$student->id, 'local');

        $pendingApplication = StudentScholarshipApplication::create([
            'student_id' => $student->id,
            'scholarship_scheme_id' => $scheme->id,
            'reason' => str_repeat('Pending scholarship reason. ', 3),
            'status' => 'pending',
            'documents_path' => $proofPath,
        ]);

        $approvedApplication = StudentScholarshipApplication::create([
            'student_id' => $this->student()->id,
            'scholarship_scheme_id' => $scheme->id,
            'reason' => str_repeat('Approved scholarship reason. ', 3),
            'status' => 'approved',
            'disbursed_amount' => 8000,
        ]);

        $this->actingAs($chair)
            ->get(route('admin.student-scholarships.index'))
            ->assertForbidden();

        $this->actingAs($chair)
            ->get(route('admin.student-scholarships.proof', $pendingApplication))
            ->assertForbidden();

        $this->actingAs($chair)
            ->patch(route('admin.student-scholarships.shortlist', $pendingApplication), [
                'review_note' => 'Unauthorized shortlist.',
            ])
            ->assertForbidden();

        $this->actingAs($chair)
            ->patch(route('admin.student-scholarships.approve', $pendingApplication), [
                'disbursed_amount' => 5000,
            ])
            ->assertForbidden();

        $this->actingAs($chair)
            ->patch(route('admin.student-scholarships.reject', $pendingApplication), [
                'review_note' => 'Unauthorized reject.',
            ])
            ->assertForbidden();

        $this->actingAs($chair)
            ->patch(route('admin.student-scholarships.disburse', $approvedApplication), [
                'disbursement_ref' => 'UTR-UNAUTH-SCH',
            ])
            ->assertForbidden();

        $this->assertSame('pending', $pendingApplication->fresh()->status);
        $this->assertNull($pendingApplication->fresh()->reviewed_by);

        $approvedApplication->refresh();
        $this->assertSame('approved', $approvedApplication->status);
        $this->assertNull($approvedApplication->disbursed_at);
        $this->assertNull($approvedApplication->disbursement_ref);
    }

    public function test_admin_cannot_shortlist_stale_or_ineligible_student_scholarship_application(): void
    {
        Storage::fake('local');

        $admin = $this->userWithRole('admin');
        $program = Program::factory()->create();
        $student = $this->student($program);
        $student->update(['status' => 'inactive']);

        $inactiveStudentApplication = StudentScholarshipApplication::create([
            'student_id' => $student->id,
            'scholarship_scheme_id' => $this->scheme(['program_id' => $program->id])->id,
            'reason' => str_repeat('Inactive student scholarship reason. ', 2),
            'status' => 'pending',
        ]);

        $missingProofApplication = StudentScholarshipApplication::create([
            'student_id' => $this->student($program)->id,
            'scholarship_scheme_id' => $this->scheme([
                'program_id' => $program->id,
                'requires_document' => true,
            ])->id,
            'reason' => str_repeat('Missing proof scholarship reason. ', 2),
            'status' => 'pending',
        ]);

        foreach ([$inactiveStudentApplication, $missingProofApplication] as $application) {
            $this->actingAs($admin)
                ->patch(route('admin.student-scholarships.shortlist', $application), [
                    'review_note' => 'Trying to shortlist stale application.',
                ])
                ->assertSessionHasErrors('scholarship');

            $this->assertSame('pending', $application->fresh()->status);
            $this->assertNull($application->fresh()->reviewed_by);
        }
    }

    public function test_admin_cannot_disburse_stale_student_scholarship_after_scheme_becomes_invalid(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student();
        $scheme = $this->scheme(['available_seats' => 1]);

        $application = StudentScholarshipApplication::create([
            'student_id' => $student->id,
            'scholarship_scheme_id' => $scheme->id,
            'reason' => str_repeat('Approved scholarship reason. ', 2),
            'status' => 'approved',
            'disbursed_amount' => 12000,
        ]);

        $scheme->update(['is_active' => false]);

        $this->actingAs($admin)
            ->patch(route('admin.student-scholarships.disburse', $application), [
                'disbursement_ref' => 'UTR-STALE-001',
            ])
            ->assertSessionHasErrors('scholarship');

        $application->refresh();
        $this->assertSame('approved', $application->status);
        $this->assertNull($application->disbursed_at);
        $this->assertNull($application->disbursement_ref);
    }

    public function test_admin_cannot_reuse_student_scholarship_disbursement_reference(): void
    {
        $admin = $this->userWithRole('admin');
        $scheme = $this->scheme(['available_seats' => 2]);
        $firstApplication = StudentScholarshipApplication::create([
            'student_id' => $this->student()->id,
            'scholarship_scheme_id' => $scheme->id,
            'reason' => str_repeat('First approved scholarship reason. ', 2),
            'status' => 'approved',
            'disbursed_amount' => 12000,
        ]);
        $secondApplication = StudentScholarshipApplication::create([
            'student_id' => $this->student()->id,
            'scholarship_scheme_id' => $scheme->id,
            'reason' => str_repeat('Second approved scholarship reason. ', 2),
            'status' => 'approved',
            'disbursed_amount' => 8000,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.student-scholarships.disburse', $firstApplication), [
                'disbursement_ref' => 'UTR-DUP-001',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Scholarship marked as disbursed.');

        $this->actingAs($admin)
            ->patch(route('admin.student-scholarships.disburse', $secondApplication), [
                'disbursement_ref' => 'utr-dup-001',
            ])
            ->assertSessionHasErrors('disbursement_ref');

        $this->assertSame('approved', $secondApplication->fresh()->status);
        $this->assertNull($secondApplication->fresh()->disbursement_ref);
    }

    public function test_admin_cannot_disburse_student_scholarship_with_blank_reference_after_trimming(): void
    {
        $admin = $this->userWithRole('admin');
        $application = StudentScholarshipApplication::create([
            'student_id' => $this->student()->id,
            'scholarship_scheme_id' => $this->scheme()->id,
            'reason' => str_repeat('Approved scholarship reason. ', 2),
            'status' => 'approved',
            'disbursed_amount' => 12000,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.student-scholarships.disburse', $application), [
                'disbursement_ref' => '   ',
            ])
            ->assertSessionHasErrors('disbursement_ref');

        $application->refresh();
        $this->assertSame('approved', $application->status);
        $this->assertNull($application->disbursement_ref);
        $this->assertNull($application->disbursed_at);
    }

    public function test_admin_cannot_reject_approved_or_disbursed_student_scholarship_commitments(): void
    {
        $admin = $this->userWithRole('admin');
        $scheme = $this->scheme(['available_seats' => 2]);
        $approvedApplication = StudentScholarshipApplication::create([
            'student_id' => $this->student()->id,
            'scholarship_scheme_id' => $scheme->id,
            'reason' => str_repeat('Approved scholarship commitment reason. ', 2),
            'status' => 'approved',
            'disbursed_amount' => 12000,
            'review_note' => 'Approved commitment.',
        ]);
        $disbursedApplication = StudentScholarshipApplication::create([
            'student_id' => $this->student()->id,
            'scholarship_scheme_id' => $scheme->id,
            'reason' => str_repeat('Disbursed scholarship commitment reason. ', 2),
            'status' => 'disbursed',
            'disbursed_amount' => 8000,
            'disbursed_at' => now(),
            'disbursement_ref' => 'UTR-DISBURSED-LOCK',
            'review_note' => 'Disbursed commitment.',
        ]);

        foreach ([$approvedApplication, $disbursedApplication] as $application) {
            $this->actingAs($admin)
                ->patch(route('admin.student-scholarships.reject', $application), [
                    'review_note' => 'Trying to reject committed scholarship.',
                ])
                ->assertRedirect()
                ->assertSessionHas('error', 'Only pending or shortlisted scholarship applications can be rejected. Approved or disbursed scholarships require an audited reversal workflow.');
        }

        $approvedApplication->refresh();
        $this->assertSame('approved', $approvedApplication->status);
        $this->assertSame('12000.00', $approvedApplication->disbursed_amount);
        $this->assertSame('Approved commitment.', $approvedApplication->review_note);

        $disbursedApplication->refresh();
        $this->assertSame('disbursed', $disbursedApplication->status);
        $this->assertSame('8000.00', $disbursedApplication->disbursed_amount);
        $this->assertSame('UTR-DISBURSED-LOCK', $disbursedApplication->disbursement_ref);
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

    public function test_admin_cannot_approve_stale_or_incomplete_scholarship_applications(): void
    {
        Storage::fake('local');

        $admin = $this->userWithRole('admin');
        $program = Program::factory()->create();
        $student = $this->student($program);

        $inactiveApplication = StudentScholarshipApplication::create([
            'student_id' => $student->id,
            'scholarship_scheme_id' => $this->scheme(['is_active' => false])->id,
            'reason' => str_repeat('Inactive scheme application reason. ', 2),
            'status' => 'pending',
        ]);

        $wrongProgramApplication = StudentScholarshipApplication::create([
            'student_id' => $student->id,
            'scholarship_scheme_id' => $this->scheme(['program_id' => Program::factory()->create()->id])->id,
            'reason' => str_repeat('Wrong program application reason. ', 2),
            'status' => 'pending',
        ]);

        $missingProofApplication = StudentScholarshipApplication::create([
            'student_id' => $student->id,
            'scholarship_scheme_id' => $this->scheme([
                'program_id' => $program->id,
                'requires_document' => true,
            ])->id,
            'reason' => str_repeat('Missing proof application reason. ', 2),
            'status' => 'pending',
        ]);

        foreach ([$inactiveApplication, $wrongProgramApplication, $missingProofApplication] as $application) {
            $this->actingAs($admin)
                ->patch(route('admin.student-scholarships.approve', $application), [
                    'disbursed_amount' => 5000,
                    'review_note' => 'Trying to approve stale application.',
                ])
                ->assertSessionHasErrors('scholarship');

            $this->assertSame('pending', $application->fresh()->status);
            $this->assertNull($application->fresh()->disbursed_amount);
        }
    }

    public function test_admin_rechecks_structured_eligibility_before_scholarship_approval(): void
    {
        $admin = $this->userWithRole('admin');
        $program = Program::factory()->create();
        $student = $this->student($program);
        $this->resultForCgpa($student, 6.5);
        $this->parentIncome($student, '800000');
        $scheme = $this->scheme([
            'program_id' => $program->id,
            'min_cgpa' => 7.5,
            'max_family_income' => 500000,
        ]);

        $application = StudentScholarshipApplication::create([
            'student_id' => $student->id,
            'scholarship_scheme_id' => $scheme->id,
            'reason' => str_repeat('Stale structured eligibility application reason. ', 2),
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.student-scholarships.approve', $application), [
                'disbursed_amount' => 5000,
                'review_note' => 'Trying to approve ineligible student.',
            ])
            ->assertSessionHasErrors('scholarship');

        $this->assertSame('pending', $application->fresh()->status);
        $this->assertNull($application->fresh()->disbursed_amount);
    }

    public function test_admin_cannot_approve_or_disburse_scholarship_for_inactive_student(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student();
        $student->update(['status' => 'inactive']);
        $scheme = $this->scheme(['available_seats' => 2]);
        $secondScheme = $this->scheme(['available_seats' => 2]);

        $pendingApplication = StudentScholarshipApplication::create([
            'student_id' => $student->id,
            'scholarship_scheme_id' => $scheme->id,
            'reason' => str_repeat('Inactive student scholarship reason. ', 2),
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.student-scholarships.approve', $pendingApplication), [
                'disbursed_amount' => 5000,
                'review_note' => 'Trying to approve inactive student.',
            ])
            ->assertSessionHasErrors('scholarship');

        $this->assertSame('pending', $pendingApplication->fresh()->status);
        $this->assertNull($pendingApplication->fresh()->disbursed_amount);

        $approvedApplication = StudentScholarshipApplication::create([
            'student_id' => $student->id,
            'scholarship_scheme_id' => $secondScheme->id,
            'reason' => str_repeat('Previously approved inactive student scholarship reason. ', 2),
            'status' => 'approved',
            'disbursed_amount' => 6000,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.student-scholarships.disburse', $approvedApplication), [
                'disbursement_ref' => 'UTR-INACTIVE-SCH',
            ])
            ->assertSessionHasErrors('scholarship');

        $approvedApplication->refresh();
        $this->assertSame('approved', $approvedApplication->status);
        $this->assertNull($approvedApplication->disbursed_at);
        $this->assertNull($approvedApplication->disbursement_ref);
    }
}
