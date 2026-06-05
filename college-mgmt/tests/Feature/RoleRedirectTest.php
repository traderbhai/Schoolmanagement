<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'teacher',  'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'student',  'guard_name' => 'web']);
    }

    public function test_admin_redirected_to_admin_dashboard(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $user->assignRole('admin');

        $response = $this->actingAs($user)->get('/');
        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_teacher_redirected_to_teacher_dashboard(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $user->assignRole('teacher');

        $response = $this->actingAs($user)->get('/');
        $response->assertRedirect(route('teacher.dashboard'));
    }

    public function test_student_redirected_to_student_dashboard(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $user->assignRole('student');

        $response = $this->actingAs($user)->get('/');
        $response->assertRedirect(route('student.dashboard'));
    }
}
