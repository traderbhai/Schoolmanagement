<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApprovalWorkflow;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Program;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DeanDashboardGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private function deanUser(): User
    {
        Role::firstOrCreate(['name' => 'dean_academics', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('dean_academics');

        return $user;
    }

    private function pendingDeanApproval(?Program $program = null, array $extra = []): ApprovalWorkflow
    {
        $applicant = Applicant::factory()->create(['program_id' => $program?->id ?? Program::factory()->create()->id]);

        return ApprovalWorkflow::create(array_merge([
            'approvable_type' => Applicant::class,
            'approvable_id' => $applicant->id,
            'approver_role' => 'dean_academics',
            'status' => 'pending',
        ], $extra));
    }

    public function test_dean_dashboard_prioritizes_overdue_approvals(): void
    {
        $user = $this->deanUser();
        $program = Program::factory()->create(['name' => 'BCA']);
        $subject = Subject::factory()->create(['program_id' => $program->id]);
        $exam = Exam::factory()->create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'passing_marks' => 40,
        ]);
        $student = Student::factory()->create(['program_id' => $program->id]);
        ExamResult::factory()->create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'marks_obtained' => 80,
        ]);

        $this->pendingDeanApproval($program, ['due_at' => now()->subDay()]);

        $this->actingAs($user)
            ->get(route('dean.dashboard'))
            ->assertStatus(200)
            ->assertSee('Dean Priority')
            ->assertSee('Clear 1 overdue dean approval')
            ->assertSee('BCA')
            ->assertSee('100%')
            ->assertSee(route('dean.approvals'), false);
    }

    public function test_dean_approval_action_generates_offer_and_only_one_program_chair_approval(): void
    {
        $user = $this->deanUser();
        $approval = $this->pendingDeanApproval();

        $this->actingAs($user)
            ->post(route('dean.approve', $approval), ['remarks' => 'Cleared'])
            ->assertRedirect();

        $this->assertDatabaseHas('offer_letters', [
            'applicant_id' => $approval->approvable_id,
            'status' => 'issued',
        ]);
        $this->assertSame(1, ApprovalWorkflow::where('approvable_type', Applicant::class)
            ->where('approvable_id', $approval->approvable_id)
            ->where('approver_role', 'program_chair')
            ->where('status', 'pending')
            ->count());

        $this->actingAs($user)
            ->post(route('dean.approve', $approval), ['remarks' => 'Duplicate'])
            ->assertForbidden();
    }

    public function test_dean_cannot_approve_non_dean_approval(): void
    {
        $user = $this->deanUser();
        $approval = ApprovalWorkflow::create([
            'approvable_type' => Applicant::class,
            'approvable_id' => Applicant::factory()->create()->id,
            'approver_role' => 'hod',
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->post(route('dean.approve', $approval), ['remarks' => 'Wrong queue'])
            ->assertForbidden();
    }
}
