<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamAnomalyLog;
use App\Models\ExamRegistration;
use App\Models\ExamResult;
use App\Models\Classroom;
use App\Models\MarksAppeal;
use App\Models\Notification;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentSubjectEnrollment;
use App\Models\Subject;
use App\Models\Term;
use App\Models\Semester;
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

    public function test_exam_anomaly_creation_requires_unpublished_exam_and_matching_student_program(): void
    {
        $user = $this->examCellUser();
        $program = Program::factory()->create();
        $otherProgram = Program::factory()->create();
        $subject = Subject::factory()->create(['program_id' => $program->id]);
        $draftExam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'published_at' => null,
        ]);
        $publishedExam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'published_at' => now(),
        ]);
        $student = Student::factory()->create(['program_id' => $program->id]);
        $otherStudent = Student::factory()->create(['program_id' => $otherProgram->id]);

        $payload = [
            'student_id' => $student->id,
            'anomaly_type' => 'late_entry',
            'description' => 'Student entered after start time.',
            'severity' => 'medium',
            'action_taken' => 'none',
        ];

        $this->actingAs($user)
            ->post(route('exam-cell.anomalies.store'), $payload + ['exam_id' => $publishedExam->id])
            ->assertSessionHasErrors('exam_id');

        $this->actingAs($user)
            ->post(route('exam-cell.anomalies.store'), array_merge($payload, [
                'exam_id' => $draftExam->id,
                'student_id' => $otherStudent->id,
            ]))
            ->assertSessionHasErrors('student_id');

        $this->actingAs($user)
            ->post(route('exam-cell.anomalies.store'), $payload + ['exam_id' => $draftExam->id])
            ->assertRedirect(route('exam-cell.anomalies.index'))
            ->assertSessionHas('success', 'Anomaly logged.');

        $this->assertSame(1, ExamAnomalyLog::count());
        $this->assertDatabaseHas('exam_anomaly_logs', [
            'exam_id' => $draftExam->id,
            'student_id' => $student->id,
            'anomaly_type' => 'late_entry',
        ]);
        $this->assertDatabaseMissing('exam_anomaly_logs', [
            'exam_id' => $publishedExam->id,
        ]);
    }

    public function test_exam_cell_create_requires_active_matching_program_subject_term_and_classroom(): void
    {
        $user = $this->examCellUser();
        $program = Program::factory()->create(['is_active' => true]);
        $inactiveProgram = Program::factory()->create(['is_active' => false]);
        $otherProgram = Program::factory()->create(['is_active' => true]);
        $subject = Subject::factory()->create(['program_id' => $program->id, 'is_active' => true]);
        $otherSubject = Subject::factory()->create(['program_id' => $otherProgram->id, 'is_active' => true]);
        $inactiveSubject = Subject::factory()->create(['program_id' => $program->id, 'is_active' => false]);
        $term = Term::factory()->create(['program_id' => $program->id]);
        $otherTerm = Term::factory()->create(['program_id' => $otherProgram->id]);
        $semester = Semester::factory()->create(['number' => $term->term_number, 'name' => 'Semester ' . $term->term_number]);
        $otherSemester = Semester::factory()->create(['number' => $term->term_number + 1, 'name' => 'Semester Mismatch']);
        $classroom = Classroom::factory()->create(['is_active' => true]);
        $inactiveClassroom = Classroom::factory()->create(['is_active' => false]);

        $payload = [
            'name' => 'Exam Contract Test',
            'type' => 'internal',
            'program_id' => $program->id,
            'semester_id' => $semester->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'exam_date' => now()->addWeek()->toDateString(),
            'total_marks' => 100,
            'passing_marks' => 40,
            'classroom_id' => $classroom->id,
        ];

        $this->actingAs($user)
            ->from(route('exam-cell.exams.create'))
            ->post(route('exam-cell.exams.store'), array_merge($payload, ['program_id' => $inactiveProgram->id]))
            ->assertRedirect(route('exam-cell.exams.create'))
            ->assertSessionHasErrors('program_id');

        $this->actingAs($user)
            ->from(route('exam-cell.exams.create'))
            ->post(route('exam-cell.exams.store'), array_merge($payload, ['subject_id' => $otherSubject->id]))
            ->assertRedirect(route('exam-cell.exams.create'))
            ->assertSessionHasErrors('subject_id');

        $this->actingAs($user)
            ->from(route('exam-cell.exams.create'))
            ->post(route('exam-cell.exams.store'), array_merge($payload, ['subject_id' => $inactiveSubject->id]))
            ->assertRedirect(route('exam-cell.exams.create'))
            ->assertSessionHasErrors('subject_id');

        $this->actingAs($user)
            ->from(route('exam-cell.exams.create'))
            ->post(route('exam-cell.exams.store'), array_merge($payload, ['term_id' => $otherTerm->id]))
            ->assertRedirect(route('exam-cell.exams.create'))
            ->assertSessionHasErrors('term_id');

        $this->actingAs($user)
            ->from(route('exam-cell.exams.create'))
            ->post(route('exam-cell.exams.store'), array_merge($payload, ['semester_id' => $otherSemester->id]))
            ->assertRedirect(route('exam-cell.exams.create'))
            ->assertSessionHasErrors('semester_id');

        $this->actingAs($user)
            ->from(route('exam-cell.exams.create'))
            ->post(route('exam-cell.exams.store'), array_merge($payload, ['classroom_id' => $inactiveClassroom->id]))
            ->assertRedirect(route('exam-cell.exams.create'))
            ->assertSessionHasErrors('classroom_id');

        $this->actingAs($user)
            ->post(route('exam-cell.exams.store'), $payload)
            ->assertRedirect(route('exam-cell.exams'));

        $this->assertDatabaseCount('exams', 1);
        $this->assertDatabaseHas('exams', [
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'classroom_id' => $classroom->id,
        ]);
    }

    public function test_exam_cell_update_rejects_wrong_program_subject_and_term_before_history_exists(): void
    {
        $user = $this->examCellUser();
        $program = Program::factory()->create(['is_active' => true]);
        $otherProgram = Program::factory()->create(['is_active' => true]);
        $subject = Subject::factory()->create(['program_id' => $program->id, 'is_active' => true]);
        $otherSubject = Subject::factory()->create(['program_id' => $otherProgram->id, 'is_active' => true]);
        $term = Term::factory()->create(['program_id' => $program->id]);
        $otherTerm = Term::factory()->create(['program_id' => $otherProgram->id]);
        $semester = Semester::factory()->create(['number' => $term->term_number, 'name' => 'Semester ' . $term->term_number]);
        $exam = Exam::factory()->create([
            'semester_id' => $semester->id,
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'name' => 'Editable Contract Exam',
            'type' => 'internal',
            'total_marks' => 80,
            'passing_marks' => 32,
            'published_at' => null,
        ]);

        $payload = [
            'name' => 'Edited Contract Exam',
            'type' => 'internal',
            'program_id' => $program->id,
            'semester_id' => $semester->id,
            'subject_id' => $otherSubject->id,
            'term_id' => $otherTerm->id,
            'exam_date' => now()->addWeek()->toDateString(),
            'total_marks' => 90,
            'passing_marks' => 36,
        ];

        $this->actingAs($user)
            ->from(route('exam-cell.exams.edit', $exam))
            ->put(route('exam-cell.exams.update', $exam), $payload)
            ->assertRedirect(route('exam-cell.exams.edit', $exam))
            ->assertSessionHasErrors(['subject_id', 'term_id']);

        $exam->refresh();
        $this->assertSame('Editable Contract Exam', $exam->name);
        $this->assertSame($subject->id, $exam->subject_id);
        $this->assertSame($term->id, $exam->term_id);
    }

    public function test_exam_cell_create_rejects_invalid_schedule_timing_marks_and_semester_date(): void
    {
        $user = $this->examCellUser();
        $program = Program::factory()->create(['is_active' => true]);
        $subject = Subject::factory()->create(['program_id' => $program->id, 'is_active' => true]);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->addMonths(3),
        ]);
        $semester = Semester::factory()->create([
            'number' => 1,
            'name' => 'Semester 1',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->addMonths(3),
        ]);

        $payload = [
            'name' => 'Invalid Schedule Contract Exam',
            'type' => 'internal',
            'program_id' => $program->id,
            'semester_id' => $semester->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'exam_date' => now()->addMonths(4)->toDateString(),
            'start_time' => '11:00',
            'end_time' => '10:00',
            'total_marks' => 50,
            'passing_marks' => 60,
        ];

        $this->actingAs($user)
            ->from(route('exam-cell.exams.create'))
            ->post(route('exam-cell.exams.store'), $payload)
            ->assertRedirect(route('exam-cell.exams.create'))
            ->assertSessionHasErrors(['exam_date', 'end_time', 'passing_marks']);

        $this->assertDatabaseMissing('exams', [
            'name' => 'Invalid Schedule Contract Exam',
        ]);
    }

    public function test_exam_cell_update_rejects_invalid_schedule_timing_marks_and_semester_date(): void
    {
        $user = $this->examCellUser();
        $program = Program::factory()->create(['is_active' => true]);
        $subject = Subject::factory()->create(['program_id' => $program->id, 'is_active' => true]);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->addMonths(3),
        ]);
        $semester = Semester::factory()->create([
            'number' => 1,
            'name' => 'Semester 1',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->addMonths(3),
        ]);
        $exam = Exam::factory()->create([
            'semester_id' => $semester->id,
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'name' => 'Valid Schedule Contract Exam',
            'type' => 'internal',
            'exam_date' => now()->addWeek()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'total_marks' => 100,
            'passing_marks' => 40,
            'published_at' => null,
        ]);

        $this->actingAs($user)
            ->from(route('exam-cell.exams.edit', $exam))
            ->put(route('exam-cell.exams.update', $exam), [
                'name' => 'Invalid Updated Schedule Contract Exam',
                'type' => 'internal',
                'program_id' => $program->id,
                'semester_id' => $semester->id,
                'subject_id' => $subject->id,
                'term_id' => $term->id,
                'exam_date' => now()->addMonths(4)->toDateString(),
                'start_time' => '15:00',
                'end_time' => '14:00',
                'total_marks' => 80,
                'passing_marks' => 90,
            ])
            ->assertRedirect(route('exam-cell.exams.edit', $exam))
            ->assertSessionHasErrors(['exam_date', 'end_time', 'passing_marks']);

        $exam->refresh();
        $this->assertSame('Valid Schedule Contract Exam', $exam->name);
        $this->assertSame(100.0, (float) $exam->total_marks);
        $this->assertSame(40.0, (float) $exam->passing_marks);
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
            'published_at' => now(),
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
            'published_at' => now(),
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

    public function test_exam_cell_marks_appeal_review_revalidates_official_result_contract(): void
    {
        $user = $this->examCellUser();
        $program = Program::factory()->create();
        $subject = Subject::factory()->create(['program_id' => $program->id]);
        $draftExam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'published_at' => null,
            'total_marks' => 60,
        ]);
        $publishedExam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'published_at' => now(),
            'total_marks' => 60,
        ]);
        $student = Student::factory()->create(['program_id' => $program->id]);
        $otherStudent = Student::factory()->create(['program_id' => $program->id]);
        $draftResult = ExamResult::factory()->create([
            'exam_id' => $draftExam->id,
            'student_id' => $student->id,
            'marks_obtained' => 30,
            'is_absent' => false,
        ]);
        $absentResult = ExamResult::factory()->create([
            'exam_id' => $publishedExam->id,
            'student_id' => $student->id,
            'marks_obtained' => null,
            'is_absent' => true,
        ]);
        $mismatchedResult = ExamResult::factory()->create([
            'exam_id' => $publishedExam->id,
            'student_id' => $otherStudent->id,
            'marks_obtained' => 36,
            'is_absent' => false,
        ]);

        foreach ([$draftResult, $absentResult, $mismatchedResult] as $index => $result) {
            $appeal = MarksAppeal::create([
                'student_id' => $student->id,
                'exam_result_id' => $result->id,
                'reason' => 'Stale appeal',
                'description' => 'This appeal should no longer be reviewable.',
                'marks_claimed' => 45,
                'status' => 'pending',
            ]);

            $this->actingAs($user)
                ->from(route('exam-cell.marks-appeals'))
                ->post(route('exam-cell.marks-appeals.review', $appeal), [
                    'action' => 'approved',
                    'revised_marks' => 45 + $index,
                    'remarks' => 'Trying to correct stale record.',
                ])
                ->assertRedirect(route('exam-cell.marks-appeals'))
                ->assertSessionHas('error', 'This marks appeal is no longer valid for correction because the official published result is unavailable or not appealable.');

            $this->assertDatabaseHas('marks_appeals', [
                'id' => $appeal->id,
                'status' => 'pending',
                'revised_marks' => null,
            ]);
            $this->assertDatabaseHas('exam_results', [
                'id' => $result->id,
                'marks_obtained' => $result->marks_obtained,
            ]);
        }
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
            'exam_date' => now()->subDay()->toDateString(),
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
            'exam_date' => now()->subDay()->toDateString(),
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

    public function test_exam_cell_cannot_enter_or_publish_results_before_exam_date(): void
    {
        $user = $this->examCellUser();
        $program = Program::factory()->create();
        $term = Term::factory()->create(['name' => 'Future Result Term']);
        $subject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'Future Result Subject']);
        $exam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'exam_date' => now()->addWeek()->toDateString(),
            'total_marks' => 100,
            'published_at' => null,
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
                'marks' => [$student->id => 82],
            ])
            ->assertRedirect(route('exam-cell.grade-sheet', $exam))
            ->assertSessionHas('error', 'Exam results cannot be entered before the exam date.');

        $this->assertDatabaseMissing('exam_results', [
            'exam_id' => $exam->id,
            'student_id' => $student->id,
        ]);

        ExamResult::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'marks_obtained' => 82,
            'is_absent' => false,
        ]);

        $this->actingAs($user)
            ->from(route('exam-cell.grade-sheet', $exam))
            ->post(route('exam-cell.publish', $exam))
            ->assertRedirect(route('exam-cell.grade-sheet', $exam))
            ->assertSessionHas('error', 'Results cannot be published before the exam date.');

        $this->assertNull($exam->fresh()->published_at);
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
            'exam_date' => now()->subDay()->toDateString(),
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

    public function test_exam_cell_cannot_rewrite_or_delete_exam_with_draft_result_or_registration_history(): void
    {
        $user = $this->examCellUser();
        $program = Program::factory()->create();
        $term = Term::factory()->create(['program_id' => $program->id]);
        $subject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'Exam History Subject']);
        $alternateSubject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'Wrong Replacement Subject']);
        $exam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'name' => 'Draft Exam With History',
            'type' => 'internal',
            'total_marks' => 80,
            'passing_marks' => 32,
            'published_at' => null,
        ]);
        $student = Student::factory()->create(['program_id' => $program->id]);

        ExamResult::factory()->create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'marks_obtained' => 54,
            'is_absent' => false,
        ]);

        ExamRegistration::create([
            'student_id' => $student->id,
            'exam_id' => $exam->id,
            'status' => 'approved',
            'attendance_eligible' => true,
            'fee_cleared' => true,
            'approved_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->from(route('exam-cell.exams.edit', $exam))
            ->put(route('exam-cell.exams.update', $exam), [
                'name' => 'Changed Draft Exam With History',
                'type' => 'final',
                'program_id' => $program->id,
                'subject_id' => $alternateSubject->id,
                'term_id' => $term->id,
                'exam_date' => now()->addWeek()->toDateString(),
                'total_marks' => 100,
                'passing_marks' => 40,
            ])
            ->assertRedirect(route('exam-cell.exams'))
            ->assertSessionHas('error', 'Exams with result or registration history cannot have program, subject, term, type, or marks-scale fields changed.');

        $exam->refresh();
        $this->assertSame('Draft Exam With History', $exam->name);
        $this->assertSame($subject->id, $exam->subject_id);
        $this->assertSame('internal', $exam->type);
        $this->assertSame(80, (int) $exam->total_marks);

        $this->actingAs($user)
            ->from(route('exam-cell.exams'))
            ->delete(route('exam-cell.exams.destroy', $exam))
            ->assertRedirect(route('exam-cell.exams'))
            ->assertSessionHas('error', 'Exams with result or registration history cannot be deleted. Archive or cancel through an audited exam workflow instead.');

        $this->assertDatabaseHas('exams', ['id' => $exam->id]);
        $this->assertDatabaseHas('exam_results', ['exam_id' => $exam->id, 'student_id' => $student->id]);
        $this->assertDatabaseHas('exam_registrations', ['exam_id' => $exam->id, 'student_id' => $student->id]);
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
        $pending = Student::factory()->create(['program_id' => $program->id, 'enrollment_number' => 'HALL-PENDING']);
        $outsider = Student::factory()->create(['program_id' => $program->id, 'enrollment_number' => 'HALL-OUT']);

        foreach ([$eligible, $pending] as $student) {
            StudentSubjectEnrollment::create([
                'student_id' => $student->id,
                'subject_id' => $subject->id,
                'term_id' => $term->id,
                'enrollment_type' => 'compulsory',
                'status' => 'active',
            ]);
        }
        ExamRegistration::create([
            'student_id' => $eligible->id,
            'exam_id' => $exam->id,
            'status' => 'approved',
            'attendance_eligible' => true,
            'fee_cleared' => true,
            'approved_by' => $user->id,
        ]);
        ExamRegistration::create([
            'student_id' => $pending->id,
            'exam_id' => $exam->id,
            'status' => 'pending',
            'attendance_eligible' => true,
            'fee_cleared' => true,
        ]);

        $this->actingAs($user)
            ->get(route('exam-cell.hall-tickets', ['exam_id' => $exam->id]))
            ->assertStatus(200)
            ->assertSee('HALL-001')
            ->assertSee('HALL-PENDING')
            ->assertSee('Registration Review')
            ->assertDontSee('HALL-OUT');

        $this->actingAs($user)
            ->get(route('exam-cell.hall-ticket.download', [$exam, $outsider]))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('exam-cell.hall-ticket.download', [$exam, $pending]))
            ->assertForbidden();

        $exam->update(['published_at' => now()]);

        $this->actingAs($user)
            ->get(route('exam-cell.hall-tickets', ['exam_id' => $exam->id]))
            ->assertStatus(200)
            ->assertSee('0 hall-ticket ready')
            ->assertSee('HALL-001');

        $this->actingAs($user)
            ->get(route('exam-cell.hall-ticket.download', [$exam, $eligible]))
            ->assertForbidden();
    }

    public function test_exam_cell_hall_ticket_pdf_uses_readable_missing_data_labels(): void
    {
        $student = Student::factory()->make([
            'id' => 4101,
            'enrollment_number' => null,
            'program_id' => null,
        ]);
        $student->setRelation('user', null);
        $student->setRelation('program', null);

        $exam = Exam::factory()->make([
            'name' => null,
            'exam_date' => null,
            'start_time' => null,
            'end_time' => null,
            'total_marks' => null,
        ]);
        $exam->setRelation('program', null);
        $exam->setRelation('subject', null);
        $exam->setRelation('classroom', null);
        $exam->setRelation('term', null);
        $exam->setRelation('semester', null);

        $html = view('departmental.exam-cell.hall-ticket-pdf', compact('student', 'exam'))->render();

        $this->assertStringContainsString('Student name missing', $html);
        $this->assertStringContainsString('Enrollment number pending', $html);
        $this->assertStringContainsString('Program not linked', $html);
        $this->assertStringContainsString('Exam name pending', $html);
        $this->assertStringContainsString('Subject not linked', $html);
        $this->assertStringContainsString('Exam date not announced', $html);
        $this->assertStringContainsString('Time not announced', $html);
        $this->assertStringContainsString('Venue to be announced', $html);
        $this->assertStringContainsString('Total marks pending', $html);
        $this->assertStringContainsString('Term not linked', $html);
        $this->assertStringContainsString('EduManage College - Examination Cell', $html);
        $this->assertStringNotContainsString('N/A', $html);
        $this->assertStringNotContainsString('&mdash;', $html);
        $this->assertStringNotContainsString('&ndash;', $html);
        $this->assertStringNotContainsString('Ã¢', $html);
        $this->assertStringNotContainsString('â', $html);
    }
}
