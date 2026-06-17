<?php

namespace Tests\Feature;

use App\Models\{Attendance, AttendanceCondonation, Batch, Classroom, Course, Enrollment, Program, RoleProgramAssignment, Semester, Student, StudentSubjectEnrollment, Subject, Teacher, Term, TimetableEntry, TimetableSlot, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentTeacherAttendanceCanonicalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

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

        $teacher = Teacher::factory()->create();
        $teacher->user->assignRole('teacher');
        $subject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Canonical Attendance Subject',
        ]);
        $studentUser = User::factory()->create(['name' => 'Canonical Attendance Student']);
        $studentUser->assignRole('student');
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'course_id' => $course->id,
            'current_term_id' => $term->id,
            'roll_number' => 'CAN001',
            'status' => 'active',
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);

        $entry = TimetableEntry::factory()->create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'semester_id' => $semester->id,
            'term_id' => $term->id,
            'classroom_id' => Classroom::factory()->create()->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => now()->dayOfWeekIso,
            'is_active' => true,
        ]);

        return compact('teacher', 'student', 'subject', 'semester', 'term', 'program', 'batch', 'course', 'entry');
    }

    public function test_teacher_can_mark_attendance_for_canonical_course_basket_student(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['teacher']->user)
            ->get(route('teacher.attendance.mark', [
                'date' => now()->toDateString(),
                'entry_id' => $fixture['entry']->id,
            ]))
            ->assertOk()
            ->assertSee('Canonical Attendance Student')
            ->assertSee('CAN001');

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.attendance.store'), [
                'timetable_entry_id' => $fixture['entry']->id,
                'date' => now()->toDateString(),
                'attendance' => [$fixture['student']->id => 'present'],
            ])
            ->assertRedirect(route('teacher.attendance.mark'));

        $this->assertDatabaseHas('attendances', [
            'student_id' => $fixture['student']->id,
            'timetable_entry_id' => $fixture['entry']->id,
            'status' => 'present',
        ]);
    }

    public function test_student_attendance_summary_uses_canonical_term_timetable_entries(): void
    {
        $fixture = $this->fixture();
        Attendance::create([
            'student_id' => $fixture['student']->id,
            'timetable_entry_id' => $fixture['entry']->id,
            'date' => now()->toDateString(),
            'status' => 'present',
            'marked_by' => $fixture['teacher']->user_id,
        ]);

        $this->actingAs($fixture['student']->user)
            ->get(route('student.attendance', ['semester_id' => $fixture['semester']->id]))
            ->assertOk()
            ->assertSee('Canonical Attendance Subject')
            ->assertSee('100%');
    }

    public function test_student_condonation_requires_enrolled_low_attendance_subject(): void
    {
        $fixture = $this->fixture();
        $highSubject = Subject::factory()->create([
            'program_id' => $fixture['program']->id,
            'term_number' => 1,
            'name' => 'High Attendance Subject',
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $fixture['student']->id,
            'subject_id' => $highSubject->id,
            'term_id' => $fixture['term']->id,
            'status' => 'active',
        ]);
        $highEntry = TimetableEntry::factory()->create([
            'teacher_id' => $fixture['teacher']->id,
            'subject_id' => $highSubject->id,
            'course_id' => $fixture['course']->id,
            'program_id' => $fixture['program']->id,
            'batch_id' => $fixture['batch']->id,
            'semester_id' => $fixture['semester']->id,
            'term_id' => $fixture['term']->id,
            'classroom_id' => Classroom::factory()->create()->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => now()->dayOfWeekIso,
            'is_active' => true,
        ]);
        $unenrolledSubject = Subject::factory()->create([
            'program_id' => $fixture['program']->id,
            'term_number' => 1,
            'name' => 'Unenrolled Condonation Subject',
        ]);

        foreach (range(1, 4) as $day) {
            Attendance::create([
                'student_id' => $fixture['student']->id,
                'timetable_entry_id' => $fixture['entry']->id,
                'date' => now()->subDays($day)->toDateString(),
                'status' => $day === 1 ? 'present' : 'absent',
                'marked_by' => $fixture['teacher']->user_id,
            ]);
            Attendance::create([
                'student_id' => $fixture['student']->id,
                'timetable_entry_id' => $highEntry->id,
                'date' => now()->subDays($day)->toDateString(),
                'status' => 'present',
                'marked_by' => $fixture['teacher']->user_id,
            ]);
        }

        $this->actingAs($fixture['student']->user)
            ->get(route('student.condonation.create'))
            ->assertOk()
            ->assertSee('Canonical Attendance Subject')
            ->assertDontSee('High Attendance Subject')
            ->assertDontSee('Unenrolled Condonation Subject');

        $this->actingAs($fixture['student']->user)
            ->post(route('student.condonation.store'), [
                'subject_id' => $unenrolledSubject->id,
                'reason' => 'Trying to request for an unenrolled subject.',
            ])
            ->assertSessionHasErrors('subject_id');

        $this->actingAs($fixture['student']->user)
            ->post(route('student.condonation.store'), [
                'subject_id' => $highSubject->id,
                'reason' => 'Trying to request despite eligible attendance.',
            ])
            ->assertSessionHasErrors('subject_id');

        $this->actingAs($fixture['student']->user)
            ->post(route('student.condonation.store'), [
                'subject_id' => $fixture['subject']->id,
                'reason' => 'Medical absence during the week.',
            ])
            ->assertRedirect(route('student.condonation.index'));

        $this->assertDatabaseHas('attendance_condonations', [
            'student_id' => $fixture['student']->id,
            'subject_id' => $fixture['subject']->id,
            'term_id' => $fixture['term']->id,
            'sessions_requested' => 3,
            'status' => 'pending',
        ]);

        $this->actingAs($fixture['student']->user)
            ->post(route('student.condonation.store'), [
                'subject_id' => $fixture['subject']->id,
                'reason' => 'Duplicate open request.',
            ])
            ->assertSessionHasErrors('subject_id');

        $this->assertSame(1, AttendanceCondonation::where('student_id', $fixture['student']->id)->count());
    }

    public function test_student_condonation_supports_legacy_active_enrollment(): void
    {
        $fixture = $this->fixture();
        $legacySubject = Subject::factory()->create([
            'program_id' => $fixture['program']->id,
            'term_number' => 1,
            'name' => 'Legacy Low Attendance',
        ]);
        Enrollment::create([
            'student_id' => $fixture['student']->id,
            'semester_id' => $fixture['semester']->id,
            'term_id' => $fixture['term']->id,
            'subject_id' => $legacySubject->id,
            'status' => 'active',
        ]);
        $entry = TimetableEntry::factory()->create([
            'teacher_id' => $fixture['teacher']->id,
            'subject_id' => $legacySubject->id,
            'course_id' => $fixture['course']->id,
            'program_id' => $fixture['program']->id,
            'batch_id' => $fixture['batch']->id,
            'semester_id' => $fixture['semester']->id,
            'term_id' => $fixture['term']->id,
            'classroom_id' => Classroom::factory()->create()->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => now()->dayOfWeekIso,
            'is_active' => true,
        ]);

        foreach (range(1, 3) as $day) {
            Attendance::create([
                'student_id' => $fixture['student']->id,
                'timetable_entry_id' => $entry->id,
                'date' => now()->subDays($day)->toDateString(),
                'status' => 'absent',
                'marked_by' => $fixture['teacher']->user_id,
            ]);
        }

        $this->actingAs($fixture['student']->user)
            ->get(route('student.condonation.create'))
            ->assertOk()
            ->assertSee('Legacy Low Attendance');

        $this->actingAs($fixture['student']->user)
            ->post(route('student.condonation.store'), [
                'subject_id' => $legacySubject->id,
                'reason' => 'Legacy enrollment low attendance request.',
            ])
            ->assertRedirect(route('student.condonation.index'));

        $this->assertDatabaseHas('attendance_condonations', [
            'student_id' => $fixture['student']->id,
            'subject_id' => $legacySubject->id,
            'term_id' => $fixture['term']->id,
        ]);
    }

    public function test_inactive_student_can_view_condonation_history_but_cannot_request_new_condonation(): void
    {
        $fixture = $this->fixture();
        $fixture['student']->update(['status' => 'inactive']);

        foreach (range(1, 4) as $day) {
            Attendance::create([
                'student_id' => $fixture['student']->id,
                'timetable_entry_id' => $fixture['entry']->id,
                'date' => now()->subDays($day)->toDateString(),
                'status' => $day === 1 ? 'present' : 'absent',
                'marked_by' => $fixture['teacher']->user_id,
            ]);
        }

        AttendanceCondonation::create([
            'student_id' => $fixture['student']->id,
            'subject_id' => $fixture['subject']->id,
            'term_id' => $fixture['term']->id,
            'reason' => 'Historical approved condonation.',
            'sessions_requested' => 2,
            'sessions_condoned' => 2,
            'status' => 'approved',
            'remarks' => 'Approved before archival.',
        ]);

        $this->actingAs($fixture['student']->user)
            ->get(route('student.condonation.index'))
            ->assertOk()
            ->assertSee('Canonical Attendance Subject')
            ->assertSee('Historical approved condonation')
            ->assertSee('New attendance condonation requests are locked')
            ->assertSee('Active students only')
            ->assertDontSee('New Request');

        $this->actingAs($fixture['student']->user)
            ->get(route('student.condonation.create'))
            ->assertRedirect(route('student.condonation.index'))
            ->assertSessionHas('error', 'Attendance condonation requests are available only for active students. Contact the academic office for archived records.');

        $this->actingAs($fixture['student']->user)
            ->post(route('student.condonation.store'), [
                'subject_id' => $fixture['subject']->id,
                'reason' => 'Inactive direct request should not be accepted.',
            ])
            ->assertRedirect(route('student.condonation.index'))
            ->assertSessionHas('error', 'Attendance condonation requests are available only for active students. Contact the academic office for archived records.');

        $this->assertSame(1, AttendanceCondonation::where('student_id', $fixture['student']->id)->count());
        $this->assertDatabaseMissing('attendance_condonations', [
            'student_id' => $fixture['student']->id,
            'subject_id' => $fixture['subject']->id,
            'reason' => 'Inactive direct request should not be accepted.',
            'status' => 'pending',
        ]);
    }

    public function test_program_chair_condonation_review_is_scoped_pending_only_and_capped_to_requested_sessions(): void
    {
        $fixture = $this->fixture();
        Role::firstOrCreate(['name' => 'program_chair', 'guard_name' => 'web']);
        $chair = User::factory()->create();
        $chair->assignRole('program_chair');

        RoleProgramAssignment::create([
            'user_id' => $chair->id,
            'role_name' => 'program_chair',
            'program_id' => $fixture['program']->id,
            'is_active' => true,
            'assigned_by' => $chair->id,
            'assigned_at' => now(),
        ]);

        $pending = AttendanceCondonation::create([
            'student_id' => $fixture['student']->id,
            'subject_id' => $fixture['subject']->id,
            'term_id' => $fixture['term']->id,
            'reason' => 'Medical absence.',
            'sessions_requested' => 2,
            'status' => 'pending',
        ]);

        $this->actingAs($chair)
            ->from(route('chair.students.condonations'))
            ->post(route('chair.students.condonations.approve', $pending), [
                'sessions_condoned' => 3,
                'remarks' => 'Too many sessions.',
            ])
            ->assertRedirect(route('chair.students.condonations'))
            ->assertSessionHasErrors('sessions_condoned');

        $this->assertDatabaseHas('attendance_condonations', [
            'id' => $pending->id,
            'status' => 'pending',
            'sessions_condoned' => 0,
        ]);

        $this->actingAs($chair)
            ->from(route('chair.students.condonations'))
            ->post(route('chair.students.condonations.approve', $pending), [
                'sessions_condoned' => 2,
                'remarks' => 'Approved within requested deficit.',
            ])
            ->assertRedirect(route('chair.students.condonations'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('attendance_condonations', [
            'id' => $pending->id,
            'status' => 'approved',
            'sessions_condoned' => 2,
            'approved_by' => $chair->id,
        ]);

        $this->actingAs($chair)
            ->from(route('chair.students.condonations'))
            ->post(route('chair.students.condonations.reject', $pending), [
                'remarks' => 'Changing history.',
            ])
            ->assertRedirect(route('chair.students.condonations'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('attendance_condonations', [
            'id' => $pending->id,
            'status' => 'approved',
        ]);

        $foreignStudent = Student::factory()->create();
        $foreignCondonation = AttendanceCondonation::create([
            'student_id' => $foreignStudent->id,
            'subject_id' => Subject::factory()->create()->id,
            'reason' => 'Out of scope.',
            'sessions_requested' => 1,
            'status' => 'pending',
        ]);

        $this->actingAs($chair)
            ->post(route('chair.students.condonations.approve', $foreignCondonation), [
                'sessions_condoned' => 1,
            ])
            ->assertForbidden();
    }
}
