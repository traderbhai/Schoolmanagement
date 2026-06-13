<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApprovalWorkflow;
use App\Models\Program;
use App\Models\RoleProgramAssignment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProgramChairDashboardGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private function chairUser(?Program $program = null): User
    {
        Role::firstOrCreate(['name' => 'program_chair', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('program_chair');

        if ($program) {
            RoleProgramAssignment::create([
                'user_id' => $user->id,
                'role_name' => 'program_chair',
                'program_id' => $program->id,
                'is_active' => true,
                'assigned_by' => $user->id,
                'assigned_at' => now(),
            ]);
        }

        return $user;
    }

    private function pendingApprovalFor(Program $program): ApprovalWorkflow
    {
        $applicant = Applicant::factory()->create(['program_id' => $program->id]);

        return ApprovalWorkflow::create([
            'approvable_type' => Applicant::class,
            'approvable_id' => $applicant->id,
            'approver_role' => 'program_chair',
            'status' => 'pending',
        ]);
    }

    public function test_dashboard_shows_assignment_needed_when_program_chair_has_no_program_scope(): void
    {
        $user = $this->chairUser();
        Student::factory()->count(2)->create();

        $this->actingAs($user)
            ->get(route('chair.dashboard'))
            ->assertStatus(200)
            ->assertSee('Program Chair Priority')
            ->assertSee('Program assignment needed')
            ->assertSee('No active program is assigned')
            ->assertSee('>0<', false);
    }

    public function test_dashboard_prioritizes_pending_approvals_for_assigned_program_only(): void
    {
        $assignedProgram = Program::factory()->create(['name' => 'Assigned Program']);
        $otherProgram = Program::factory()->create(['name' => 'Other Program']);
        $user = $this->chairUser($assignedProgram);

        $this->pendingApprovalFor($assignedProgram);
        $this->pendingApprovalFor($otherProgram);

        $this->actingAs($user)
            ->get(route('chair.dashboard'))
            ->assertStatus(200)
            ->assertSee('Review 1 pending approval')
            ->assertSee('Open Approvals')
            ->assertSee(route('chair.approvals'), false);

        $this->actingAs($user)
            ->get(route('chair.approvals'))
            ->assertStatus(200)
            ->assertSee('Assigned Program')
            ->assertDontSee('Other Program');
    }

    public function test_program_chair_cannot_approve_another_program_approval(): void
    {
        $assignedProgram = Program::factory()->create();
        $otherProgram = Program::factory()->create();
        $user = $this->chairUser($assignedProgram);
        $foreignApproval = $this->pendingApprovalFor($otherProgram);

        $this->actingAs($user)
            ->post(route('chair.approve', $foreignApproval), ['remarks' => 'Looks fine'])
            ->assertForbidden();
    }
}
