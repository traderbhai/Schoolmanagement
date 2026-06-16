<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LayoutProfileNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['name' => 'Admin Profile Link']);
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_admin_layout_profile_menu_links_to_real_account_profile(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('profile.edit'), false)
            ->assertDontSee('<a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i>View Profile</a>', false);
    }

    public function test_obe_breadcrumb_links_to_real_framework_page(): void
    {
        $admin = $this->adminUser();

        foreach ([
            'academic.obe.co.index',
            'academic.obe.po.index',
            'academic.obe.matrix',
            'academic.obe.attainment',
            'academic.obe.surveys.index',
        ] as $route) {
            $this->actingAs($admin)
                ->get(route($route))
                ->assertOk()
                ->assertSee(route('academic.obe.co.index'), false)
                ->assertDontSee('<a href="#">OBE Framework</a>', false);
        }
    }

    public function test_teacher_layout_profile_menu_links_to_teacher_profile(): void
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $user = User::factory()->create(['name' => 'Teacher Profile Link']);
        $user->assignRole('teacher');
        Teacher::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('teacher.dashboard'))
            ->assertOk()
            ->assertSee(route('teacher.profile'), false)
            ->assertDontSee('<a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i>View Profile</a>', false);
    }
}
