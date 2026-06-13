<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_not_found_page_uses_commercial_recovery_copy(): void
    {
        $this->get('/definitely-missing-commercial-page')
            ->assertStatus(404)
            ->assertSee('We could not find that page')
            ->assertSee('Go to my dashboard');
    }

    public function test_forbidden_page_uses_role_access_recovery_copy(): void
    {
        Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('applicant');

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertStatus(403)
            ->assertSee('This area is restricted')
            ->assertSee('role, department, and program permissions');
    }

    public function test_server_error_view_has_actionable_support_copy(): void
    {
        $this->view('errors.500')
            ->assertSee('The service hit a problem')
            ->assertSee('application logs, queue health, recent deployments, and failed jobs');
    }
}
