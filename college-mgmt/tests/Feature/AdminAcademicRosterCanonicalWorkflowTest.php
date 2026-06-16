<?php

namespace Tests\Feature;

use App\Models\{Attendance, Batch, Classroom, Course, Exam, ExamResult, Program, Semester, Student, StudentSubjectEnrollment, Subject, Teacher, Term, TimetableEntry, TimetableSlot, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAcademicRosterCanonicalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $program = Program::factory()->create(['is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $course = Course::factory()->create();
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
        $subject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Admin Canonical Subject',
        ]);
        $student = Student::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'course_id' => $course->id,
            'current_term_id' => $term->id,
            'roll_number' => 'ADM001',
            'status' => 'active',
        ]);
        $student->user->forceFill(['name' => 'Admin Canonical Student'])->save();
        $outsider = Student::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'course_id' => $course->id,
            'current_term_id' => $term->id,
            'roll_number' => 'ADM002',
            'status' => 'active',
        ]);
        $outsider->user->forceFill(['name' => 'Admin Outsider Student'])->save();

        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);

        $exam = Exam::factory()->create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'semester_id' => $semester->id,
            'subject_id' => $subject->id,
            'name' => 'Admin Canonical Exam',
            'total_marks' => 100,
        ]);
        $entry = TimetableEntry::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'course_id' => $course->id,
            'term_id' => $term->id,
            'semester_id' => $semester->id,
            'subject_id' => $subject->id,
            'teacher_id' => Teacher::factory()->create()->id,
            'classroom_id' => Classroom::factory()->create()->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => now()->dayOfWeekIso,
            'is_active' => true,
        ]);

        return compact('admin', 'student', 'outsider', 'exam', 'entry');
    }

    public function test_admin_exam_results_use_canonical_subject_roster_and_reject_outsider_results(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['admin'])
            ->get(route('admin.exams.results', $fixture['exam']))
            ->assertOk()
            ->assertSee('Admin Canonical Student')
            ->assertDontSee('Admin Outsider Student');

        $this->actingAs($fixture['admin'])
            ->post(route('admin.exams.results.save', $fixture['exam']), [
                'results' => [
                    $fixture['student']->id => ['marks_obtained' => 91],
                    $fixture['outsider']->id => ['marks_obtained' => 74],
                ],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('exam_results', ['student_id' => $fixture['outsider']->id]);

        $this->actingAs($fixture['admin'])
            ->post(route('admin.exams.results.save', $fixture['exam']), [
                'results' => [
                    $fixture['student']->id => ['marks' => 91, 'grade' => 'A'],
                ],
            ])
            ->assertRedirect(route('admin.exams.show', $fixture['exam']));

        $this->assertDatabaseHas('exam_results', [
            'exam_id' => $fixture['exam']->id,
            'student_id' => $fixture['student']->id,
            'marks_obtained' => 91,
            'grade' => 'A',
        ]);
    }

    public function test_admin_exam_results_validate_marks_and_absent_state(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['admin'])
            ->from(route('admin.exams.results', $fixture['exam']))
            ->post(route('admin.exams.results.save', $fixture['exam']), [
                'results' => [
                    $fixture['student']->id => ['marks' => 101],
                ],
            ])
            ->assertRedirect(route('admin.exams.results', $fixture['exam']))
            ->assertSessionHasErrors("results.{$fixture['student']->id}.marks");

        $this->actingAs($fixture['admin'])
            ->from(route('admin.exams.results', $fixture['exam']))
            ->post(route('admin.exams.results.save', $fixture['exam']), [
                'results' => [
                    $fixture['student']->id => ['remarks' => 'Present but missing marks'],
                ],
            ])
            ->assertRedirect(route('admin.exams.results', $fixture['exam']))
            ->assertSessionHasErrors("results.{$fixture['student']->id}.marks");

        ExamResult::create([
            'exam_id' => $fixture['exam']->id,
            'student_id' => $fixture['student']->id,
            'marks_obtained' => 88,
            'grade' => 'A',
            'is_absent' => false,
        ]);

        $this->actingAs($fixture['admin'])
            ->post(route('admin.exams.results.save', $fixture['exam']), [
                'results' => [
                    $fixture['student']->id => [
                        'is_absent' => '1',
                        'marks' => 90,
                        'grade' => 'A+',
                        'remarks' => 'Absent with medical note',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.exams.show', $fixture['exam']));

        $this->assertDatabaseHas('exam_results', [
            'exam_id' => $fixture['exam']->id,
            'student_id' => $fixture['student']->id,
            'marks_obtained' => null,
            'grade' => null,
            'is_absent' => true,
            'remarks' => 'Absent with medical note',
        ]);
    }

    public function test_admin_attendance_uses_canonical_subject_roster_and_rejects_outsider_attendance(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['admin'])
            ->get(route('admin.attendance.mark', [
                'timetable_entry_id' => $fixture['entry']->id,
                'date' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Admin Canonical Student')
            ->assertDontSee('Admin Outsider Student');

        $this->actingAs($fixture['admin'])
            ->post(route('admin.attendance.store'), [
                'timetable_entry_id' => $fixture['entry']->id,
                'date' => now()->toDateString(),
                'attendance' => [
                    $fixture['student']->id => 'present',
                    $fixture['outsider']->id => 'present',
                ],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('attendances', ['student_id' => $fixture['outsider']->id]);

        $this->actingAs($fixture['admin'])
            ->post(route('admin.attendance.store'), [
                'timetable_entry_id' => $fixture['entry']->id,
                'date' => now()->toDateString(),
                'attendance' => [
                    $fixture['student']->id => 'present',
                ],
            ])
            ->assertRedirect(route('admin.attendance.index'));

        $this->assertDatabaseHas('attendances', [
            'student_id' => $fixture['student']->id,
            'timetable_entry_id' => $fixture['entry']->id,
            'status' => 'present',
        ]);
    }
}
