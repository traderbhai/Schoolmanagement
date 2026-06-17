<?php

namespace Tests\Feature;

use App\Models\AuditLog;
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

    public function test_admin_user_role_revoke_removes_actual_authorization_role_when_no_other_active_assignment_exists(): void
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

        $this->assertDatabaseMissing('user_roles', ['id' => $assignment->id]);
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
}
