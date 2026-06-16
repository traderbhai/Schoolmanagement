<?php

namespace Tests\Feature;

use App\Models\{Batch, Classroom, Course, Program, Semester, Student, StudentSubjectEnrollment, Subject, Teacher, Term, TimetableEntry, TimetableSlot, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentTimetableWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_timetable_uses_canonical_subject_enrollments_and_numeric_weekdays(): void
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'Term 1',
        ]);
        $semester = Semester::factory()->create(['number' => 1, 'name' => 'Term 1']);
        $course = Course::factory()->create();
        $user = User::factory()->create();
        $user->assignRole('student');
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'course_id' => $course->id,
            'current_term_id' => $term->id,
            'status' => 'active',
        ]);
        $subject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Canonical Timetable Subject',
            'code' => 'CTS101',
        ]);
        $hiddenSubject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Unenrolled Timetable Subject',
            'code' => 'UTS101',
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);
        $teacher = Teacher::factory()->create();
        $slot = TimetableSlot::factory()->create(['name' => 'Morning Slot', 'sort_order' => 1]);
        $room = Classroom::factory()->create(['name' => 'Room T1']);

        TimetableEntry::create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'timetable_slot_id' => $slot->id,
            'day_of_week' => 1,
            'is_active' => true,
        ]);
        TimetableEntry::create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $hiddenSubject->id,
            'teacher_id' => Teacher::factory()->create()->id,
            'classroom_id' => Classroom::factory()->create()->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => 2,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('student.timetable'))
            ->assertOk()
            ->assertSee('Monday')
            ->assertSee('Canonical Timetable Subject')
            ->assertSee('Room T1')
            ->assertDontSee('Unenrolled Timetable Subject');
    }
}
