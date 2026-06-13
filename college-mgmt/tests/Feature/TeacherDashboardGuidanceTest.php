<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeacherDashboardGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private function makeTeacher(): Teacher
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $user = User::factory()->create(['name' => 'Teacher Guide']);
        $user->assignRole('teacher');

        return Teacher::factory()->create(['user_id' => $user->id]);
    }

    private function makeAssignment(Teacher $teacher, array $overrides = []): Assignment
    {
        return Assignment::create(array_merge([
            'subject_id' => Subject::factory()->create()->id,
            'created_by' => $teacher->user_id,
            'title' => 'Case Analysis',
            'description' => 'Review the submitted case.',
            'max_marks' => 100,
            'due_at' => now()->addDays(3),
            'is_published' => true,
        ], $overrides));
    }

    public function test_teacher_dashboard_prioritizes_pending_grading(): void
    {
        $teacher = $this->makeTeacher();
        $assignment = $this->makeAssignment($teacher);

        AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => Student::factory()->create()->id,
            'answer_text' => 'My submission',
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);

        $this->actingAs($teacher->user)
            ->get(route('teacher.dashboard'))
            ->assertStatus(200)
            ->assertSee('Grade 1 pending submission')
            ->assertSee('Review Submissions')
            ->assertSee(route('teacher.assignments.index'), false);
    }

    public function test_teacher_dashboard_shows_active_assignment_priority_when_no_grading_pending(): void
    {
        $teacher = $this->makeTeacher();
        $this->makeAssignment($teacher);

        $this->actingAs($teacher->user)
            ->get(route('teacher.dashboard'))
            ->assertStatus(200)
            ->assertSee('Monitor active assignments')
            ->assertSee('View Assignments');
    }

    public function test_teacher_dashboard_has_empty_priority_state(): void
    {
        $teacher = $this->makeTeacher();

        $this->actingAs($teacher->user)
            ->get(route('teacher.dashboard'))
            ->assertStatus(200)
            ->assertSee('No urgent teaching action due today')
            ->assertSee('Upload Material')
            ->assertSee('View Timetable');
    }
}
