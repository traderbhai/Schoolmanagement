<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminGlobalReportingAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_broad_academic_role_cannot_access_global_reporting_surfaces(): void
    {
        $programChair = $this->userWithRole('program_chair');

        $this->actingAs($programChair)
            ->get(route('admin.placements.export'))
            ->assertForbidden();

        $this->actingAs($programChair)
            ->get(route('admin.institutional-kpi'))
            ->assertForbidden();

        $this->actingAs($programChair)
            ->get(route('admin.aicte-report'))
            ->assertForbidden();

        $this->actingAs($programChair)
            ->get(route('admin.aicte-report.pdf'))
            ->assertForbidden();

        $this->actingAs($programChair)
            ->get(route('admin.email-logs.index'))
            ->assertForbidden();
    }

    public function test_cmc_can_export_placements_but_not_regulatory_or_email_reports(): void
    {
        $cmc = $this->userWithRole('cmc');

        $this->actingAs($cmc)
            ->get(route('admin.placements.export'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->actingAs($cmc)
            ->get(route('admin.institutional-kpi'))
            ->assertForbidden();

        $this->actingAs($cmc)
            ->get(route('admin.email-logs.index'))
            ->assertForbidden();
    }

    public function test_dean_can_view_regulatory_reports_but_not_email_logs(): void
    {
        $dean = $this->userWithRole('dean_academics');

        $this->actingAs($dean)
            ->get(route('admin.institutional-kpi'))
            ->assertOk();

        $this->actingAs($dean)
            ->get(route('admin.aicte-report'))
            ->assertOk();

        $this->actingAs($dean)
            ->get(route('admin.email-logs.index'))
            ->assertForbidden();
    }
}
