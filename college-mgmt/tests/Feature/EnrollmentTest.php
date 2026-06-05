<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin',  'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
    }

    public function test_admin_can_view_enrollments_index(): void
    {
        $admin = User::factory()->create(['password' => Hash::make('password')]);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get('/admin/enrollments');
        $response->assertStatus(200);
    }

    public function test_non_admin_cannot_access_enrollments(): void
    {
        $student = User::factory()->create(['password' => Hash::make('password')]);
        $student->assignRole('student');

        $response = $this->actingAs($student)->get('/admin/enrollments');
        $this->assertTrue(
            in_array($response->getStatusCode(), [403, 302]),
            'Expected 403 or redirect, got ' . $response->getStatusCode()
        );
    }
}
