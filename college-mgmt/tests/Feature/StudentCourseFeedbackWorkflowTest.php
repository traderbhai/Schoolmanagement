<?php

namespace Tests\Feature;

use App\Models\CourseFeedback;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
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

class StudentCourseFeedbackWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $user = User::factory()->create(['name' => 'Feedback Student']);
        $user->assignRole('student');

        $program = Program::factory()->create();
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Term 1',
            'is_current' => true,
        ]);
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'current_term_id' => $term->id,
        ]);
        $enrolledSubject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Enrolled Marketing',
        ]);
        $unenrolledSubject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Unenrolled Finance',
        ]);

        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $enrolledSubject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);

        return compact('user', 'student', 'program', 'term', 'enrolledSubject', 'unenrolledSubject');
    }

    public function test_student_feedback_index_uses_active_enrollments_and_submitted_state(): void
    {
        $fixture = $this->fixture();
        CourseFeedback::create([
            'student_id' => $fixture['student']->id,
            'subject_id' => $fixture['enrolledSubject']->id,
            'term_id' => $fixture['term']->id,
            'teaching_rating' => 4,
            'content_rating' => 4,
            'overall_rating' => 5,
            'is_anonymous' => true,
        ]);

        $this->actingAs($fixture['user'])
            ->get(route('student.feedback.index'))
            ->assertOk()
            ->assertSee('Enrolled Marketing')
            ->assertSee('Submitted')
            ->assertDontSee('Unenrolled Finance');
    }

    public function test_student_cannot_create_or_submit_feedback_for_unenrolled_subject(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['user'])
            ->get(route('student.feedback.create', $fixture['unenrolledSubject']))
            ->assertForbidden();

        $this->actingAs($fixture['user'])
            ->post(route('student.feedback.store', $fixture['unenrolledSubject']), [
                'teaching_rating' => 5,
                'content_rating' => 5,
                'overall_rating' => 5,
                'comments' => 'This should not be accepted.',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('course_feedback', [
            'student_id' => $fixture['student']->id,
            'subject_id' => $fixture['unenrolledSubject']->id,
        ]);
    }

    public function test_student_can_submit_feedback_for_canonical_enrolled_subject_once_per_term(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['user'])
            ->get(route('student.feedback.create', $fixture['enrolledSubject']))
            ->assertOk()
            ->assertSee('Enrolled Marketing');

        $this->actingAs($fixture['user'])
            ->post(route('student.feedback.store', $fixture['enrolledSubject']), [
                'teaching_rating' => 5,
                'content_rating' => 4,
                'overall_rating' => 5,
                'comments' => 'Useful classroom examples.',
            ])
            ->assertRedirect(route('student.feedback.index'));

        $this->assertDatabaseHas('course_feedback', [
            'student_id' => $fixture['student']->id,
            'subject_id' => $fixture['enrolledSubject']->id,
            'term_id' => $fixture['term']->id,
            'overall_rating' => 5,
        ]);

        $this->actingAs($fixture['user'])
            ->get(route('student.feedback.create', $fixture['enrolledSubject']))
            ->assertRedirect(route('student.feedback.index'))
            ->assertSessionHas('info');
    }

    public function test_direct_feedback_post_cannot_overwrite_existing_feedback(): void
    {
        $fixture = $this->fixture();

        CourseFeedback::create([
            'student_id' => $fixture['student']->id,
            'subject_id' => $fixture['enrolledSubject']->id,
            'term_id' => $fixture['term']->id,
            'teaching_rating' => 3,
            'content_rating' => 4,
            'overall_rating' => 4,
            'comments' => 'Original submitted feedback.',
            'is_anonymous' => true,
        ]);

        $this->actingAs($fixture['user'])
            ->post(route('student.feedback.store', $fixture['enrolledSubject']), [
                'teaching_rating' => 1,
                'content_rating' => 1,
                'overall_rating' => 1,
                'comments' => 'Overwritten feedback should not be accepted.',
            ])
            ->assertRedirect(route('student.feedback.index'))
            ->assertSessionHas('info');

        $this->assertDatabaseHas('course_feedback', [
            'student_id' => $fixture['student']->id,
            'subject_id' => $fixture['enrolledSubject']->id,
            'term_id' => $fixture['term']->id,
            'overall_rating' => 4,
            'comments' => 'Original submitted feedback.',
        ]);
        $this->assertDatabaseMissing('course_feedback', [
            'student_id' => $fixture['student']->id,
            'subject_id' => $fixture['enrolledSubject']->id,
            'term_id' => $fixture['term']->id,
            'overall_rating' => 1,
            'comments' => 'Overwritten feedback should not be accepted.',
        ]);
    }

    public function test_inactive_student_can_view_feedback_history_but_cannot_submit_new_feedback(): void
    {
        $fixture = $this->fixture();
        $fixture['student']->update(['status' => 'inactive']);

        CourseFeedback::create([
            'student_id' => $fixture['student']->id,
            'subject_id' => $fixture['enrolledSubject']->id,
            'term_id' => $fixture['term']->id,
            'teaching_rating' => 4,
            'content_rating' => 4,
            'overall_rating' => 5,
            'comments' => 'Historical feedback remains visible.',
            'is_anonymous' => true,
        ]);

        $this->actingAs($fixture['user'])
            ->get(route('student.feedback.index'))
            ->assertOk()
            ->assertSee('Enrolled Marketing')
            ->assertSee('Submitted')
            ->assertSee('Feedback submission is locked')
            ->assertDontSee('Give Feedback');

        $this->actingAs($fixture['user'])
            ->get(route('student.feedback.create', $fixture['enrolledSubject']))
            ->assertRedirect(route('student.feedback.index'))
            ->assertSessionHas('error');

        $this->actingAs($fixture['user'])
            ->post(route('student.feedback.store', $fixture['enrolledSubject']), [
                'teaching_rating' => 1,
                'content_rating' => 1,
                'overall_rating' => 1,
                'comments' => 'Inactive update should not be accepted.',
            ])
            ->assertRedirect(route('student.feedback.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('course_feedback', [
            'student_id' => $fixture['student']->id,
            'subject_id' => $fixture['enrolledSubject']->id,
            'term_id' => $fixture['term']->id,
            'overall_rating' => 5,
            'comments' => 'Historical feedback remains visible.',
        ]);
        $this->assertDatabaseMissing('course_feedback', [
            'student_id' => $fixture['student']->id,
            'subject_id' => $fixture['enrolledSubject']->id,
            'term_id' => $fixture['term']->id,
            'overall_rating' => 1,
            'comments' => 'Inactive update should not be accepted.',
        ]);
    }

    public function test_legacy_active_enrollment_still_allows_course_feedback(): void
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $user = User::factory()->create(['name' => 'Legacy Feedback Student']);
        $user->assignRole('student');
        $term = Term::factory()->create(['term_number' => 2, 'name' => 'Term 2']);
        $semester = Semester::factory()->create(['number' => 2, 'name' => 'Term 2']);
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'current_term_id' => $term->id,
        ]);
        $subject = Subject::factory()->create(['name' => 'Legacy Strategy']);
        Enrollment::create([
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('student.feedback.index'))
            ->assertOk()
            ->assertSee('Legacy Strategy');

        $this->actingAs($user)
            ->post(route('student.feedback.store', $subject), [
                'teaching_rating' => 4,
                'content_rating' => 4,
                'overall_rating' => 4,
            ])
            ->assertRedirect(route('student.feedback.index'));

        $this->assertDatabaseHas('course_feedback', [
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'overall_rating' => 4,
        ]);
    }

    public function test_teacher_feedback_dashboard_counts_only_enrolled_student_feedback(): void
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $course = Course::factory()->create();
        $batch = Batch::factory()->create();
        $program = Program::factory()->create();
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Term 1',
            'start_date' => now()->subMonth(),
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
            'name' => 'Feedback Analytics',
        ]);
        TimetableEntry::factory()->create([
            'teacher_id' => $teacher->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'semester_id' => $semester->id,
            'term_id' => $term->id,
            'is_active' => true,
        ]);

        $enrolledStudent = Student::factory()->create([
            'program_id' => $program->id,
            'current_term_id' => $term->id,
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $enrolledStudent->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'status' => 'active',
        ]);
        CourseFeedback::create([
            'student_id' => $enrolledStudent->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'teaching_rating' => 5,
            'content_rating' => 5,
            'overall_rating' => 5,
            'comments' => 'Legitimate enrolled feedback.',
            'is_anonymous' => true,
        ]);

        $rogueStudent = Student::factory()->create([
            'program_id' => $program->id,
            'current_term_id' => $term->id,
        ]);
        CourseFeedback::create([
            'student_id' => $rogueStudent->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'teaching_rating' => 1,
            'content_rating' => 1,
            'overall_rating' => 1,
            'comments' => 'Rogue feedback should be ignored.',
            'is_anonymous' => true,
        ]);

        $this->actingAs($teacher->user)
            ->get(route('teacher.feedback.index'))
            ->assertOk()
            ->assertSee('Feedback Analytics')
            ->assertSee('1 responses')
            ->assertSee('5.0')
            ->assertSee('Legitimate enrolled feedback.')
            ->assertDontSee('Rogue feedback should be ignored.');
    }
}
