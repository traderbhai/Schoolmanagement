<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminGlobalReadAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_broad_academic_role_cannot_access_global_admin_read_surfaces(): void
    {
        $programChair = $this->userWithRole('program_chair');

        $this->actingAs($programChair)
            ->get(route('admin.search', ['q' => 'student']))
            ->assertForbidden();

        $this->actingAs($programChair)
            ->get(route('admin.activity-log'))
            ->assertForbidden();

        $this->actingAs($programChair)
            ->get(route('admin.analytics'))
            ->assertForbidden();
    }

    public function test_dean_keeps_allowed_institutional_search_and_analytics_without_audit_log_access(): void
    {
        $dean = $this->userWithRole('dean_academics');

        $this->actingAs($dean)
            ->get(route('admin.search'))
            ->assertOk();

        $this->actingAs($dean)
            ->get(route('admin.analytics'))
            ->assertOk();

        $this->actingAs($dean)
            ->get(route('admin.activity-log'))
            ->assertForbidden();
    }
}
