<?php

namespace Tests\Feature;

use App\Models\{Batch, Department, DepartmentFeatureSetting, Exam, ExamResult, Program, Semester, Student, StudentSubjectEnrollment, Subject, Term, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcademicTranscriptCanonicalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Role::firstOrCreate(['name' => 'dean_academics', 'guard_name' => 'web']);
        $academic = Department::firstOrCreate(
            ['code' => 'ACAD'],
            ['name' => 'Academics', 'head_name' => 'Dean Academics', 'is_active' => true]
        );
        DepartmentFeatureSetting::create([
            'department_id' => $academic->id,
            'feature_key' => 'academic.reports',
            'feature_name' => 'Academic Reports',
            'is_enabled' => true,
        ]);

        $dean = User::factory()->create();
        $dean->assignRole('dean_academics');
        $program = Program::factory()->create(['is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id]);
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
            'current_term_id' => $term->id,
            'status' => 'active',
        ]);
        $enrolledSubject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Transcript Enrolled Subject',
            'credits' => 4,
        ]);
        $unenrolledSubject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Transcript Unenrolled Subject',
            'credits' => 5,
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $enrolledSubject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);
        $enrolledExam = Exam::factory()->create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'semester_id' => $semester->id,
            'subject_id' => $enrolledSubject->id,
            'name' => 'Transcript Enrolled Exam',
            'total_marks' => 100,
            'published_at' => now(),
        ]);
        Exam::factory()->create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'semester_id' => $semester->id,
            'subject_id' => $unenrolledSubject->id,
            'name' => 'Transcript Unenrolled Exam',
            'total_marks' => 100,
            'published_at' => now(),
        ]);
        ExamResult::factory()->create([
            'exam_id' => $enrolledExam->id,
            'student_id' => $student->id,
            'marks_obtained' => 88,
            'is_absent' => false,
        ]);

        return compact('dean', 'student');
    }

    public function test_academic_transcript_uses_canonical_enrolled_subjects_not_program_subjects(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['dean'])
            ->get(route('academic.transcripts.show', $fixture['student']))
            ->assertOk()
            ->assertSee('Transcript Enrolled Subject')
            ->assertSee('88')
            ->assertSee('4')
            ->assertDontSee('Transcript Unenrolled Subject')
            ->assertDontSee('5</td>', false);
    }

    public function test_academic_transcript_excludes_unpublished_draft_exam_results(): void
    {
        $fixture = $this->fixture();
        $student = $fixture['student'];
        $subject = StudentSubjectEnrollment::where('student_id', $student->id)->first()->subject;
        $term = $student->currentTerm;
        $semester = Semester::where('number', $term->term_number)->first();

        $draftExam = Exam::factory()->create([
            'program_id' => $student->program_id,
            'term_id' => $term->id,
            'semester_id' => $semester->id,
            'subject_id' => $subject->id,
            'name' => 'Draft Transcript Exam',
            'total_marks' => 100,
            'published_at' => null,
        ]);
        ExamResult::factory()->create([
            'exam_id' => $draftExam->id,
            'student_id' => $student->id,
            'marks_obtained' => 99,
            'is_absent' => false,
        ]);

        $this->actingAs($fixture['dean'])
            ->get(route('academic.transcripts.show', $student))
            ->assertOk()
            ->assertSee('Transcript Enrolled Subject')
            ->assertSee('88')
            ->assertDontSee('Draft Transcript Exam')
            ->assertDontSee('99.00/100', false);
    }
}
