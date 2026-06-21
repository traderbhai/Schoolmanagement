<?php

namespace Tests\Feature;

use App\Models\{Batch, Classroom, Course, Semester, Student, StudentSubjectEnrollment, Teacher, Term, TimetableEntry, TimetableSlot, User, Program, Subject};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_view_mark_attendance_page(): void
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $tUser   = User::factory()->create();
        $tUser->assignRole('teacher');
        Teacher::factory()->create(['user_id' => $tUser->id]);

        $response = $this->actingAs($tUser)->get(route('teacher.attendance.mark'));
        $response->assertStatus(200);
    }

    public function test_teacher_attendance_page_explains_workflow_and_missing_schedule_data(): void
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $program = Program::factory()->create(['is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $course = Course::factory()->create(['name' => 'Attendance UX Course']);
        $semester = Semester::factory()->create(['name' => 'Term 1', 'number' => 1, 'is_current' => true]);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'Term 1',
            'is_current' => true,
        ]);
        $subject = Subject::factory()->create([
            'program_id' => $program->id,
            'name' => 'Attendance UX Subject',
            'term_number' => 1,
        ]);
        $teacher = Teacher::factory()->create();
        $teacher->user->assignRole('teacher');

        $studentUser = User::factory()->create(['name' => 'Attendance UX Student']);
        $studentUser->assignRole('student');
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'course_id' => $course->id,
            'current_term_id' => $term->id,
            'enrollment_number' => 'ATT-UX-001',
            'roll_number' => 'UX-01',
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
            'classroom_id' => Classroom::factory()->create(['room_number' => 'UX-201'])->id,
            'timetable_slot_id' => TimetableSlot::factory()->create(['name' => 'UX Morning'])->id,
            'day_of_week' => now()->dayOfWeekIso,
            'is_active' => true,
            'status' => 'published',
        ]);

        $response = $this->actingAs($teacher->user)->get(route('teacher.attendance.mark', [
            'date' => now()->toDateString(),
            'entry_id' => $entry->id,
        ]));

        $response
            ->assertOk()
            ->assertSee('Attendance marking sequence')
            ->assertSee('Attendance UX Student')
            ->assertSee('UX-201')
            ->assertSee('UX Morning')
            ->assertSee('ATT-UX-001')
            ->assertSee('UX-01')
            ->assertDontSee('N/A')
            ->assertDontSee('&mdash;', false)
            ->assertDontSee('&ndash;', false);
    }

    public function test_student_can_view_attendance(): void
    {
        $program = Program::factory()->create(['is_active' => true]);
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $sUser   = User::factory()->create();
        $sUser->assignRole('student');
        Student::factory()->create(['user_id' => $sUser->id, 'program_id' => $program->id]);

        $this->actingAs($sUser)->get(route('student.attendance'))->assertStatus(200);
    }

    public function test_student_with_low_attendance_sees_dashboard(): void
    {
        $program = Program::factory()->create(['is_active' => true]);
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $sUser   = User::factory()->create();
        $sUser->assignRole('student');
        Student::factory()->create(['user_id' => $sUser->id, 'program_id' => $program->id]);

        // Even with no attendance records, dashboard should load
        $this->actingAs($sUser)->get(route('student.dashboard'))->assertStatus(200);
    }
}
