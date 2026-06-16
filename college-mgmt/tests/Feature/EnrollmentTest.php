<?php

namespace Tests\Feature;

use App\Models\{Batch, Course, Enrollment, Program, Semester, Student, StudentSubjectEnrollment, Subject, Term, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin',  'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
    }

    public function test_admin_can_view_enrollments_index(): void
    {
        $admin = User::factory()->create(['password' => Hash::make('password')]);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get('/admin/enrollments');
        $response->assertStatus(200);
    }

    public function test_non_admin_cannot_access_enrollments(): void
    {
        $student = User::factory()->create(['password' => Hash::make('password')]);
        $student->assignRole('student');

        $response = $this->actingAs($student)->get('/admin/enrollments');
        $this->assertTrue(
            in_array($response->getStatusCode(), [403, 302]),
            'Expected 403 or redirect, got ' . $response->getStatusCode()
        );
    }

    public function test_admin_manual_enrollment_syncs_canonical_student_subject_enrollment(): void
    {
        $fixture = $this->academicFixture();

        $this->actingAs($fixture['admin'])
            ->post(route('admin.enrollments.store'), [
                'student_id' => $fixture['student']->id,
                'semester_id' => $fixture['semester']->id,
                'subject_ids' => [$fixture['subject']->id],
            ])
            ->assertRedirect(route('admin.enrollments.index'));

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $fixture['student']->id,
            'subject_id' => $fixture['subject']->id,
            'semester_id' => $fixture['semester']->id,
            'term_id' => $fixture['term']->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('student_subject_enrollments', [
            'student_id' => $fixture['student']->id,
            'subject_id' => $fixture['subject']->id,
            'term_id' => $fixture['term']->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);
    }

    public function test_admin_manual_enrollment_rejects_wrong_program_subject_and_completed_history(): void
    {
        $fixture = $this->academicFixture();
        $wrongProgramSubject = Subject::factory()->create([
            'term_number' => 1,
            'is_active' => true,
        ]);
        $completedSubject = Subject::factory()->create([
            'program_id' => $fixture['program']->id,
            'term_number' => 1,
            'is_active' => true,
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $fixture['student']->id,
            'subject_id' => $completedSubject->id,
            'term_id' => $fixture['term']->id,
            'enrollment_type' => 'compulsory',
            'status' => 'completed',
        ]);

        $this->actingAs($fixture['admin'])
            ->from(route('admin.enrollments.create'))
            ->post(route('admin.enrollments.store'), [
                'student_id' => $fixture['student']->id,
                'semester_id' => $fixture['semester']->id,
                'subject_ids' => [$wrongProgramSubject->id],
            ])
            ->assertRedirect(route('admin.enrollments.create'))
            ->assertSessionHasErrors('subject_ids');

        $this->actingAs($fixture['admin'])
            ->from(route('admin.enrollments.create'))
            ->post(route('admin.enrollments.store'), [
                'student_id' => $fixture['student']->id,
                'semester_id' => $fixture['semester']->id,
                'subject_ids' => [$completedSubject->id],
            ])
            ->assertRedirect(route('admin.enrollments.create'))
            ->assertSessionHasErrors('subject_ids');

        $this->assertDatabaseMissing('enrollments', [
            'student_id' => $fixture['student']->id,
            'subject_id' => $wrongProgramSubject->id,
        ]);
        $this->assertDatabaseMissing('enrollments', [
            'student_id' => $fixture['student']->id,
            'subject_id' => $completedSubject->id,
            'status' => 'active',
        ]);
    }

    public function test_admin_bulk_enrollment_syncs_canonical_rows_for_course_students(): void
    {
        $fixture = $this->academicFixture();
        $secondStudent = Student::factory()->create([
            'program_id' => $fixture['program']->id,
            'batch_id' => $fixture['batch']->id,
            'course_id' => $fixture['course']->id,
            'current_term_id' => $fixture['term']->id,
            'status' => 'active',
        ]);

        $this->actingAs($fixture['admin'])
            ->post(route('admin.enrollments.bulk'), [
                'course_id' => $fixture['course']->id,
                'semester_id' => $fixture['semester']->id,
                'subject_ids' => [$fixture['subject']->id],
            ])
            ->assertRedirect(route('admin.enrollments.index'));

        foreach ([$fixture['student'], $secondStudent] as $student) {
            $this->assertDatabaseHas('student_subject_enrollments', [
                'student_id' => $student->id,
                'subject_id' => $fixture['subject']->id,
                'term_id' => $fixture['term']->id,
                'enrollment_type' => 'compulsory',
                'status' => 'active',
            ]);
        }
    }

    public function test_admin_bulk_enrollment_skips_ineligible_or_completed_history_rows(): void
    {
        $fixture = $this->academicFixture();
        $secondStudent = Student::factory()->create([
            'program_id' => $fixture['program']->id,
            'batch_id' => $fixture['batch']->id,
            'course_id' => $fixture['course']->id,
            'current_term_id' => $fixture['term']->id,
            'status' => 'active',
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $fixture['student']->id,
            'subject_id' => $fixture['subject']->id,
            'term_id' => $fixture['term']->id,
            'enrollment_type' => 'compulsory',
            'status' => 'completed',
        ]);
        $wrongProgramSubject = Subject::factory()->create([
            'term_number' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($fixture['admin'])
            ->post(route('admin.enrollments.bulk'), [
                'course_id' => $fixture['course']->id,
                'semester_id' => $fixture['semester']->id,
                'subject_ids' => [$fixture['subject']->id, $wrongProgramSubject->id],
            ])
            ->assertRedirect(route('admin.enrollments.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('enrollments', [
            'student_id' => $fixture['student']->id,
            'subject_id' => $fixture['subject']->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('student_subject_enrollments', [
            'student_id' => $fixture['student']->id,
            'subject_id' => $fixture['subject']->id,
            'term_id' => $fixture['term']->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('student_subject_enrollments', [
            'student_id' => $secondStudent->id,
            'subject_id' => $fixture['subject']->id,
            'term_id' => $fixture['term']->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseMissing('student_subject_enrollments', [
            'student_id' => $secondStudent->id,
            'subject_id' => $wrongProgramSubject->id,
        ]);
    }

    public function test_admin_enrollment_delete_marks_matching_canonical_row_dropped(): void
    {
        $fixture = $this->academicFixture();
        $enrollment = Enrollment::create([
            'student_id' => $fixture['student']->id,
            'subject_id' => $fixture['subject']->id,
            'semester_id' => $fixture['semester']->id,
            'term_id' => $fixture['term']->id,
            'status' => 'inactive',
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $fixture['student']->id,
            'subject_id' => $fixture['subject']->id,
            'term_id' => $fixture['term']->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);

        $this->actingAs($fixture['admin'])
            ->delete(route('admin.enrollments.destroy', $enrollment))
            ->assertRedirect();

        $this->assertDatabaseMissing('enrollments', ['id' => $enrollment->id]);
        $this->assertDatabaseHas('student_subject_enrollments', [
            'student_id' => $fixture['student']->id,
            'subject_id' => $fixture['subject']->id,
            'term_id' => $fixture['term']->id,
            'status' => 'dropped',
        ]);
    }

    public function test_admin_cannot_delete_completed_enrollment_history(): void
    {
        $fixture = $this->academicFixture();
        $enrollment = Enrollment::create([
            'student_id' => $fixture['student']->id,
            'subject_id' => $fixture['subject']->id,
            'semester_id' => $fixture['semester']->id,
            'term_id' => $fixture['term']->id,
            'status' => 'completed',
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $fixture['student']->id,
            'subject_id' => $fixture['subject']->id,
            'term_id' => $fixture['term']->id,
            'enrollment_type' => 'compulsory',
            'status' => 'completed',
        ]);

        $this->actingAs($fixture['admin'])
            ->from(route('admin.enrollments.index'))
            ->delete(route('admin.enrollments.destroy', $enrollment))
            ->assertRedirect(route('admin.enrollments.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('enrollments', [
            'id' => $enrollment->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('student_subject_enrollments', [
            'student_id' => $fixture['student']->id,
            'subject_id' => $fixture['subject']->id,
            'term_id' => $fixture['term']->id,
            'status' => 'completed',
        ]);
    }

    private function academicFixture(): array
    {
        $admin = User::factory()->create(['password' => Hash::make('password')]);
        $admin->assignRole('admin');
        $program = Program::factory()->create(['is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $course = Course::factory()->create(['is_active' => true]);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'Term 1',
            'is_current' => true,
        ]);
        $semester = Semester::factory()->create([
            'number' => 1,
            'name' => 'Term 1',
            'is_current' => true,
        ]);
        $student = Student::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'course_id' => $course->id,
            'current_term_id' => $term->id,
            'status' => 'active',
        ]);
        $subject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'is_active' => true,
        ]);

        return compact('admin', 'program', 'batch', 'course', 'term', 'semester', 'student', 'subject');
    }
}
