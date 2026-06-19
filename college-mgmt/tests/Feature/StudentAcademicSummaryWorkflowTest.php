<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Course;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentSubjectEnrollment;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\TimetableVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentAcademicSummaryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_academic_summary_attendance_ignores_draft_timetable_history(): void
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('student');
        $course = Course::factory()->create();
        $program = Program::factory()->create();
        $term = Term::factory()->create(['program_id' => $program->id, 'term_number' => 1, 'name' => 'Term 1']);
        $semester = Semester::factory()->create(['number' => 1, 'name' => 'Term 1']);
        $subject = Subject::factory()->create(['program_id' => $program->id, 'term_number' => 1, 'name' => 'Summary Attendance Subject']);
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'current_term_id' => $term->id,
            'status' => 'active',
        ]);

        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);

        $publishedEntry = TimetableEntry::factory()->create([
            'program_id' => $program->id,
            'course_id' => $course->id,
            'term_id' => $term->id,
            'semester_id' => $semester->id,
            'subject_id' => $subject->id,
            'teacher_id' => Teacher::factory()->create()->id,
            'is_active' => true,
            'status' => 'published',
        ]);
        $draftEntry = TimetableEntry::factory()->create([
            'program_id' => $program->id,
            'course_id' => $course->id,
            'term_id' => $term->id,
            'semester_id' => $semester->id,
            'subject_id' => $subject->id,
            'teacher_id' => Teacher::factory()->create()->id,
            'is_active' => true,
            'status' => 'draft',
        ]);
        $draftVersion = TimetableVersion::create([
            'program_id' => $program->id,
            'course_id' => $course->id,
            'term_id' => $term->id,
            'version_number' => 2,
            'status' => 'draft',
            'created_by' => User::factory()->create()->id,
        ]);
        $draftVersionEntry = TimetableEntry::factory()->create([
            'program_id' => $program->id,
            'course_id' => $course->id,
            'term_id' => $term->id,
            'semester_id' => $semester->id,
            'subject_id' => $subject->id,
            'teacher_id' => Teacher::factory()->create()->id,
            'is_active' => true,
            'status' => 'published',
            'timetable_version_id' => $draftVersion->id,
        ]);

        foreach (range(1, 3) as $day) {
            Attendance::create([
                'student_id' => $student->id,
                'timetable_entry_id' => $publishedEntry->id,
                'date' => now()->subDays($day)->toDateString(),
                'status' => 'present',
            ]);
        }

        foreach ([$draftEntry, $draftVersionEntry] as $entry) {
            Attendance::create([
                'student_id' => $student->id,
                'timetable_entry_id' => $entry->id,
                'date' => now()->subDays(10)->toDateString(),
                'status' => 'absent',
            ]);
        }

        $this->actingAs($user)
            ->get(route('student.summary.index'))
            ->assertOk()
            ->assertSee('100%')
            ->assertDontSee('60%')
            ->assertSee('Summary Attendance Subject');
    }
}
