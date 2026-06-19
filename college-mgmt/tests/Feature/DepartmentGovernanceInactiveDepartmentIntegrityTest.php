<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentFeatureSetting;
use App\Models\DepartmentImpersonationSession;
use App\Models\DepartmentMember;
use App\Models\DepartmentRole;
use App\Models\DepartmentTeam;
use App\Models\User;
use App\Services\DepartmentHierarchyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DepartmentGovernanceInactiveDepartmentIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function attachMember(Department $department, User $user, string $roleCode, ?DepartmentMember $manager = null): DepartmentMember
    {
        $role = DepartmentRole::where('department_id', $department->id)
            ->where('code', $roleCode)
            ->firstOrFail();

        return DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $role->id,
            'user_id' => $user->id,
            'reports_to_member_id' => $manager?->id,
            'is_active' => true,
        ]);
    }

    public function test_inactive_department_cannot_be_configured_or_impersonated(): void
    {
        $department = Department::where('code', 'ACC')->firstOrFail();
        $owner = $this->userWithRole('accounts_officer');
        $staff = $this->userWithRole('accounts_officer');
        $ownerMember = $this->attachMember($department, $owner, 'department_owner');
        $staffMember = $this->attachMember($department, $staff, 'department_staff', $ownerMember);
        $team = DepartmentTeam::create([
            'department_id' => $department->id,
            'name' => 'Inactive Finance Ops',
            'type' => 'function',
            'is_active' => true,
        ]);

        $department->update(['is_active' => false]);

        $this->actingAs($owner)
            ->get(route('department-governance.index', ['department_id' => $department->id]))
            ->assertForbidden();

        $this->actingAs($owner)->post(route('department-governance.features.update', $department), [
            'feature_key' => 'accounts.reconciliation',
            'is_enabled' => 0,
        ])->assertForbidden();

        $this->actingAs($owner)
            ->post(route('department-governance.impersonation.start', $staffMember), [
                'reason' => 'Inactive department support',
            ])->assertForbidden();

        $this->actingAs($owner)->post(route('department-hierarchy.roles.store'), [
            'department_id' => $department->id,
            'name' => 'Inactive Department Role',
            'code' => 'inactive_department_role',
            'level' => 30,
            'permissions' => ['view_assigned'],
        ])->assertForbidden();

        $this->actingAs($owner)
            ->patch(route('department-hierarchy.teams.deactivate', $team))
            ->assertForbidden();

        $this->assertDatabaseMissing('department_feature_settings', [
            'department_id' => $department->id,
            'feature_key' => 'accounts.reconciliation',
        ]);
        $this->assertDatabaseMissing('department_roles', [
            'department_id' => $department->id,
            'code' => 'inactive_department_role',
        ]);
        $this->assertSame(0, DepartmentImpersonationSession::count());
        $this->assertTrue($team->refresh()->is_active);
    }

    public function test_inactive_department_is_excluded_from_admin_governance_lists(): void
    {
        $department = Department::where('code', 'ACC')->firstOrFail();
        $department->update(['is_active' => false]);

        $admin = $this->userWithRole('admin');

        $this->assertFalse(
            app(DepartmentHierarchyService::class)
                ->manageableGovernanceDepartments($admin)
                ->contains('id', $department->id)
        );

        $this->actingAs($admin)
            ->get(route('department-governance.index'))
            ->assertOk();

        $this->actingAs($admin)->post(route('department-governance.features.update', $department), [
            'feature_key' => 'accounts.reconciliation',
            'is_enabled' => 0,
        ])->assertForbidden();

        $this->assertSame(0, DepartmentFeatureSetting::where('department_id', $department->id)->count());
    }
}
