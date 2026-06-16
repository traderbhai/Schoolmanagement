<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Department;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentSubjectEnrollment;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeacherStudentListTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_student_list_shows_database_backed_attendance(): void
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $teacherUser = User::factory()->create(['name' => 'Faculty One']);
        $teacherUser->assignRole('teacher');

        $department = Department::factory()->create();
        $course = Course::factory()->create(['department_id' => $department->id]);
        $program = Program::factory()->create(['department_id' => $department->id]);
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $semester = Semester::factory()->create(['number' => 1, 'is_current' => true]);
        $term = Term::create([
            'batch_id' => $batch->id,
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Term 1',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->addMonths(4),
            'is_current' => true,
            'sort_order' => 1,
        ]);

        $teacher = Teacher::factory()->create([
            'user_id' => $teacherUser->id,
            'department_id' => $department->id,
        ]);

        $subject = Subject::factory()->create([
            'department_id' => $department->id,
            'program_id' => $program->id,
        ]);

        $student = Student::factory()->create([
            'department_id' => $department->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'current_semester' => 1,
            'current_term_id' => $term->id,
            'roll_number' => 'ROLL001',
        ]);
        $student->user->forceFill(['name' => 'Aarav Searchable'])->save();

        $otherStudent = Student::factory()->create([
            'department_id' => $department->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'current_semester' => 1,
            'current_term_id' => $term->id,
            'roll_number' => 'ROLL002',
        ]);
        $otherStudent->user->forceFill(['name' => 'Bhavna Filtered'])->save();

        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $otherStudent->id,
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
            'semester_id' => $semester->id,
            'term_id' => $term->id,
            'is_active' => true,
        ]);

        Attendance::create([
            'student_id' => $student->id,
            'timetable_entry_id' => $entry->id,
            'date' => now()->subDay()->toDateString(),
            'status' => 'present',
            'marked_by' => $teacherUser->id,
        ]);

        Attendance::create([
            'student_id' => $student->id,
            'timetable_entry_id' => $entry->id,
            'date' => now()->toDateString(),
            'status' => 'absent',
            'marked_by' => $teacherUser->id,
        ]);

        $this->actingAs($teacherUser)
            ->get(route('teacher.students.index'))
            ->assertOk()
            ->assertSee($student->user->name)
            ->assertSee('50%')
            ->assertSee('1/2')
            ->assertSee(route('teacher.students.index'), false)
            ->assertSee(route('notifications.index'), false);

        $this->actingAs($teacherUser)
            ->get(route('teacher.students.index', ['search' => 'Aarav']))
            ->assertOk()
            ->assertSee('Aarav Searchable')
            ->assertDontSee('Bhavna Filtered');
    }
}
