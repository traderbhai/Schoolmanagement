<?php

namespace Tests\Feature;

use App\Models\{Exam, Program, Student, StudentSubjectEnrollment, Subject, Term, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentAdmitCardWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(): array
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $program = Program::factory()->create();
        $user = User::factory()->create();
        $user->assignRole('student');
        $student = Student::factory()->create(['user_id' => $user->id, 'program_id' => $program->id]);

        return [$user, $student, $program];
    }

    public function test_student_admit_cards_show_only_subject_enrolled_exams_and_block_direct_download(): void
    {
        [$user, $student, $program] = $this->makeStudent();
        $term = Term::factory()->create();
        $eligibleSubject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'Eligible Accounting']);
        $otherSubject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'Hidden Finance']);
        $eligibleExam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $eligibleSubject->id,
            'term_id' => $term->id,
            'name' => 'Eligible Accounting Final',
            'exam_date' => now()->addWeek(),
        ]);
        $otherExam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $otherSubject->id,
            'term_id' => $term->id,
            'name' => 'Hidden Finance Final',
            'exam_date' => now()->addWeek(),
        ]);

        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $eligibleSubject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('student.admit-cards.index'))
            ->assertStatus(200)
            ->assertSee('Eligible Accounting Final')
            ->assertSee(route('student.admit-cards.download', $eligibleExam), false)
            ->assertDontSee('Hidden Finance Final')
            ->assertDontSee(route('student.admit-cards.download', $otherExam), false);

        $this->actingAs($user)
            ->get(route('student.admit-cards.download', $otherExam))
            ->assertForbidden();
    }
}
