<?php

namespace Tests\Feature;

use App\Models\{Batch, Classroom, Course, Semester, Student, StudentSubjectEnrollment, Teacher, Term, TimetableEntry, TimetableSlot, User, Program, Subject, Exam, ExamResult};
use App\Services\GradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExamResultTest extends TestCase
{
    use RefreshDatabase;

    public function test_grade_service_O_grade(): void
    {
        $gradeService = new GradeService();
        $grade = $gradeService->getGrade(92);
        $this->assertEquals('O', $grade['letter']);
    }

    public function test_grade_service_F_grade(): void
    {
        $gradeService = new GradeService();
        $grade = $gradeService->getGrade(30);
        $this->assertEquals('F', $grade['letter']);
    }

    public function test_grade_service_B_grade(): void
    {
        $gradeService = new GradeService();
        $grade = $gradeService->getGrade(57);
        $this->assertContains($grade['letter'], ['B', 'B+', 'C']);
    }

    public function test_teacher_can_view_exam_list(): void
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        $tUser   = User::factory()->create();
        $tUser->assignRole('teacher');
        Teacher::factory()->create(['user_id' => $tUser->id]);

        $response = $this->actingAs($tUser)->get(route('teacher.exams.index'));
        $response->assertStatus(200);
    }

    public function test_teacher_exam_list_explains_marks_entry_workflow_when_empty(): void
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $tUser = User::factory()->create();
        $tUser->assignRole('teacher');
        Teacher::factory()->create(['user_id' => $tUser->id]);

        $this->actingAs($tUser)
            ->get(route('teacher.exams.index'))
            ->assertOk()
            ->assertSee('Marks entry sequence')
            ->assertSee('No published exam-entry work is available yet')
            ->assertSee('verify teacher assignment, timetable publication, and exam setup')
            ->assertDontSee('No Exams Found')
            ->assertDontSee('N/A')
            ->assertDontSee('&mdash;', false)
            ->assertDontSee('&ndash;', false);
    }

    public function test_teacher_result_entry_page_explains_roster_and_publication_boundary(): void
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $program = Program::factory()->create(['is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $course = Course::factory()->create(['name' => 'Marks UX Course']);
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
            'term_number' => 1,
            'name' => 'Marks UX Subject',
        ]);
        $teacher = Teacher::factory()->create();
        $teacher->user->assignRole('teacher');

        TimetableEntry::factory()->create([
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
            'status' => 'published',
        ]);

        $studentUser = User::factory()->create(['name' => 'Marks UX Student']);
        $studentUser->assignRole('student');
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'course_id' => $course->id,
            'current_term_id' => $term->id,
            'enrollment_number' => 'MRK-UX-001',
            'roll_number' => null,
            'status' => 'active',
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);

        $exam = Exam::factory()->create([
            'program_id' => $program->id,
            'semester_id' => $semester->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'name' => 'Marks UX Exam',
            'type' => 'internal',
            'exam_date' => now()->subDay()->toDateString(),
            'total_marks' => 50,
            'passing_marks' => 20,
        ]);

        $this->actingAs($teacher->user)
            ->get(route('teacher.exams.results', $exam))
            ->assertOk()
            ->assertSee('Result entry sequence')
            ->assertSee('Marks UX Student')
            ->assertSee('MRK-UX-001')
            ->assertSee('Roll pending')
            ->assertSee('Optional note for Exam Cell')
            ->assertSeeText('Once Exam Cell publishes the result')
            ->assertDontSeeText('N/A')
            ->assertDontSee('&mdash;', false)
            ->assertDontSee('&ndash;', false);
    }

    public function test_student_can_view_results(): void
    {
        $program = Program::factory()->create(['is_active' => true]);
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $sUser   = User::factory()->create();
        $sUser->assignRole('student');
        Student::factory()->create(['user_id' => $sUser->id, 'program_id' => $program->id]);

        $this->actingAs($sUser)->get(route('student.results'))->assertStatus(200);
    }
}
