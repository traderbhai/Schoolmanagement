<?php

namespace Tests\Feature;

use App\Models\{Exam, ExamResult, Program, Semester, Student, StudentSubjectEnrollment, Subject, Term, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentResultsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_results_use_canonical_subject_enrollments_without_legacy_enrollment_rows(): void
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $program = Program::factory()->create();
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Term 1',
            'is_current' => true,
        ]);
        $semester = Semester::factory()->create([
            'number' => 1,
            'name' => 'Term 1',
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
        $subject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'credits' => 4,
            'name' => 'Canonical Result Subject',
            'code' => 'CRS201',
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);
        $exam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'semester_id' => $semester->id,
            'name' => 'Canonical Result Exam',
            'total_marks' => 100,
            'passing_marks' => 35,
        ]);
        ExamResult::factory()->create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'marks_obtained' => 82,
            'is_absent' => false,
        ]);

        $this->actingAs($user)
            ->get(route('student.results'))
            ->assertOk()
            ->assertSee('Term 1')
            ->assertSee('Canonical Result Subject')
            ->assertSee('82')
            ->assertSee('Pass');

        $report = app(\App\Services\GradeService::class)->calculateStudentSemesterReport($student->id, $semester->id);
        $this->assertSame(4, $report['total_credits']);
        $this->assertSame('Pass', $report['result']);
        $this->assertSame(9.0, app(\App\Services\GradeService::class)->calculateCGPA($student->id));
    }
}
