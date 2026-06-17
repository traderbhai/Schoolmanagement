<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\MarksAppeal;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentSubjectEnrollment;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentMarksAppealWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function studentWithResult(): array
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $user = User::factory()->create(['name' => 'Appeal Student']);
        $user->assignRole('student');

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
        $subject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Quantitative Techniques',
        ]);
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'current_term_id' => $term->id,
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'status' => 'active',
        ]);
        $exam = Exam::factory()->create([
            'program_id' => $program->id,
            'semester_id' => $semester->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'name' => 'Mid Term Retest',
            'total_marks' => 60,
            'published_at' => now(),
        ]);
        $result = ExamResult::factory()->create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'marks_obtained' => 34,
        ]);

        return compact('user', 'student', 'program', 'term', 'semester', 'exam', 'result');
    }

    public function test_student_appeal_create_and_index_show_real_exam_name(): void
    {
        $fixture = $this->studentWithResult();

        $this->actingAs($fixture['user'])
            ->get(route('student.appeals.create'))
            ->assertStatus(200)
            ->assertSee('Quantitative Techniques')
            ->assertSee('Mid Term Retest');

        MarksAppeal::create([
            'student_id' => $fixture['student']->id,
            'exam_result_id' => $fixture['result']->id,
            'reason' => 'totalling_error',
            'description' => 'Please retotal the paper.',
            'marks_claimed' => 38,
            'status' => 'pending',
        ]);

        $this->actingAs($fixture['user'])
            ->get(route('student.appeals.index'))
            ->assertStatus(200)
            ->assertSee('Quantitative Techniques')
            ->assertSee('Mid Term Retest');
    }

    public function test_student_can_submit_one_valid_appeal_for_own_result(): void
    {
        $fixture = $this->studentWithResult();

        $this->actingAs($fixture['user'])
            ->post(route('student.appeals.store'), [
                'exam_result_id' => $fixture['result']->id,
                'reason' => 'totalling_error',
                'description' => 'The answer sheet total appears incorrect.',
                'marks_claimed' => 42,
            ])
            ->assertRedirect(route('student.appeals.index'))
            ->assertSessionHas('success', 'Marks appeal submitted. The Exam Cell will review it.');

        $this->assertDatabaseHas('marks_appeals', [
            'student_id' => $fixture['student']->id,
            'exam_result_id' => $fixture['result']->id,
            'marks_claimed' => 42,
            'status' => 'pending',
        ]);
    }

    public function test_student_cannot_claim_marks_above_exam_total_or_duplicate_appeal(): void
    {
        $fixture = $this->studentWithResult();

        $this->actingAs($fixture['user'])
            ->from(route('student.appeals.create'))
            ->post(route('student.appeals.store'), [
                'exam_result_id' => $fixture['result']->id,
                'reason' => 'wrong_entry',
                'description' => 'The entered marks appear too low.',
                'marks_claimed' => 70,
            ])
            ->assertRedirect(route('student.appeals.create'))
            ->assertSessionHasErrors('marks_claimed');

        MarksAppeal::create([
            'student_id' => $fixture['student']->id,
            'exam_result_id' => $fixture['result']->id,
            'reason' => 'wrong_entry',
            'description' => 'Already submitted.',
            'marks_claimed' => 42,
            'status' => 'pending',
        ]);

        $this->actingAs($fixture['user'])
            ->post(route('student.appeals.store'), [
                'exam_result_id' => $fixture['result']->id,
                'reason' => 'totalling_error',
                'description' => 'Second appeal attempt.',
                'marks_claimed' => 43,
            ])
            ->assertStatus(422);
    }

    public function test_student_cannot_view_or_submit_appeal_for_unenrolled_or_absent_result(): void
    {
        $fixture = $this->studentWithResult();
        $unenrolledSubject = Subject::factory()->create([
            'program_id' => $fixture['program']->id,
            'term_number' => 1,
            'name' => 'Unenrolled Result Subject',
        ]);
        $unenrolledExam = Exam::factory()->create([
            'program_id' => $fixture['program']->id,
            'semester_id' => $fixture['semester']->id,
            'term_id' => $fixture['term']->id,
            'subject_id' => $unenrolledSubject->id,
            'name' => 'Unenrolled Result Exam',
            'total_marks' => 60,
        ]);
        $unenrolledResult = ExamResult::factory()->create([
            'exam_id' => $unenrolledExam->id,
            'student_id' => $fixture['student']->id,
            'marks_obtained' => 35,
            'is_absent' => false,
        ]);
        $absentExam = Exam::factory()->create([
            'program_id' => $fixture['program']->id,
            'semester_id' => $fixture['semester']->id,
            'term_id' => $fixture['term']->id,
            'subject_id' => $fixture['exam']->subject_id,
            'name' => 'Absent Enrolled Exam',
            'total_marks' => 60,
            'published_at' => now(),
        ]);
        $absentResult = ExamResult::factory()->create([
            'exam_id' => $absentExam->id,
            'student_id' => $fixture['student']->id,
            'marks_obtained' => null,
            'is_absent' => true,
        ]);

        $this->actingAs($fixture['user'])
            ->get(route('student.appeals.create'))
            ->assertOk()
            ->assertSee('Mid Term Retest')
            ->assertDontSee('Unenrolled Result Exam');

        $this->actingAs($fixture['user'])
            ->post(route('student.appeals.store'), [
                'exam_result_id' => $unenrolledResult->id,
                'reason' => 'wrong_entry',
                'description' => 'This result should not be appealable.',
                'marks_claimed' => 45,
            ])
            ->assertNotFound();

        $this->actingAs($fixture['user'])
            ->post(route('student.appeals.store'), [
                'exam_result_id' => $absentResult->id,
                'reason' => 'wrong_entry',
                'description' => 'Absent result should not be appealable.',
                'marks_claimed' => 45,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('marks_appeals', [
            'student_id' => $fixture['student']->id,
            'exam_result_id' => $unenrolledResult->id,
        ]);
        $this->assertDatabaseMissing('marks_appeals', [
            'student_id' => $fixture['student']->id,
            'exam_result_id' => $absentResult->id,
        ]);
    }

    public function test_student_cannot_view_or_submit_appeal_for_unpublished_draft_result(): void
    {
        $fixture = $this->studentWithResult();
        $draftExam = Exam::factory()->create([
            'program_id' => $fixture['program']->id,
            'semester_id' => $fixture['semester']->id,
            'term_id' => $fixture['term']->id,
            'subject_id' => $fixture['exam']->subject_id,
            'name' => 'Draft Internal Marks',
            'total_marks' => 60,
            'published_at' => null,
        ]);
        $draftResult = ExamResult::factory()->create([
            'exam_id' => $draftExam->id,
            'student_id' => $fixture['student']->id,
            'marks_obtained' => 40,
            'is_absent' => false,
        ]);

        $this->actingAs($fixture['user'])
            ->get(route('student.appeals.create'))
            ->assertOk()
            ->assertSee('Mid Term Retest')
            ->assertDontSee('Draft Internal Marks');

        $this->actingAs($fixture['user'])
            ->post(route('student.appeals.store'), [
                'exam_result_id' => $draftResult->id,
                'reason' => 'wrong_entry',
                'description' => 'Draft marks should not be appealable.',
                'marks_claimed' => 45,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('marks_appeals', [
            'student_id' => $fixture['student']->id,
            'exam_result_id' => $draftResult->id,
        ]);
    }
}
