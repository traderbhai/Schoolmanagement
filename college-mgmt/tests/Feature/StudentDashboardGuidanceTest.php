<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\FeeDemand;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentSubjectEnrollment;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentDashboardGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(): Student
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $program = Program::factory()->create(['name' => 'Student Guidance Program']);
        $user = User::factory()->create(['name' => 'Student Guide']);
        $user->assignRole('student');

        return Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
        ]);
    }

    public function test_dashboard_uses_fee_demands_for_outstanding_priority(): void
    {
        $student = $this->makeStudent();
        $term = Term::factory()->create(['program_id' => $student->program_id]);

        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'total_amount' => 10000,
            'scholarship_deduction' => 0,
            'final_amount' => 10000,
            'penalty_amount' => 2500,
            'status' => 'overdue',
        ]);
        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'total_amount' => 5000,
            'scholarship_deduction' => 0,
            'final_amount' => 5000,
            'penalty_amount' => 0,
            'status' => 'fully_paid',
        ]);

        $this->actingAs($student->user)
            ->get(route('student.dashboard'))
            ->assertStatus(200)
            ->assertSee('Clear pending fee balance')
            ->assertSee('Review Fees')
            ->assertSee('Rs. 12,500')
            ->assertDontSee('Rs. 17,500');
    }

    public function test_dashboard_prioritizes_upcoming_assignment_when_no_fee_or_attendance_blocker(): void
    {
        $student = $this->makeStudent();
        $subject = Subject::factory()->create(['program_id' => $student->program_id, 'name' => 'Business Analytics']);
        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'status' => 'active',
        ]);

        Assignment::create([
            'subject_id' => $subject->id,
            'created_by' => User::factory()->create()->id,
            'title' => 'Analytics Case Study',
            'description' => 'Submit the case analysis.',
            'max_marks' => 100,
            'due_at' => now()->addDays(2),
            'is_published' => true,
        ]);

        $this->actingAs($student->user)
            ->get(route('student.dashboard'))
            ->assertStatus(200)
            ->assertSee('Submit 1 upcoming assignment')
            ->assertSee('Open Assignments')
            ->assertSee('Analytics Case Study');
    }

    public function test_dashboard_has_clear_empty_priority_state(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student->user)
            ->get(route('student.dashboard'))
            ->assertStatus(200)
            ->assertSee('No urgent academic action due today')
            ->assertSee('Review Courses')
            ->assertSee('Need Help?');
    }
}
