<?php

namespace Tests\Feature;

use App\Models\{Enrollment, Program, Semester, Student, StudentSubjectEnrollment, Subject, Term, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentSubjectRegistrationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudentContext(): array
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $program = Program::factory()->create();
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'term_number' => 2,
            'name' => 'Term 2',
            'is_current' => true,
        ]);
        $semester = Semester::factory()->create([
            'number' => 2,
            'name' => 'Term 2',
            'is_current' => true,
        ]);
        $user = User::factory()->create();
        $user->assignRole('student');
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'current_term_id' => $term->id,
            'status' => 'active',
        ]);

        return compact('program', 'term', 'semester', 'user', 'student');
    }

    public function test_subject_registration_shows_only_current_program_and_term_subjects(): void
    {
        $ctx = $this->makeStudentContext();
        $available = Subject::factory()->create([
            'program_id' => $ctx['program']->id,
            'term_number' => 2,
            'name' => 'Available Analytics',
        ]);
        $otherTerm = Subject::factory()->create([
            'program_id' => $ctx['program']->id,
            'term_number' => 3,
            'name' => 'Future Strategy',
        ]);
        $otherProgram = Subject::factory()->create([
            'term_number' => 2,
            'name' => 'Other Program Finance',
        ]);

        $this->actingAs($ctx['user'])
            ->get(route('student.subjects.index'))
            ->assertOk()
            ->assertSee($available->name)
            ->assertDontSee($otherTerm->name)
            ->assertDontSee($otherProgram->name);
    }

    public function test_subject_registration_creates_canonical_and_legacy_enrollment_rows(): void
    {
        $ctx = $this->makeStudentContext();
        $subject = Subject::factory()->create([
            'program_id' => $ctx['program']->id,
            'term_number' => 2,
            'credits' => 3,
            'name' => 'Service Operations',
        ]);

        $this->actingAs($ctx['user'])
            ->post(route('student.subjects.store'), ['subject_id' => $subject->id])
            ->assertRedirect()
            ->assertSessionHas('success', 'Enrolled in Service Operations successfully.');

        $this->assertDatabaseHas('student_subject_enrollments', [
            'student_id' => $ctx['student']->id,
            'subject_id' => $subject->id,
            'term_id' => $ctx['term']->id,
            'enrollment_type' => 'elective',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('enrollments', [
            'student_id' => $ctx['student']->id,
            'subject_id' => $subject->id,
            'semester_id' => $ctx['semester']->id,
            'term_id' => $ctx['term']->id,
            'status' => 'active',
        ]);
    }

    public function test_subject_registration_rejects_subjects_outside_student_program_or_term(): void
    {
        $ctx = $this->makeStudentContext();
        $otherProgramSubject = Subject::factory()->create(['term_number' => 2]);

        $this->actingAs($ctx['user'])
            ->post(route('student.subjects.store'), ['subject_id' => $otherProgramSubject->id])
            ->assertRedirect()
            ->assertSessionHas('error', 'This subject is not available for your current program and term.');

        $this->assertDatabaseMissing('student_subject_enrollments', [
            'student_id' => $ctx['student']->id,
            'subject_id' => $otherProgramSubject->id,
        ]);
    }

    public function test_subject_registration_does_not_reactivate_completed_history(): void
    {
        $ctx = $this->makeStudentContext();
        $subject = Subject::factory()->create([
            'program_id' => $ctx['program']->id,
            'term_number' => 2,
            'credits' => 3,
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $ctx['student']->id,
            'subject_id' => $subject->id,
            'term_id' => $ctx['term']->id,
            'enrollment_type' => 'elective',
            'status' => 'completed',
        ]);

        $this->actingAs($ctx['user'])
            ->post(route('student.subjects.store'), ['subject_id' => $subject->id])
            ->assertRedirect()
            ->assertSessionHas('error', 'Completed subject history cannot be re-registered from self-service.');

        $this->assertDatabaseHas('student_subject_enrollments', [
            'student_id' => $ctx['student']->id,
            'subject_id' => $subject->id,
            'term_id' => $ctx['term']->id,
            'status' => 'completed',
        ]);
    }

    public function test_subject_drop_marks_canonical_and_legacy_enrollments_dropped(): void
    {
        $ctx = $this->makeStudentContext();
        $subject = Subject::factory()->create([
            'program_id' => $ctx['program']->id,
            'term_number' => 2,
        ]);
        $canonical = StudentSubjectEnrollment::create([
            'student_id' => $ctx['student']->id,
            'subject_id' => $subject->id,
            'term_id' => $ctx['term']->id,
            'enrollment_type' => 'elective',
            'status' => 'active',
        ]);
        Enrollment::create([
            'student_id' => $ctx['student']->id,
            'subject_id' => $subject->id,
            'semester_id' => $ctx['semester']->id,
            'term_id' => $ctx['term']->id,
            'status' => 'active',
        ]);

        $this->actingAs($ctx['user'])
            ->delete(route('student.subjects.drop', $canonical))
            ->assertRedirect()
            ->assertSessionHas('success', 'Subject dropped successfully.');

        $this->assertDatabaseHas('student_subject_enrollments', [
            'id' => $canonical->id,
            'status' => 'dropped',
        ]);
        $this->assertDatabaseHas('enrollments', [
            'student_id' => $ctx['student']->id,
            'subject_id' => $subject->id,
            'term_id' => $ctx['term']->id,
            'status' => 'dropped',
        ]);
    }

    public function test_subject_drop_blocks_compulsory_completed_and_old_term_enrollments(): void
    {
        $ctx = $this->makeStudentContext();
        $oldTerm = Term::factory()->create([
            'program_id' => $ctx['program']->id,
            'term_number' => 1,
            'name' => 'Term 1',
        ]);
        $compulsory = Subject::factory()->create(['program_id' => $ctx['program']->id, 'term_number' => 2]);
        $completed = Subject::factory()->create(['program_id' => $ctx['program']->id, 'term_number' => 2]);
        $oldSubject = Subject::factory()->create(['program_id' => $ctx['program']->id, 'term_number' => 1]);

        $compulsoryEnrollment = StudentSubjectEnrollment::create([
            'student_id' => $ctx['student']->id,
            'subject_id' => $compulsory->id,
            'term_id' => $ctx['term']->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);
        $completedEnrollment = StudentSubjectEnrollment::create([
            'student_id' => $ctx['student']->id,
            'subject_id' => $completed->id,
            'term_id' => $ctx['term']->id,
            'enrollment_type' => 'elective',
            'status' => 'completed',
        ]);
        $oldEnrollment = StudentSubjectEnrollment::create([
            'student_id' => $ctx['student']->id,
            'subject_id' => $oldSubject->id,
            'term_id' => $oldTerm->id,
            'enrollment_type' => 'elective',
            'status' => 'active',
        ]);

        $this->actingAs($ctx['user'])
            ->delete(route('student.subjects.drop', $compulsoryEnrollment))
            ->assertRedirect()
            ->assertSessionHas('error', 'Compulsory subjects cannot be dropped from student self-service.');

        $this->actingAs($ctx['user'])
            ->delete(route('student.subjects.drop', $completedEnrollment))
            ->assertRedirect()
            ->assertSessionHas('error', 'Only active subject enrollments can be dropped.');

        $this->actingAs($ctx['user'])
            ->delete(route('student.subjects.drop', $oldEnrollment))
            ->assertRedirect()
            ->assertSessionHas('error', 'Only current-term subjects can be dropped from self-service.');

        foreach ([$compulsoryEnrollment, $completedEnrollment, $oldEnrollment] as $enrollment) {
            $this->assertDatabaseHas('student_subject_enrollments', [
                'id' => $enrollment->id,
                'status' => $enrollment->status,
            ]);
        }
    }
}
