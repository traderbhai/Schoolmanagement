<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamAnomalyLog;
use App\Models\ExamResult;
use App\Models\MarksAppeal;
use App\Models\Notification;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentSubjectEnrollment;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExamCellDashboardGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private function examCellUser(): User
    {
        Role::firstOrCreate(['name' => 'exam_cell', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('exam_cell');

        return $user;
    }

    public function test_exam_cell_dashboard_prioritizes_completed_exams_without_results(): void
    {
        $user = $this->examCellUser();
        $program = Program::factory()->create();
        $subject = Subject::factory()->create(['program_id' => $program->id]);

        Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'name' => 'Internal Assessment 1',
            'exam_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('exam-cell.dashboard'))
            ->assertStatus(200)
            ->assertSee('Exam Cell Priority')
            ->assertSee('Enter results for 1 completed exam')
            ->assertSee('Enter Results')
            ->assertSee(route('exam-cell.results'), false)
            ->assertSee(route('exam-cell.exams.create'), false)
            ->assertDontSee(route('admin.exams.create'), false);
    }

    public function test_exam_cell_dashboard_prioritizes_open_anomalies(): void
    {
        $user = $this->examCellUser();
        $program = Program::factory()->create();
        $subject = Subject::factory()->create(['program_id' => $program->id]);
        $exam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'name' => 'End Semester Exam',
            'exam_date' => now()->subDay()->toDateString(),
        ]);
        $student = Student::factory()->create(['program_id' => $program->id]);

        ExamAnomalyLog::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'anomaly_type' => 'malpractice',
            'description' => 'Suspicious activity reported by invigilator.',
            'severity' => 'high',
            'reported_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('exam-cell.dashboard'))
            ->assertStatus(200)
            ->assertSee('Resolve 1 open exam anomaly')
            ->assertSee('Review Anomalies')
            ->assertSee(route('exam-cell.anomalies.index'), false)
            ->assertDontSee('Enter results for 1 completed exam');
    }

    public function test_exam_cell_anomaly_resolution_requires_notes_and_locks_history(): void
    {
        $user = $this->examCellUser();
        $program = Program::factory()->create();
        $subject = Subject::factory()->create(['program_id' => $program->id]);
        $exam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
        ]);
        $student = Student::factory()->create(['program_id' => $program->id]);
        $anomaly = ExamAnomalyLog::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'anomaly_type' => 'late_entry',
            'description' => 'Student entered after reporting time.',
            'severity' => 'medium',
            'reported_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->from(route('exam-cell.anomalies.show', $anomaly))
            ->post(route('exam-cell.anomalies.resolve', $anomaly), [
                'action_taken' => 'warning',
            ])
            ->assertRedirect(route('exam-cell.anomalies.show', $anomaly))
            ->assertSessionHasErrors('resolution_notes');

        $this->actingAs($user)
            ->post(route('exam-cell.anomalies.resolve', $anomaly), [
                'action_taken' => 'warning',
                'resolution_notes' => 'Late entry noted and warning issued.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $anomaly->refresh();
        $this->assertSame('warning', $anomaly->action_taken);
        $this->assertSame('Late entry noted and warning issued.', $anomaly->resolution_notes);
        $this->assertSame($user->id, $anomaly->resolved_by);
        $this->assertNotNull($anomaly->resolved_at);

        $this->actingAs($user)
            ->from(route('exam-cell.anomalies.show', $anomaly))
            ->post(route('exam-cell.anomalies.resolve', $anomaly), [
                'action_taken' => 'cancelled',
                'resolution_notes' => 'Changing history.',
            ])
            ->assertRedirect(route('exam-cell.anomalies.show', $anomaly))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('exam_anomaly_logs', [
            'id' => $anomaly->id,
            'action_taken' => 'warning',
            'resolution_notes' => 'Late entry noted and warning issued.',
            'resolved_by' => $user->id,
        ]);
    }

    public function test_exam_cell_marks_appeal_review_uses_exam_result_contract(): void
    {
        $user = $this->examCellUser();
        $program = Program::factory()->create();
        $subject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'Business Analytics']);
        $exam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'exam_date' => now()->subDay()->toDateString(),
        ]);
        $student = Student::factory()->create(['program_id' => $program->id]);
        $result = ExamResult::factory()->create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'marks_obtained' => 41,
        ]);
        $appeal = MarksAppeal::create([
            'student_id' => $student->id,
            'exam_result_id' => $result->id,
            'reason' => 'Retotal requested.',
            'description' => 'Student requested retotal after comparing answer sheet.',
            'marks_claimed' => 50,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->get(route('exam-cell.marks-appeals'))
            ->assertStatus(200)
            ->assertSee('Business Analytics')
            ->assertSee(route('exam-cell.marks-appeals.review', $appeal), false)
            ->assertSee('data-current-marks="41.00"', false);

        $this->actingAs($user)
            ->post(route('exam-cell.marks-appeals.review', $appeal), [
                'action' => 'approved',
                'revised_marks' => 48,
                'remarks' => 'Retotal approved.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Appeal reviewed.');

        $appeal->refresh();
        $result->refresh();
        $this->assertSame('resolved', $appeal->status);
        $this->assertSame($user->id, $appeal->reviewed_by);
        $this->assertSame('Retotal approved.', $appeal->review_remarks);
        $this->assertEquals(48.0, (float) $result->marks_obtained);
    }

    public function test_exam_cell_marks_appeal_review_enforces_final_decision_rules_and_locks_history(): void
    {
        $user = $this->examCellUser();
        $program = Program::factory()->create();
        $subject = Subject::factory()->create(['program_id' => $program->id]);
        $exam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'total_marks' => 60,
        ]);
        $student = Student::factory()->create(['program_id' => $program->id]);
        $result = ExamResult::factory()->create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'marks_obtained' => 32,
        ]);
        $appeal = MarksAppeal::create([
            'student_id' => $student->id,
            'exam_result_id' => $result->id,
            'reason' => 'Wrong marks posted.',
            'description' => 'Student believes marks are low.',
            'marks_claimed' => 44,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->from(route('exam-cell.marks-appeals'))
            ->post(route('exam-cell.marks-appeals.review', $appeal), [
                'action' => 'approved',
                'revised_marks' => 44,
            ])
            ->assertRedirect(route('exam-cell.marks-appeals'))
            ->assertSessionHasErrors('remarks');

        $this->actingAs($user)
            ->from(route('exam-cell.marks-appeals'))
            ->post(route('exam-cell.marks-appeals.review', $appeal), [
                'action' => 'approved',
                'remarks' => 'Approved but missing revised marks.',
            ])
            ->assertRedirect(route('exam-cell.marks-appeals'))
            ->assertSessionHasErrors('revised_marks');

        $this->actingAs($user)
            ->from(route('exam-cell.marks-appeals'))
            ->post(route('exam-cell.marks-appeals.review', $appeal), [
                'action' => 'approved',
                'revised_marks' => 61,
                'remarks' => 'Too high.',
            ])
            ->assertRedirect(route('exam-cell.marks-appeals'))
            ->assertSessionHasErrors('revised_marks');

        $this->actingAs($user)
            ->post(route('exam-cell.marks-appeals.review', $appeal), [
                'action' => 'approved',
                'revised_marks' => 45,
                'remarks' => 'Answer two was not counted.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('marks_appeals', [
            'id' => $appeal->id,
            'status' => 'resolved',
            'revised_marks' => 45,
            'review_remarks' => 'Answer two was not counted.',
        ]);
        $this->assertDatabaseHas('exam_results', [
            'id' => $result->id,
            'marks_obtained' => 45,
        ]);

        $this->actingAs($user)
            ->from(route('exam-cell.marks-appeals'))
            ->post(route('exam-cell.marks-appeals.review', $appeal), [
                'action' => 'rejected',
                'remarks' => 'Changing history.',
            ])
            ->assertRedirect(route('exam-cell.marks-appeals'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('marks_appeals', [
            'id' => $appeal->id,
            'status' => 'resolved',
            'revised_marks' => 45,
        ]);
    }

    public function test_exam_cell_grade_sheet_and_marks_are_limited_to_subject_enrolled_students(): void
    {
        $user = $this->examCellUser();
        $program = Program::factory()->create();
        $term = Term::factory()->create(['name' => 'Term Exam Scope']);
        $subject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'Operations Research']);
        $exam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'name' => 'Operations Mid Term',
        ]);
        $eligible = Student::factory()->create(['program_id' => $program->id, 'enrollment_number' => 'ELIG-001']);
        $outsider = Student::factory()->create(['program_id' => $program->id, 'enrollment_number' => 'OUT-001']);

        StudentSubjectEnrollment::create([
            'student_id' => $eligible->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('exam-cell.grade-sheet', $exam))
            ->assertStatus(200)
            ->assertSee($eligible->user->name)
            ->assertDontSee($outsider->user->name);

        $this->actingAs($user)
            ->from(route('exam-cell.grade-sheet', $exam))
            ->post(route('exam-cell.save-marks', $exam), [
                'marks' => [
                    $eligible->id => 72,
                    $outsider->id => 88,
                ],
            ])
            ->assertRedirect(route('exam-cell.grade-sheet', $exam))
            ->assertSessionHasErrors('marks');

        $this->assertDatabaseMissing('exam_results', [
            'exam_id' => $exam->id,
            'student_id' => $outsider->id,
        ]);

        $this->actingAs($user)
            ->post(route('exam-cell.save-marks', $exam), [
                'marks' => [
                    $eligible->id => 72,
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('exam_results', [
            'exam_id' => $exam->id,
            'student_id' => $eligible->id,
            'marks_obtained' => 72,
        ]);
    }

    public function test_exam_cell_grade_sheet_validates_marks_and_absent_state(): void
    {
        $user = $this->examCellUser();
        $program = Program::factory()->create();
        $term = Term::factory()->create(['name' => 'Term Exam Validation']);
        $subject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'Financial Reporting']);
        $exam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'total_marks' => 40,
        ]);
        $student = Student::factory()->create(['program_id' => $program->id]);
        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->from(route('exam-cell.grade-sheet', $exam))
            ->post(route('exam-cell.save-marks', $exam), [
                'marks' => [$student->id => 41],
            ])
            ->assertRedirect(route('exam-cell.grade-sheet', $exam))
            ->assertSessionHasErrors("marks.{$student->id}");

        $this->actingAs($user)
            ->from(route('exam-cell.grade-sheet', $exam))
            ->post(route('exam-cell.save-marks', $exam), [
                'marks' => [$student->id => ''],
            ])
            ->assertRedirect(route('exam-cell.grade-sheet', $exam))
            ->assertSessionHasErrors("marks.{$student->id}");

        ExamResult::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'marks_obtained' => 35,
            'is_absent' => false,
        ]);

        $this->actingAs($user)
            ->post(route('exam-cell.save-marks', $exam), [
                'marks' => [$student->id => 36],
                'absent' => [$student->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('exam_results', [
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'marks_obtained' => null,
            'is_absent' => true,
        ]);
    }

    public function test_exam_cell_publish_requires_complete_roster_and_open_case_clearance(): void
    {
        $user = $this->examCellUser();
        $program = Program::factory()->create();
        $term = Term::factory()->create(['name' => 'Publication Review Term']);
        $subject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'Strategic Management']);
        $exam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'total_marks' => 100,
        ]);
        $first = Student::factory()->create(['program_id' => $program->id]);
        $second = Student::factory()->create(['program_id' => $program->id]);

        foreach ([$first, $second] as $student) {
            StudentSubjectEnrollment::create([
                'student_id' => $student->id,
                'subject_id' => $subject->id,
                'term_id' => $term->id,
                'enrollment_type' => 'compulsory',
                'status' => 'active',
            ]);
        }

        ExamResult::create([
            'exam_id' => $exam->id,
            'student_id' => $first->id,
            'marks_obtained' => 76,
            'is_absent' => false,
            'remarks' => 'Faculty moderation note.',
        ]);

        $this->actingAs($user)
            ->from(route('exam-cell.grade-sheet', $exam))
            ->post(route('exam-cell.publish', $exam))
            ->assertRedirect(route('exam-cell.grade-sheet', $exam))
            ->assertSessionHas('error', 'Results cannot be published until marks or absence are entered for 1 eligible student(s).');

        $this->assertNull($exam->fresh()->published_at);

        ExamResult::create([
            'exam_id' => $exam->id,
            'student_id' => $second->id,
            'marks_obtained' => null,
            'is_absent' => true,
        ]);

        $anomaly = ExamAnomalyLog::create([
            'exam_id' => $exam->id,
            'student_id' => $second->id,
            'anomaly_type' => 'late_entry',
            'description' => 'Student entered after start time.',
            'severity' => 'medium',
            'reported_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->from(route('exam-cell.grade-sheet', $exam))
            ->post(route('exam-cell.publish', $exam))
            ->assertRedirect(route('exam-cell.grade-sheet', $exam))
            ->assertSessionHas('error', 'Results cannot be published while 1 exam anomaly case(s) are unresolved.');

        $anomaly->update([
            'action_taken' => 'warning',
            'resolution_notes' => 'Resolved before publication.',
            'resolved_by' => $user->id,
            'resolved_at' => now(),
        ]);

        $appeal = MarksAppeal::create([
            'student_id' => $first->id,
            'exam_result_id' => ExamResult::where('exam_id', $exam->id)->where('student_id', $first->id)->value('id'),
            'reason' => 'Retotal requested.',
            'description' => 'Pending before official publication.',
            'marks_claimed' => 82,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->from(route('exam-cell.grade-sheet', $exam))
            ->post(route('exam-cell.publish', $exam))
            ->assertRedirect(route('exam-cell.grade-sheet', $exam))
            ->assertSessionHas('error', 'Results cannot be published while 1 marks appeal(s) are pending review.');

        $appeal->update([
            'status' => 'resolved',
            'reviewed_by' => $user->id,
            'review_remarks' => 'No change after review.',
            'reviewed_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('exam-cell.publish', $exam))
            ->assertRedirect(route('exam-cell.grade-sheet', $exam))
            ->assertSessionHas('success', 'Results published successfully.');

        $exam->refresh();
        $this->assertNotNull($exam->published_at);
        $this->assertSame($user->id, $exam->published_by);
        $this->assertSame(2, Notification::where('type', 'result_published')->count());
        foreach ([$first, $second] as $student) {
            $this->assertDatabaseHas('notifications', [
                'user_id' => $student->user_id,
                'type' => 'result_published',
                'title' => 'Exam result published',
                'action_url' => route('student.results', ['semester_id' => $exam->semester_id]),
                'is_read' => false,
            ]);
        }
        $this->assertDatabaseHas('exam_results', [
            'exam_id' => $exam->id,
            'student_id' => $first->id,
            'remarks' => 'Faculty moderation note.',
        ]);

        $this->actingAs($user)
            ->from(route('exam-cell.grade-sheet', $exam))
            ->post(route('exam-cell.save-marks', $exam), [
                'marks' => [$first->id => 91],
            ])
            ->assertRedirect(route('exam-cell.grade-sheet', $exam))
            ->assertSessionHas('error', 'Published results are locked. Reopen through an approved correction workflow before editing marks.');

        $this->assertDatabaseHas('exam_results', [
            'exam_id' => $exam->id,
            'student_id' => $first->id,
            'marks_obtained' => 76,
            'remarks' => 'Faculty moderation note.',
        ]);

        $this->actingAs($user)
            ->from(route('exam-cell.grade-sheet', $exam))
            ->post(route('exam-cell.publish', $exam))
            ->assertRedirect(route('exam-cell.grade-sheet', $exam))
            ->assertSessionHas('error', 'Results are already published for this exam.');

        $this->assertSame(2, Notification::where('type', 'result_published')->count());
    }

    public function test_exam_cell_cannot_edit_or_delete_published_exam_schedule(): void
    {
        $user = $this->examCellUser();
        $program = Program::factory()->create(['is_active' => true]);
        $term = Term::factory()->create(['program_id' => $program->id]);
        $subject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'Published Exam Subject']);
        $exam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'name' => 'Published Exam Schedule',
            'type' => 'internal',
            'total_marks' => 80,
            'passing_marks' => 32,
            'published_at' => now(),
            'published_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('exam-cell.exams'))
            ->assertOk()
            ->assertSee('Published Exam Schedule')
            ->assertSee('Published exam locked');

        $this->actingAs($user)
            ->from(route('exam-cell.exams.edit', $exam))
            ->put(route('exam-cell.exams.update', $exam), [
                'name' => 'Changed Exam Schedule',
                'type' => 'internal',
                'program_id' => $program->id,
                'subject_id' => $subject->id,
                'term_id' => $term->id,
                'exam_date' => now()->addWeek()->toDateString(),
                'total_marks' => 90,
                'passing_marks' => 35,
            ])
            ->assertRedirect(route('exam-cell.exams'))
            ->assertSessionHas('error', 'Published exams cannot be edited because official result history is locked.');

        $this->assertDatabaseHas('exams', [
            'id' => $exam->id,
            'name' => 'Published Exam Schedule',
            'total_marks' => 80,
        ]);

        $this->actingAs($user)
            ->from(route('exam-cell.exams'))
            ->delete(route('exam-cell.exams.destroy', $exam))
            ->assertRedirect(route('exam-cell.exams'))
            ->assertSessionHas('error', 'Published exams cannot be deleted because official result history is locked.');

        $this->assertDatabaseHas('exams', ['id' => $exam->id]);
    }

    public function test_exam_cell_hall_ticket_download_requires_subject_enrollment(): void
    {
        $user = $this->examCellUser();
        $program = Program::factory()->create();
        $term = Term::factory()->create();
        $subject = Subject::factory()->create(['program_id' => $program->id]);
        $exam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'exam_date' => now()->addWeek(),
        ]);
        $eligible = Student::factory()->create(['program_id' => $program->id, 'enrollment_number' => 'HALL-001']);
        $outsider = Student::factory()->create(['program_id' => $program->id, 'enrollment_number' => 'HALL-OUT']);

        StudentSubjectEnrollment::create([
            'student_id' => $eligible->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('exam-cell.hall-tickets', ['exam_id' => $exam->id]))
            ->assertStatus(200)
            ->assertSee('HALL-001')
            ->assertDontSee('HALL-OUT');

        $this->actingAs($user)
            ->get(route('exam-cell.hall-ticket.download', [$exam, $outsider]))
            ->assertForbidden();
    }
}
