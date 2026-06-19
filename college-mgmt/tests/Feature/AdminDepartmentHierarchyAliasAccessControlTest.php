<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentMember;
use App\Models\DepartmentRole;
use App\Models\DepartmentTeam;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminDepartmentHierarchyAliasAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function ownedDepartment(User $owner): Department
    {
        $department = Department::updateOrCreate(
            ['code' => 'ACC'],
            [
                'name' => 'Accounts',
                'description' => 'Accounts office',
                'is_active' => true,
            ]
        );

        $ownerRole = DepartmentRole::updateOrCreate(
            [
                'department_id' => $department->id,
                'code' => 'department_owner',
            ],
            [
                'name' => 'Department Owner',
                'level' => 10,
                'can_manage_lower_levels' => true,
                'can_view_team_data' => true,
                'can_assign_work' => true,
                'permissions' => ['manage_department_settings', 'configure_department'],
                'is_active' => true,
            ]
        );

        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $ownerRole->id,
            'user_id' => $owner->id,
            'is_active' => true,
        ]);

        return $department;
    }

    public function test_department_owner_uses_scoped_route_but_cannot_write_through_admin_alias(): void
    {
        $owner = $this->userWithRole('accounts_officer');
        $department = $this->ownedDepartment($owner);
        $team = DepartmentTeam::create([
            'department_id' => $department->id,
            'name' => 'Dormant Operations',
            'type' => 'function',
            'is_active' => true,
        ]);

        $this->actingAs($owner)->post(route('department-hierarchy.roles.store'), [
            'department_id' => $department->id,
            'name' => 'Scoped Operations Lead',
            'code' => 'scoped_operations_lead',
            'level' => 30,
            'permissions' => ['view_assigned'],
        ])->assertRedirect();

        $this->assertDatabaseHas('department_roles', [
            'department_id' => $department->id,
            'code' => 'scoped_operations_lead',
            'is_active' => true,
        ]);

        $this->actingAs($owner)->post(route('admin.department-hierarchy.roles.store'), [
            'department_id' => $department->id,
            'name' => 'Alias Operations Lead',
            'code' => 'alias_operations_lead',
            'level' => 30,
            'permissions' => ['view_assigned'],
        ])->assertForbidden();

        $this->actingAs($owner)
            ->patch(route('admin.department-hierarchy.teams.deactivate', $team))
            ->assertForbidden();

        $this->assertDatabaseMissing('department_roles', [
            'department_id' => $department->id,
            'code' => 'alias_operations_lead',
        ]);
        $this->assertTrue($team->refresh()->is_active);
    }

    public function test_admin_alias_department_hierarchy_write_requires_system_configuration_authority(): void
    {
        $admin = $this->userWithRole('admin');
        $department = Department::create([
            'name' => 'Administration',
            'code' => 'ADM-GOV',
            'description' => 'Governance department',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.department-hierarchy.roles.store'), [
            'department_id' => $department->id,
            'name' => 'Admin Alias Role',
            'code' => 'admin_alias_role',
            'level' => 30,
            'permissions' => ['view_assigned'],
        ])->assertRedirect();

        $this->assertDatabaseHas('department_roles', [
            'department_id' => $department->id,
            'code' => 'admin_alias_role',
            'is_active' => true,
        ]);
    }
}
