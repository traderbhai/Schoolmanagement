<?php

namespace Tests\Feature;

use App\Models\RoleFeatureAccess;
use App\Models\RolePermissionMatrix;
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
}
