<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
    }

    public function test_teacher_can_access_mark_attendance_page(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $user->assignRole('teacher');
        $dept = Department::create(['name' => 'Test Dept', 'code' => 'TD', 'head_name' => 'Dr. Test']);
        Teacher::create([
            'user_id'         => $user->id,
            'department_id'   => $dept->id,
            'employee_id'     => 'T001',
            'designation'     => 'Lecturer',
            'date_of_joining' => now()->toDateString(),
        ]);

        $response = $this->actingAs($user)->get('/teacher/attendance/mark');
        $response->assertStatus(200);
    }

    public function test_student_cannot_access_teacher_attendance(): void
    {
        $student = User::factory()->create(['password' => Hash::make('password')]);
        $student->assignRole('student');

        $response = $this->actingAs($student)->get('/teacher/attendance/mark');
        $this->assertTrue(
            in_array($response->getStatusCode(), [403, 302]),
            'Expected 403 or redirect, got ' . $response->getStatusCode()
        );
    }
}
