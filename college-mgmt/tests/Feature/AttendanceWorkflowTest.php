<?php

namespace Tests\Feature;

use App\Models\{User, Student, Teacher, Program, Subject};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_view_mark_attendance_page(): void
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        $tUser   = User::factory()->create();
        $tUser->assignRole('teacher');
        Teacher::factory()->create(['user_id' => $tUser->id]);

        $response = $this->actingAs($tUser)->get(route('teacher.attendance.mark'));
        $response->assertStatus(200);
    }

    public function test_student_can_view_attendance(): void
    {
        $program = Program::factory()->create(['is_active' => true]);
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $sUser   = User::factory()->create();
        $sUser->assignRole('student');
        Student::factory()->create(['user_id' => $sUser->id, 'program_id' => $program->id]);

        $this->actingAs($sUser)->get(route('student.attendance'))->assertStatus(200);
    }

    public function test_student_with_low_attendance_sees_dashboard(): void
    {
        $program = Program::factory()->create(['is_active' => true]);
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $sUser   = User::factory()->create();
        $sUser->assignRole('student');
        Student::factory()->create(['user_id' => $sUser->id, 'program_id' => $program->id]);

        // Even with no attendance records, dashboard should load
        $this->actingAs($sUser)->get(route('student.dashboard'))->assertStatus(200);
    }
}
