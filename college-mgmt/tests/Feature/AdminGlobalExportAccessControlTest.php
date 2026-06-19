<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminGlobalExportAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_broad_academic_role_cannot_download_global_admin_exports(): void
    {
        $programChair = $this->userWithRole('program_chair');

        $this->actingAs($programChair)
            ->get(route('admin.students.export'))
            ->assertForbidden();

        $this->actingAs($programChair)
            ->get(route('admin.attendance.export'))
            ->assertForbidden();

        $this->actingAs($programChair)
            ->get(route('admin.fees.export'))
            ->assertForbidden();
    }

    public function test_dean_can_download_academic_global_exports_but_not_fee_export(): void
    {
        $dean = $this->userWithRole('dean_academics');

        $this->actingAs($dean)
            ->get(route('admin.students.export'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->actingAs($dean)
            ->get(route('admin.attendance.export'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->actingAs($dean)
            ->get(route('admin.fees.export'))
            ->assertForbidden();
    }

    public function test_accounts_officer_keeps_fee_export_without_academic_global_exports(): void
    {
        $accounts = $this->userWithRole('accounts_officer');

        $this->actingAs($accounts)
            ->get(route('admin.fees.export'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->actingAs($accounts)
            ->get(route('admin.students.export'))
            ->assertForbidden();

        $this->actingAs($accounts)
            ->get(route('admin.attendance.export'))
            ->assertForbidden();
    }
}
