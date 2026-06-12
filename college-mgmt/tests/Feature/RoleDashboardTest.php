<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Smoke-tests that every role dashboard returns HTTP 200 after login.
 */
class RoleDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithRole(string $roleName): User
    {
        Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($roleName);
        return $user;
    }

    public function test_admin_dashboard_accessible(): void
    {
        $user = $this->makeUserWithRole('admin');
        $this->actingAs($user)->get(route('admin.dashboard'))->assertStatus(200);
    }

    public function test_dean_academics_dashboard_accessible(): void
    {
        $user = $this->makeUserWithRole('dean_academics');
        $this->actingAs($user)->get(route('dean.dashboard'))->assertStatus(200);
    }

    public function test_hod_dashboard_accessible(): void
    {
        $user = $this->makeUserWithRole('hod');
        $this->actingAs($user)->get(route('hod.dashboard'))->assertStatus(200);
    }

    public function test_program_chair_dashboard_accessible(): void
    {
        $user = $this->makeUserWithRole('program_chair');
        $this->actingAs($user)->get(route('chair.dashboard'))->assertStatus(200);
    }

    public function test_exam_cell_dashboard_accessible(): void
    {
        $user = $this->makeUserWithRole('exam_cell');
        $this->actingAs($user)->get(route('exam-cell.dashboard'))->assertStatus(200);
    }

    public function test_accounts_dashboard_accessible(): void
    {
        $user = $this->makeUserWithRole('accounts_officer');
        $this->actingAs($user)->get(route('accounts.dashboard'))->assertStatus(200);
    }

    public function test_cmc_dashboard_accessible(): void
    {
        $user = $this->makeUserWithRole('cmc');
        $this->actingAs($user)->get(route('cmc.dashboard'))->assertStatus(200);
    }

    public function test_director_dashboard_accessible(): void
    {
        $user = $this->makeUserWithRole('director');
        $this->actingAs($user)->get(route('director.dashboard'))->assertStatus(200);
    }

    public function test_unauthenticated_redirected_to_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_wrong_role_cannot_access_admin_dashboard(): void
    {
        $user = $this->makeUserWithRole('teacher');
        $response = $this->actingAs($user)->get(route('admin.dashboard'));
        $response->assertStatus(403);
    }
}
