<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAuditLogAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_director_can_view_audit_logs(): void
    {
        $log = $this->auditLog();

        foreach (['admin', 'director'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('admin.audit.index'))->assertOk();
            $this->actingAs($user)->get(route('admin.audit.search', ['q' => 'role']))->assertOk();
            $this->actingAs($user)->get(route('admin.audit.show', $log))->assertOk();
        }
    }

    public function test_broad_admin_group_roles_cannot_view_audit_logs(): void
    {
        $log = $this->auditLog();

        foreach (['dean_academics', 'program_chair', 'hod', 'exam_cell', 'accounts_officer', 'cmc'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('admin.audit.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.audit.search', ['q' => 'role']))->assertForbidden();
            $this->actingAs($user)->get(route('admin.audit.show', $log))->assertForbidden();
        }
    }

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function auditLog(): AuditLog
    {
        $actor = $this->userWithRole('admin');

        return AuditLog::create([
            'actor_id' => $actor->id,
            'action' => 'role_assigned',
            'target_type' => User::class,
            'target_id' => $actor->id,
            'changes' => [
                'role' => 'admin',
                'user_email' => $actor->email,
            ],
        ]);
    }
}
