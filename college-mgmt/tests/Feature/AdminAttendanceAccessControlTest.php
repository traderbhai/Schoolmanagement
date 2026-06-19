<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAttendanceAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_global_academic_authority_roles_can_open_admin_attendance_surfaces(): void
    {
        foreach (['admin', 'director', 'dean_academics'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('admin.attendance.index'))->assertOk();
            $this->actingAs($user)->get(route('admin.attendance.report'))->assertOk();
        }
    }

    public function test_scoped_or_non_attendance_admin_group_roles_cannot_open_global_attendance_surfaces(): void
    {
        foreach (['program_chair', 'hod', 'exam_cell', 'accounts_officer', 'cmc'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('admin.attendance.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.attendance.report'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.attendance.export'))->assertForbidden();
        }
    }

    public function test_scoped_roles_cannot_probe_or_mutate_global_attendance_routes_directly(): void
    {
        $chair = $this->userWithRole('program_chair');

        $this->actingAs($chair)->get(route('admin.attendance.entries'))->assertForbidden();
        $this->actingAs($chair)->get(route('admin.attendance.mark'))->assertForbidden();
        $this->actingAs($chair)->post(route('admin.attendance.store'))->assertForbidden();
    }
}
