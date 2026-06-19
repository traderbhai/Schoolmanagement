<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Program;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminUserRoleIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    private function role(string $name): Role
    {
        return Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    public function test_admin_user_role_assignment_grants_actual_authorization_role(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $role = $this->role('cmc');

        $this->actingAs($admin)
            ->post(route('admin.users.roles.store'), [
                'user_id' => $target->id,
                'role_id' => $role->id,
            ])
            ->assertRedirect(route('admin.users.roles.index'))
            ->assertSessionHas('success', 'Role assigned successfully.');

        $this->assertTrue($target->fresh()->hasRole('cmc'));
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $target->id,
            'role_id' => $role->id,
            'assigned_by' => $admin->id,
        ]);
        $this->assertTrue(AuditLog::where('action', 'role_assigned')->where('target_id', $target->id)->exists());
    }

    public function test_global_user_role_assignment_rejects_program_scope_to_avoid_hidden_global_access(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $role = $this->role('exam_cell');
        $program = Program::factory()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->from(route('admin.users.roles.create'))
            ->post(route('admin.users.roles.store'), [
                'user_id' => $target->id,
                'role_id' => $role->id,
                'program_id' => $program->id,
            ])
            ->assertRedirect(route('admin.users.roles.create'))
            ->assertSessionHasErrors('program_id');

        $this->assertDatabaseMissing('user_roles', [
            'user_id' => $target->id,
            'role_id' => $role->id,
            'program_id' => $program->id,
        ]);
        $this->assertFalse($target->fresh()->hasRole('exam_cell'));
    }

    public function test_global_user_role_assignment_form_directs_program_scopes_to_scoped_role_workflow(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.users.roles.create'))
            ->assertOk()
            ->assertSee('Grant a global role with optional expiry')
            ->assertSee('Scoped Role Assignments')
            ->assertDontSee('Scope this role to a specific program');
    }

    public function test_admin_user_role_revoke_expires_history_and_removes_actual_authorization_role_when_no_other_active_assignment_exists(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $role = $this->role('cmc');
        $target->assignRole('cmc');
        $assignment = UserRole::create([
            'user_id' => $target->id,
            'role_id' => $role->id,
            'assigned_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.users.roles.destroy', $assignment))
            ->assertRedirect()
            ->assertSessionHas('success', 'Role revoked successfully.');

        $assignment->refresh();
        $this->assertSame(today()->subDay()->toDateString(), $assignment->active_until->toDateString());
        $this->assertDatabaseHas('user_roles', ['id' => $assignment->id]);
        $this->assertFalse($target->fresh()->hasRole('cmc'));
        $this->assertTrue(AuditLog::where('action', 'role_revoked')->where('target_id', $target->id)->exists());
    }

    public function test_expire_all_expires_indefinite_roles_and_removes_actual_roles(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $cmc = $this->role('cmc');
        $exam = $this->role('exam_cell');
        $target->assignRole(['cmc', 'exam_cell']);

        $cmcAssignment = UserRole::create([
            'user_id' => $target->id,
            'role_id' => $cmc->id,
            'assigned_by' => $admin->id,
            'active_until' => null,
        ]);
        $examAssignment = UserRole::create([
            'user_id' => $target->id,
            'role_id' => $exam->id,
            'assigned_by' => $admin->id,
            'active_until' => now()->addDays(3)->toDateString(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.roles.expire-all', $target))
            ->assertRedirect()
            ->assertSessionHas('success', 'All active roles revoked for ' . $target->name);

        $this->assertSame(today()->subDay()->toDateString(), $cmcAssignment->fresh()->active_until->toDateString());
        $this->assertSame(today()->subDay()->toDateString(), $examAssignment->fresh()->active_until->toDateString());
        $this->assertFalse($target->fresh()->hasAnyRole(['cmc', 'exam_cell']));
    }

    public function test_current_or_last_admin_role_cannot_be_revoked_from_user_role_management(): void
    {
        $admin = $this->admin();
        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $assignment = UserRole::create([
            'user_id' => $admin->id,
            'role_id' => $adminRole->id,
            'assigned_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.users.roles.destroy', $assignment))
            ->assertRedirect()
            ->assertSessionHas('error', 'Cannot revoke the final or current admin access. Assign another admin first.');

        $this->assertDatabaseHas('user_roles', ['id' => $assignment->id]);
        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }

    public function test_non_security_admin_cannot_manage_global_user_roles_by_direct_route(): void
    {
        $programChair = User::factory()->create();
        $programChair->assignRole($this->role('program_chair'));
        $target = User::factory()->create();
        $cmcRole = $this->role('cmc');
        $assignment = UserRole::create([
            'user_id' => $target->id,
            'role_id' => $cmcRole->id,
            'assigned_by' => $programChair->id,
        ]);
        $target->assignRole('cmc');

        $this->actingAs($programChair)
            ->get(route('admin.users.roles.index'))
            ->assertForbidden();

        $this->actingAs($programChair)
            ->get(route('admin.users.roles.create'))
            ->assertForbidden();

        $this->actingAs($programChair)
            ->post(route('admin.users.roles.store'), [
                'user_id' => $target->id,
                'role_id' => $this->role('exam_cell')->id,
            ])
            ->assertForbidden();

        $this->actingAs($programChair)
            ->delete(route('admin.users.roles.destroy', $assignment))
            ->assertForbidden();

        $this->actingAs($programChair)
            ->post(route('admin.users.roles.expire-all', $target))
            ->assertForbidden();

        $this->assertTrue($assignment->fresh()->isActive());
        $this->assertTrue($target->fresh()->hasRole('cmc'));
        $this->assertFalse($target->fresh()->hasRole('exam_cell'));
    }
}
