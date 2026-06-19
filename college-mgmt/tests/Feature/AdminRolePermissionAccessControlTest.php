<?php

namespace Tests\Feature;

use App\Models\RoleFeatureAccess;
use App\Models\RolePermissionMatrix;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminRolePermissionAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function role(string $name): Role
    {
        return Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    public function test_non_security_admin_cannot_manage_role_permission_matrix_by_direct_route(): void
    {
        $programChair = User::factory()->create();
        $programChair->assignRole($this->role('program_chair'));
        $targetRole = $this->role('exam_cell');
        $permission = Permission::firstOrCreate(['name' => 'exam.publish_results', 'guard_name' => 'web']);

        $this->actingAs($programChair)
            ->get(route('admin.roles.hierarchy'))
            ->assertForbidden();

        $this->actingAs($programChair)
            ->get(route('admin.roles.permissions.index'))
            ->assertForbidden();

        $this->actingAs($programChair)
            ->get(route('admin.roles.permissions.show', $targetRole))
            ->assertForbidden();

        $this->actingAs($programChair)
            ->put(route('admin.roles.permissions.update', $targetRole), [
                'permissions' => [$permission->id],
            ])
            ->assertForbidden();

        $this->assertFalse(RolePermissionMatrix::where('role_id', $targetRole->id)
            ->where('permission_id', $permission->id)
            ->exists());
    }

    public function test_role_permission_matrix_rejects_inactive_program_scope(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole($this->role('admin'));
        $targetRole = $this->role('program_chair');
        $permission = Permission::firstOrCreate(['name' => 'program.edit_curriculum', 'guard_name' => 'web']);
        $inactiveProgram = Program::factory()->create(['is_active' => false]);

        $this->actingAs($admin)
            ->from(route('admin.roles.permissions.show', $targetRole))
            ->put(route('admin.roles.permissions.update', $targetRole), [
                'permissions' => [$permission->id],
                'program_id' => $inactiveProgram->id,
            ])
            ->assertRedirect(route('admin.roles.permissions.show', $targetRole))
            ->assertSessionHasErrors('program_id');

        $this->assertFalse(RolePermissionMatrix::where('role_id', $targetRole->id)
            ->where('permission_id', $permission->id)
            ->where('program_id', $inactiveProgram->id)
            ->exists());
    }

    public function test_non_security_admin_cannot_manage_role_feature_access_by_direct_route(): void
    {
        $programChair = User::factory()->create();
        $programChair->assignRole($this->role('program_chair'));
        $targetRole = $this->role('exam_cell');

        $this->actingAs($programChair)
            ->get(route('admin.roles.feature-access.index'))
            ->assertForbidden();

        $this->actingAs($programChair)
            ->get(route('admin.roles.feature-access.edit', $targetRole))
            ->assertForbidden();

        $this->actingAs($programChair)
            ->put(route('admin.roles.feature-access.update', $targetRole), [
                'access' => [
                    'exam.publish_results' => 'approve',
                ],
            ])
            ->assertForbidden();

        $this->assertFalse(RoleFeatureAccess::where('role_id', $targetRole->id)
            ->where('feature_code', 'exam.publish_results')
            ->exists());
    }

    public function test_role_feature_access_rejects_unknown_feature_codes_from_direct_post(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole($this->role('admin'));
        $targetRole = $this->role('exam_cell');

        $this->actingAs($admin)
            ->from(route('admin.roles.feature-access.edit', $targetRole))
            ->put(route('admin.roles.feature-access.update', $targetRole), [
                'access' => [
                    'exam.publish_results' => 'approve',
                    'system.superuser_backdoor' => 'delete',
                ],
            ])
            ->assertRedirect(route('admin.roles.feature-access.edit', $targetRole))
            ->assertSessionHasErrors('access');

        $this->assertFalse(RoleFeatureAccess::where('role_id', $targetRole->id)
            ->whereIn('feature_code', ['exam.publish_results', 'system.superuser_backdoor'])
            ->exists());
    }
}
