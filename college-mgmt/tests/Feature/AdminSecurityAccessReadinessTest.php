<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\OrgReportingLine;
use App\Models\Program;
use App\Models\RoleFeatureAccess;
use App\Models\RolePermissionMatrix;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminSecurityAccessReadinessTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_director_can_operate_security_configuration_surfaces(): void
    {
        $director = $this->userWithRole('director');
        $target = User::factory()->create();
        $targetRole = Role::firstOrCreate(['name' => 'exam_cell', 'guard_name' => 'web']);
        $permission = Permission::firstOrCreate(['name' => 'exam.publish_results', 'guard_name' => 'web']);
        $program = Program::factory()->create(['is_active' => true]);

        $this->actingAs($director)->get(route('admin.roles.hierarchy'))->assertOk();
        $this->actingAs($director)->get(route('admin.roles.permissions.index'))->assertOk();
        $this->actingAs($director)->get(route('admin.roles.permissions.show', $targetRole))->assertOk();
        $this->actingAs($director)
            ->put(route('admin.roles.permissions.update', $targetRole), [
                'permissions' => [$permission->id],
                'program_id' => $program->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('role_permission_matrices', [
            'role_id' => $targetRole->id,
            'permission_id' => $permission->id,
            'program_id' => $program->id,
            'program_specific' => true,
        ]);

        $this->actingAs($director)->get(route('admin.roles.feature-access.index'))->assertOk();
        $this->actingAs($director)->get(route('admin.roles.feature-access.edit', $targetRole))->assertOk();
        $this->actingAs($director)
            ->put(route('admin.roles.feature-access.update', $targetRole), [
                'access' => ['exam.publish_results' => 'approve'],
            ])
            ->assertRedirect(route('admin.roles.feature-access.index'));

        $this->assertTrue(RoleFeatureAccess::where('role_id', $targetRole->id)
            ->where('feature_code', 'exam.publish_results')
            ->where('access_level', 'approve')
            ->exists());

        $this->actingAs($director)->get(route('admin.users.roles.index'))->assertOk();
        $this->actingAs($director)->get(route('admin.users.roles.create'))->assertOk();
        $this->actingAs($director)
            ->post(route('admin.users.roles.store'), [
                'user_id' => $target->id,
                'role_id' => $targetRole->id,
            ])
            ->assertRedirect(route('admin.users.roles.index'));

        $this->assertTrue($target->fresh()->hasRole('exam_cell'));

        $this->actingAs($director)->get(route('admin.role-assignments.index'))->assertOk();
        $this->actingAs($director)->get(route('admin.role-assignments.create'))->assertOk();
    }

    public function test_director_can_operate_system_hierarchy_and_audit_surfaces(): void
    {
        $director = $this->userWithRole('director');
        $line = OrgReportingLine::where('parent_role', 'dean_academics')
            ->where('child_role', 'program_chair')
            ->firstOrFail();
        $log = AuditLog::create([
            'actor_id' => $director->id,
            'action' => 'security_readiness_test',
            'target_type' => User::class,
            'target_id' => $director->id,
            'changes' => ['status' => 'ok'],
        ]);

        $this->actingAs($director)->get(route('admin.settings'))->assertOk();
        $this->actingAs($director)->get(route('admin.settings.branding'))->assertOk();
        $this->actingAs($director)->get(route('admin.api-docs'))->assertOk();
        $this->actingAs($director)
            ->post(route('admin.settings.update'), [
                'institute_name' => 'Director Managed Institute',
                'short_name' => 'DMI',
                'email' => 'director-managed@example.test',
                'phone' => '1234567890',
                'website' => 'https://example.test',
            ])
            ->assertRedirect();

        $this->actingAs($director)->get(route('admin.org-hierarchy.index'))->assertOk();
        $this->actingAs($director)
            ->patch(route('admin.org-hierarchy.update', $line), [
                'can_view_summary' => '1',
                'can_view_full' => '1',
            ])
            ->assertRedirect();

        $this->actingAs($director)->get(route('admin.audit.index'))->assertOk();
        $this->actingAs($director)->get(route('admin.audit.search', ['q' => 'security']))->assertOk();
        $this->actingAs($director)->get(route('admin.audit.show', $log))->assertOk();
    }

    public function test_security_configuration_rejects_invalid_scoped_and_feature_payloads_without_partial_writes(): void
    {
        $director = $this->userWithRole('director');
        $targetRole = Role::firstOrCreate(['name' => 'program_chair', 'guard_name' => 'web']);
        $permission = Permission::firstOrCreate(['name' => 'program.edit_curriculum', 'guard_name' => 'web']);
        $inactiveProgram = Program::factory()->create(['is_active' => false]);

        $this->actingAs($director)
            ->from(route('admin.roles.permissions.show', $targetRole))
            ->put(route('admin.roles.permissions.update', $targetRole), [
                'permissions' => [$permission->id],
                'program_id' => $inactiveProgram->id,
            ])
            ->assertRedirect(route('admin.roles.permissions.show', $targetRole))
            ->assertSessionHasErrors('program_id');

        $this->assertFalse(RolePermissionMatrix::where('role_id', $targetRole->id)
            ->where('program_id', $inactiveProgram->id)
            ->exists());

        $this->actingAs($director)
            ->from(route('admin.roles.feature-access.edit', $targetRole))
            ->put(route('admin.roles.feature-access.update', $targetRole), [
                'access' => [
                    'exam.publish_results' => 'approve',
                    'security.hidden_feature' => 'delete',
                ],
            ])
            ->assertRedirect(route('admin.roles.feature-access.edit', $targetRole))
            ->assertSessionHasErrors('access');

        $this->assertFalse(RoleFeatureAccess::where('role_id', $targetRole->id)->exists());
    }
}
