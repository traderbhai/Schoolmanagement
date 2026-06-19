<?php

namespace Tests\Feature;

use App\Models\{Attendance, Batch, Classroom, Course, Exam, ExamRegistration, ExamResult, Program, Semester, Student, StudentSubjectEnrollment, Subject, Teacher, Term, TimetableEntry, TimetableSlot, TimetableVersion, User};
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
            'type' => 'internal',
            'exam_date' => now()->subDay()->toDateString(),
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

    public function test_admin_cannot_enter_results_before_exam_date(): void
    {
        $fixture = $this->fixture();
        $fixture['exam']->forceFill(['exam_date' => now()->addDays(3)->toDateString()])->save();

        $this->actingAs($fixture['admin'])
            ->from(route('admin.exams.results', $fixture['exam']))
            ->post(route('admin.exams.results.save', $fixture['exam']), [
                'results' => [
                    $fixture['student']->id => ['marks' => 91, 'grade' => 'A'],
                ],
            ])
            ->assertRedirect(route('admin.exams.results', $fixture['exam']))
            ->assertSessionHas('error', 'Exam results cannot be entered before the exam date.');

        $this->assertDatabaseMissing('exam_results', [
            'exam_id' => $fixture['exam']->id,
            'student_id' => $fixture['student']->id,
        ]);
    }

    public function test_admin_cannot_change_results_after_exam_publication(): void
    {
        $fixture = $this->fixture();
        $fixture['exam']->forceFill([
            'published_at' => now(),
            'published_by' => $fixture['admin']->id,
        ])->save();

        ExamResult::create([
            'exam_id' => $fixture['exam']->id,
            'student_id' => $fixture['student']->id,
            'marks_obtained' => 88,
            'grade' => 'A',
            'is_absent' => false,
            'remarks' => 'Published mark.',
        ]);

        $this->actingAs($fixture['admin'])
            ->get(route('admin.exams.results', $fixture['exam']))
            ->assertOk()
            ->assertSee('Results published');

        $this->actingAs($fixture['admin'])
            ->from(route('admin.exams.results', $fixture['exam']))
            ->post(route('admin.exams.results.save', $fixture['exam']), [
                'results' => [
                    $fixture['student']->id => ['marks' => 95, 'grade' => 'A+', 'remarks' => 'Changed after publish'],
                ],
            ])
            ->assertRedirect(route('admin.exams.results', $fixture['exam']))
            ->assertSessionHas('error', 'Published results are locked. Use the Exam Cell appeal/correction workflow for changes.');

        $this->assertDatabaseHas('exam_results', [
            'exam_id' => $fixture['exam']->id,
            'student_id' => $fixture['student']->id,
            'marks_obtained' => 88,
            'grade' => 'A',
            'remarks' => 'Published mark.',
        ]);
    }

    public function test_admin_cannot_edit_or_delete_published_exam_schedule(): void
    {
        $fixture = $this->fixture();
        $fixture['exam']->forceFill([
            'published_at' => now(),
            'published_by' => $fixture['admin']->id,
        ])->save();

        $this->actingAs($fixture['admin'])
            ->get(route('admin.exams.show', $fixture['exam']))
            ->assertOk()
            ->assertSee('Published')
            ->assertSee('Locked');

        $this->actingAs($fixture['admin'])
            ->from(route('admin.exams.edit', $fixture['exam']))
            ->put(route('admin.exams.update', $fixture['exam']), [
                'semester_id' => $fixture['exam']->semester_id,
                'subject_id' => $fixture['exam']->subject_id,
                'name' => 'Changed Published Exam',
                'type' => $fixture['exam']->type,
                'exam_date' => now()->addDays(10)->toDateString(),
                'total_marks' => 90,
                'passing_marks' => 35,
            ])
            ->assertRedirect(route('admin.exams.show', $fixture['exam']))
            ->assertSessionHas('error', 'Published exams cannot be edited because official result history is locked.');

        $this->assertDatabaseHas('exams', [
            'id' => $fixture['exam']->id,
            'name' => 'Admin Canonical Exam',
            'total_marks' => 100,
        ]);

        $this->actingAs($fixture['admin'])
            ->delete(route('admin.exams.destroy', $fixture['exam']))
            ->assertRedirect(route('admin.exams.index'))
            ->assertSessionHas('error', 'Published exams cannot be deleted because official result history is locked.');

        $this->assertDatabaseHas('exams', ['id' => $fixture['exam']->id]);
    }

    public function test_admin_exam_schedule_requires_active_subject_and_classroom(): void
    {
        $fixture = $this->fixture();
        $inactiveSubject = Subject::factory()->create(['is_active' => false]);
        $inactiveClassroom = Classroom::factory()->create(['is_active' => false]);

        $this->actingAs($fixture['admin'])
            ->from(route('admin.exams.create'))
            ->post(route('admin.exams.store'), [
                'semester_id' => $fixture['exam']->semester_id,
                'subject_id' => $inactiveSubject->id,
                'name' => 'Inactive Subject Exam',
                'type' => 'internal',
                'exam_date' => now()->addWeek()->toDateString(),
                'start_time' => '09:00',
                'end_time' => '10:00',
                'total_marks' => 100,
                'passing_marks' => 40,
                'classroom_id' => $fixture['entry']->classroom_id,
            ])
            ->assertRedirect(route('admin.exams.create'))
            ->assertSessionHasErrors('subject_id');

        $this->actingAs($fixture['admin'])
            ->from(route('admin.exams.create'))
            ->post(route('admin.exams.store'), [
                'semester_id' => $fixture['exam']->semester_id,
                'subject_id' => $fixture['exam']->subject_id,
                'name' => 'Inactive Classroom Exam',
                'type' => 'internal',
                'exam_date' => now()->addWeek()->toDateString(),
                'start_time' => '09:00',
                'end_time' => '10:00',
                'total_marks' => 100,
                'passing_marks' => 40,
                'classroom_id' => $inactiveClassroom->id,
            ])
            ->assertRedirect(route('admin.exams.create'))
            ->assertSessionHasErrors('classroom_id');

        $this->actingAs($fixture['admin'])
            ->from(route('admin.exams.edit', $fixture['exam']))
            ->put(route('admin.exams.update', $fixture['exam']), [
                'semester_id' => $fixture['exam']->semester_id,
                'subject_id' => $inactiveSubject->id,
                'name' => 'Move To Inactive Subject',
                'type' => $fixture['exam']->type,
                'exam_date' => now()->addWeek()->toDateString(),
                'total_marks' => 100,
                'passing_marks' => 40,
            ])
            ->assertRedirect(route('admin.exams.edit', $fixture['exam']))
            ->assertSessionHasErrors('subject_id');

        $this->assertDatabaseMissing('exams', ['name' => 'Inactive Subject Exam']);
        $this->assertDatabaseMissing('exams', ['name' => 'Inactive Classroom Exam']);
        $this->assertDatabaseHas('exams', [
            'id' => $fixture['exam']->id,
            'name' => 'Admin Canonical Exam',
            'subject_id' => $fixture['exam']->subject_id,
        ]);
    }

    public function test_admin_exam_schedule_syncs_program_from_selected_subject(): void
    {
        $fixture = $this->fixture();
        $otherProgram = Program::factory()->create(['is_active' => true]);
        $otherSubject = Subject::factory()->create([
            'program_id' => $otherProgram->id,
            'is_active' => true,
            'name' => 'Other Program Exam Subject',
        ]);

        $this->actingAs($fixture['admin'])
            ->post(route('admin.exams.store'), [
                'semester_id' => $fixture['exam']->semester_id,
                'subject_id' => $fixture['exam']->subject_id,
                'name' => 'Program Synced Exam',
                'type' => 'internal',
                'exam_date' => now()->addWeek()->toDateString(),
                'start_time' => '09:00',
                'end_time' => '10:00',
                'total_marks' => 100,
                'passing_marks' => 40,
                'classroom_id' => $fixture['entry']->classroom_id,
            ])
            ->assertRedirect(route('admin.exams.index'));

        $this->assertDatabaseHas('exams', [
            'name' => 'Program Synced Exam',
            'subject_id' => $fixture['exam']->subject_id,
            'program_id' => $fixture['exam']->program_id,
        ]);

        $this->actingAs($fixture['admin'])
            ->put(route('admin.exams.update', $fixture['exam']), [
                'semester_id' => $fixture['exam']->semester_id,
                'subject_id' => $otherSubject->id,
                'name' => 'Program Resynced Exam',
                'type' => $fixture['exam']->type,
                'exam_date' => now()->addWeek()->toDateString(),
                'total_marks' => 100,
                'passing_marks' => 40,
            ])
            ->assertRedirect(route('admin.exams.show', $fixture['exam']));

        $fixture['exam']->refresh();
        $this->assertSame($otherSubject->id, $fixture['exam']->subject_id);
        $this->assertSame($otherProgram->id, $fixture['exam']->program_id);
    }

    public function test_admin_exam_schedule_date_must_fall_inside_selected_semester(): void
    {
        $fixture = $this->fixture();
        $semester = $fixture['exam']->semester;
        $outsideDate = $semester->end_date->copy()->addDay()->toDateString();
        $originalExamDate = $fixture['exam']->exam_date->toDateTimeString();

        $this->actingAs($fixture['admin'])
            ->from(route('admin.exams.create'))
            ->post(route('admin.exams.store'), [
                'semester_id' => $semester->id,
                'subject_id' => $fixture['exam']->subject_id,
                'name' => 'Outside Semester Exam',
                'type' => 'internal',
                'exam_date' => $outsideDate,
                'start_time' => '09:00',
                'end_time' => '10:00',
                'total_marks' => 100,
                'passing_marks' => 40,
                'classroom_id' => $fixture['entry']->classroom_id,
            ])
            ->assertRedirect(route('admin.exams.create'))
            ->assertSessionHasErrors('exam_date');

        $this->actingAs($fixture['admin'])
            ->from(route('admin.exams.edit', $fixture['exam']))
            ->put(route('admin.exams.update', $fixture['exam']), [
                'semester_id' => $semester->id,
                'subject_id' => $fixture['exam']->subject_id,
                'name' => 'Outside Semester Update',
                'type' => $fixture['exam']->type,
                'exam_date' => $outsideDate,
                'total_marks' => 100,
                'passing_marks' => 40,
            ])
            ->assertRedirect(route('admin.exams.edit', $fixture['exam']))
            ->assertSessionHasErrors('exam_date');

        $this->assertDatabaseMissing('exams', ['name' => 'Outside Semester Exam']);
        $fixture['exam']->refresh();
        $this->assertSame('Admin Canonical Exam', $fixture['exam']->name);
        $this->assertSame($originalExamDate, $fixture['exam']->exam_date->toDateTimeString());
    }

    public function test_admin_cannot_rewrite_or_delete_exam_with_draft_result_or_registration_history(): void
    {
        $fixture = $this->fixture();
        $alternateSubject = Subject::factory()->create([
            'program_id' => $fixture['exam']->program_id,
            'name' => 'Alternate Exam Subject',
        ]);

        ExamResult::factory()->create([
            'exam_id' => $fixture['exam']->id,
            'student_id' => $fixture['student']->id,
            'marks_obtained' => 72,
            'is_absent' => false,
        ]);

        ExamRegistration::create([
            'student_id' => $fixture['student']->id,
            'exam_id' => $fixture['exam']->id,
            'status' => 'approved',
            'attendance_eligible' => true,
            'fee_cleared' => true,
            'approved_by' => $fixture['admin']->id,
        ]);

        $this->actingAs($fixture['admin'])
            ->from(route('admin.exams.edit', $fixture['exam']))
            ->put(route('admin.exams.update', $fixture['exam']), [
                'semester_id' => $fixture['exam']->semester_id,
                'subject_id' => $alternateSubject->id,
                'name' => 'Changed Draft Exam Contract',
                'type' => $fixture['exam']->type,
                'exam_date' => now()->addDays(10)->toDateString(),
                'total_marks' => 90,
                'passing_marks' => 35,
            ])
            ->assertRedirect(route('admin.exams.show', $fixture['exam']))
            ->assertSessionHas('error', 'Exams with result or registration history cannot have program, subject, semester, type, or marks-scale fields changed.');

        $fixture['exam']->refresh();
        $this->assertNotSame($alternateSubject->id, $fixture['exam']->subject_id);
        $this->assertSame('Admin Canonical Exam', $fixture['exam']->name);
        $this->assertSame(100, (int) $fixture['exam']->total_marks);

        $this->actingAs($fixture['admin'])
            ->delete(route('admin.exams.destroy', $fixture['exam']))
            ->assertRedirect(route('admin.exams.index'))
            ->assertSessionHas('error', 'Exams with result or registration history cannot be deleted. Archive or cancel through an audited exam workflow instead.');

        $this->assertDatabaseHas('exams', ['id' => $fixture['exam']->id]);
        $this->assertDatabaseHas('exam_results', ['exam_id' => $fixture['exam']->id, 'student_id' => $fixture['student']->id]);
        $this->assertDatabaseHas('exam_registrations', ['exam_id' => $fixture['exam']->id, 'student_id' => $fixture['student']->id]);
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
            ->from(route('admin.attendance.index'))
            ->post(route('admin.attendance.store'), [
                'timetable_entry_id' => $fixture['entry']->id,
                'date' => now()->toDateString(),
                'attendance' => [
                    $fixture['student']->id => 'holiday',
                ],
            ])
            ->assertRedirect(route('admin.attendance.index'))
            ->assertSessionHasErrors('attendance.' . $fixture['student']->id);

        $this->assertDatabaseMissing('attendances', [
            'student_id' => $fixture['student']->id,
            'timetable_entry_id' => $fixture['entry']->id,
            'status' => 'holiday',
        ]);

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

    public function test_admin_attendance_requires_active_entry_and_scheduled_date(): void
    {
        $fixture = $this->fixture();
        $futureDate = now()->addDay()->toDateString();
        $mismatchedDate = now()->subDay();

        if ((int) $mismatchedDate->dayOfWeekIso === (int) $fixture['entry']->day_of_week) {
            $mismatchedDate = now()->subDays(2);
        }

        $this->actingAs($fixture['admin'])
            ->from(route('admin.attendance.index'))
            ->post(route('admin.attendance.store'), [
                'timetable_entry_id' => $fixture['entry']->id,
                'date' => $futureDate,
                'attendance' => [$fixture['student']->id => 'present'],
            ])
            ->assertRedirect(route('admin.attendance.index'))
            ->assertSessionHasErrors('date');

        $this->actingAs($fixture['admin'])
            ->from(route('admin.attendance.index'))
            ->post(route('admin.attendance.store'), [
                'timetable_entry_id' => $fixture['entry']->id,
                'date' => $mismatchedDate->toDateString(),
                'attendance' => [$fixture['student']->id => 'present'],
            ])
            ->assertRedirect(route('admin.attendance.index'))
            ->assertSessionHasErrors('date');

        $fixture['entry']->update(['is_active' => false]);

        $this->actingAs($fixture['admin'])
            ->post(route('admin.attendance.store'), [
                'timetable_entry_id' => $fixture['entry']->id,
                'date' => now()->toDateString(),
                'attendance' => [$fixture['student']->id => 'present'],
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('attendances', [
            'student_id' => $fixture['student']->id,
            'timetable_entry_id' => $fixture['entry']->id,
        ]);
    }

    public function test_admin_attendance_requires_published_timetable_entry_and_version(): void
    {
        $fixture = $this->fixture();
        $fixture['entry']->update(['status' => 'draft']);

        $this->actingAs($fixture['admin'])
            ->post(route('admin.attendance.store'), [
                'timetable_entry_id' => $fixture['entry']->id,
                'date' => now()->toDateString(),
                'attendance' => [$fixture['student']->id => 'present'],
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('attendances', [
            'student_id' => $fixture['student']->id,
            'timetable_entry_id' => $fixture['entry']->id,
        ]);

        $draftVersion = TimetableVersion::create([
            'program_id' => $fixture['entry']->program_id,
            'term_id' => $fixture['entry']->term_id,
            'batch_id' => $fixture['entry']->batch_id,
            'version_number' => 1,
            'status' => 'draft',
            'created_by' => $fixture['admin']->id,
        ]);
        $fixture['entry']->update([
            'status' => 'published',
            'timetable_version_id' => $draftVersion->id,
        ]);

        $this->actingAs($fixture['admin'])
            ->post(route('admin.attendance.store'), [
                'timetable_entry_id' => $fixture['entry']->id,
                'date' => now()->toDateString(),
                'attendance' => [$fixture['student']->id => 'present'],
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('attendances', [
            'student_id' => $fixture['student']->id,
            'timetable_entry_id' => $fixture['entry']->id,
        ]);
    }

    public function test_admin_attendance_reports_exclude_draft_timetable_history(): void
    {
        $fixture = $this->fixture();
        Attendance::create([
            'student_id' => $fixture['student']->id,
            'timetable_entry_id' => $fixture['entry']->id,
            'date' => now()->toDateString(),
            'status' => 'present',
        ]);

        $draftSubject = Subject::factory()->create([
            'program_id' => $fixture['entry']->program_id,
            'term_number' => 1,
            'name' => 'Draft Attendance Report Subject',
        ]);
        $draftEntry = TimetableEntry::factory()->create([
            'program_id' => $fixture['entry']->program_id,
            'batch_id' => $fixture['entry']->batch_id,
            'course_id' => $fixture['entry']->course_id,
            'term_id' => $fixture['entry']->term_id,
            'semester_id' => $fixture['entry']->semester_id,
            'subject_id' => $draftSubject->id,
            'teacher_id' => Teacher::factory()->create()->id,
            'classroom_id' => Classroom::factory()->create()->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => now()->dayOfWeekIso,
            'is_active' => true,
            'status' => 'draft',
        ]);
        Attendance::create([
            'student_id' => $fixture['student']->id,
            'timetable_entry_id' => $draftEntry->id,
            'date' => now()->toDateString(),
            'status' => 'absent',
        ]);

        $draftVersion = TimetableVersion::create([
            'program_id' => $fixture['entry']->program_id,
            'term_id' => $fixture['entry']->term_id,
            'batch_id' => $fixture['entry']->batch_id,
            'version_number' => 2,
            'status' => 'draft',
            'created_by' => $fixture['admin']->id,
        ]);
        $draftVersionSubject = Subject::factory()->create([
            'program_id' => $fixture['entry']->program_id,
            'term_number' => 1,
            'name' => 'Draft Version Attendance Subject',
        ]);
        $draftVersionEntry = TimetableEntry::factory()->create([
            'program_id' => $fixture['entry']->program_id,
            'batch_id' => $fixture['entry']->batch_id,
            'course_id' => $fixture['entry']->course_id,
            'term_id' => $fixture['entry']->term_id,
            'semester_id' => $fixture['entry']->semester_id,
            'subject_id' => $draftVersionSubject->id,
            'teacher_id' => Teacher::factory()->create()->id,
            'classroom_id' => Classroom::factory()->create()->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => now()->dayOfWeekIso,
            'is_active' => true,
            'status' => 'published',
            'timetable_version_id' => $draftVersion->id,
        ]);
        Attendance::create([
            'student_id' => $fixture['student']->id,
            'timetable_entry_id' => $draftVersionEntry->id,
            'date' => now()->toDateString(),
            'status' => 'absent',
        ]);

        $this->actingAs($fixture['admin'])
            ->get(route('admin.attendance.report', [
                'student_id' => $fixture['student']->id,
                'semester_id' => $fixture['entry']->semester_id,
            ]))
            ->assertOk()
            ->assertSee('Admin Canonical Subject')
            ->assertSee('100%')
            ->assertDontSee('Draft Attendance Report Subject')
            ->assertDontSee('Draft Version Attendance Subject');

        $export = $this->actingAs($fixture['admin'])
            ->get(route('admin.attendance.export', [
                'semester_id' => $fixture['entry']->semester_id,
            ]));
        $export->assertOk();
        $csv = $export->streamedContent();
        $this->assertStringContainsString('Admin Canonical Subject', $csv);
        $this->assertStringNotContainsString('Draft Attendance Report Subject', $csv);
        $this->assertStringNotContainsString('Draft Version Attendance Subject', $csv);
    }

    public function test_official_admin_timetable_grid_excludes_draft_entries_and_draft_versions(): void
    {
        $fixture = $this->fixture();
        $fixture['entry']->update([
            'status' => 'published',
            'day_of_week' => 1,
        ]);

        $draftSubject = Subject::factory()->create([
            'program_id' => $fixture['entry']->program_id,
            'term_number' => 1,
            'name' => 'Draft Timetable PDF Subject',
        ]);
        $draftSlot = TimetableSlot::factory()->create(['sort_order' => 50]);
        TimetableEntry::factory()->create([
            'program_id' => $fixture['entry']->program_id,
            'batch_id' => $fixture['entry']->batch_id,
            'course_id' => $fixture['entry']->course_id,
            'term_id' => $fixture['entry']->term_id,
            'semester_id' => $fixture['entry']->semester_id,
            'subject_id' => $draftSubject->id,
            'teacher_id' => Teacher::factory()->create()->id,
            'classroom_id' => Classroom::factory()->create()->id,
            'timetable_slot_id' => $draftSlot->id,
            'day_of_week' => 1,
            'is_active' => true,
            'status' => 'draft',
        ]);

        $draftVersion = TimetableVersion::create([
            'program_id' => $fixture['entry']->program_id,
            'term_id' => $fixture['entry']->term_id,
            'batch_id' => $fixture['entry']->batch_id,
            'version_number' => 3,
            'status' => 'draft',
            'created_by' => $fixture['admin']->id,
        ]);
        $draftVersionSubject = Subject::factory()->create([
            'program_id' => $fixture['entry']->program_id,
            'term_number' => 1,
            'name' => 'Draft Version Timetable PDF Subject',
        ]);
        $draftVersionSlot = TimetableSlot::factory()->create(['sort_order' => 51]);
        TimetableEntry::factory()->create([
            'program_id' => $fixture['entry']->program_id,
            'batch_id' => $fixture['entry']->batch_id,
            'course_id' => $fixture['entry']->course_id,
            'term_id' => $fixture['entry']->term_id,
            'semester_id' => $fixture['entry']->semester_id,
            'subject_id' => $draftVersionSubject->id,
            'teacher_id' => Teacher::factory()->create()->id,
            'classroom_id' => Classroom::factory()->create()->id,
            'timetable_slot_id' => $draftVersionSlot->id,
            'day_of_week' => 1,
            'is_active' => true,
            'status' => 'published',
            'timetable_version_id' => $draftVersion->id,
        ]);

        $publishedVersion = TimetableVersion::create([
            'program_id' => $fixture['entry']->program_id,
            'term_id' => $fixture['entry']->term_id,
            'batch_id' => $fixture['entry']->batch_id,
            'version_number' => 4,
            'status' => 'published',
            'created_by' => $fixture['admin']->id,
            'published_by' => $fixture['admin']->id,
            'published_at' => now(),
        ]);
        $publishedVersionSubject = Subject::factory()->create([
            'program_id' => $fixture['entry']->program_id,
            'term_number' => 1,
            'name' => 'Published Version Timetable PDF Subject',
        ]);
        $publishedVersionSlot = TimetableSlot::factory()->create(['sort_order' => 52]);
        TimetableEntry::factory()->create([
            'program_id' => $fixture['entry']->program_id,
            'batch_id' => $fixture['entry']->batch_id,
            'course_id' => $fixture['entry']->course_id,
            'term_id' => $fixture['entry']->term_id,
            'semester_id' => $fixture['entry']->semester_id,
            'subject_id' => $publishedVersionSubject->id,
            'teacher_id' => Teacher::factory()->create()->id,
            'classroom_id' => Classroom::factory()->create()->id,
            'timetable_slot_id' => $publishedVersionSlot->id,
            'day_of_week' => 1,
            'is_active' => true,
            'status' => 'published',
            'timetable_version_id' => $publishedVersion->id,
        ]);

        $grid = app(\App\Services\TimetableService::class)
            ->buildWeeklyGrid($fixture['entry']->semester_id, officialOnly: true);
        $subjects = collect($grid)
            ->flatMap(fn (array $day) => collect($day))
            ->filter()
            ->map(fn (TimetableEntry $entry) => $entry->subject?->name)
            ->values()
            ->all();

        $this->assertContains('Admin Canonical Subject', $subjects);
        $this->assertContains('Published Version Timetable PDF Subject', $subjects);
        $this->assertNotContains('Draft Timetable PDF Subject', $subjects);
        $this->assertNotContains('Draft Version Timetable PDF Subject', $subjects);

        $this->actingAs($fixture['admin'])
            ->get(route('admin.reports.timetable', $fixture['entry']->semester))
            ->assertOk();
    }
}
