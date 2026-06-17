<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApprovalWorkflow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SharedApprovalWorkflowIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function approval(array $overrides = []): ApprovalWorkflow
    {
        $applicant = Applicant::factory()->create();

        return ApprovalWorkflow::create(array_merge([
            'approvable_type' => Applicant::class,
            'approvable_id' => $applicant->id,
            'approver_role' => 'hod',
            'workflow_type' => 'offer_letter',
            'step_order' => 1,
            'status' => 'pending',
            'sla_hours' => 24,
            'due_at' => now()->addDay(),
        ], $overrides));
    }

    public function test_assigned_approver_role_can_view_shared_approval_chain(): void
    {
        $approval = $this->approval();
        $hod = $this->userWithRole('hod');

        $this->actingAs($hod)
            ->get(route('approvals.chain', $approval))
            ->assertOk()
            ->assertSee('Approval Chain')
            ->assertSee('HOD Clearance');
    }

    public function test_unrelated_user_cannot_view_shared_approval_chain_by_direct_url(): void
    {
        $approval = $this->approval();
        $teacher = $this->userWithRole('teacher');

        $this->actingAs($teacher)
            ->get(route('approvals.chain', $approval))
            ->assertForbidden();
    }

    public function test_shared_approval_advance_creates_next_chain_step_once(): void
    {
        $approval = $this->approval();
        $hod = $this->userWithRole('hod');

        $this->actingAs($hod)
            ->post(route('approvals.approve', $approval), [
                'remarks' => 'Cleared by HOD.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Approved. Next step sent to Dean Academics.');

        $this->assertDatabaseHas('approval_workflows', [
            'id' => $approval->id,
            'status' => 'approved',
            'approver_id' => $hod->id,
            'workflow_type' => 'offer_letter',
            'step_order' => 1,
        ]);

        $this->assertDatabaseHas('approval_workflows', [
            'approvable_type' => Applicant::class,
            'approvable_id' => $approval->approvable_id,
            'approver_role' => 'dean_academics',
            'workflow_type' => 'offer_letter',
            'step_order' => 2,
            'status' => 'pending',
            'parent_approval_id' => $approval->id,
        ]);

        $this->actingAs($hod)
            ->post(route('approvals.approve', $approval->fresh()), [
                'remarks' => 'Trying again.',
            ])
            ->assertStatus(422);

        $this->assertSame(
            1,
            ApprovalWorkflow::where('approvable_type', Applicant::class)
                ->where('approvable_id', $approval->approvable_id)
                ->where('approver_role', 'dean_academics')
                ->where('status', 'pending')
                ->count()
        );
    }
}
