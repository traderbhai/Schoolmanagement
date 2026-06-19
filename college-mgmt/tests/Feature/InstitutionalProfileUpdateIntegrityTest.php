<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InstitutionalProfileUpdateIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_student_profile_update_is_locked(): void
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $user = User::factory()->create(['name' => 'Archived Student User']);
        $user->assignRole('student');
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'status' => 'inactive',
            'phone' => '1111111111',
            'address' => 'Archived address',
            'guardian_name' => 'Original Guardian',
        ]);

        $this->actingAs($user)
            ->from(route('student.profile'))
            ->patch(route('student.profile.update'), [
                'name' => 'Rewritten Archived Student',
                'phone' => '9999999999',
                'address' => 'Mutated archived address',
                'guardian_name' => 'Changed Guardian',
                'guardian_phone' => '8888888888',
            ])
            ->assertRedirect(route('student.profile'))
            ->assertSessionHas('error', 'Profile updates are locked because this student profile is not active.');

        $this->assertSame('Archived Student User', $user->fresh()->name);
        $student->refresh();
        $this->assertSame('1111111111', $student->phone);
        $this->assertSame('Archived address', $student->address);
        $this->assertSame('Original Guardian', $student->guardian_name);
    }

    public function test_inactive_teacher_profile_update_is_locked(): void
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('teacher');
        $teacher = Teacher::factory()->create([
            'user_id' => $user->id,
            'status' => 'inactive',
            'designation' => 'Assistant Professor',
            'phone' => '2222222222',
            'specialization' => 'Finance',
            'qualification' => 'MBA',
        ]);

        $this->actingAs($user)
            ->from(route('teacher.profile'))
            ->put(route('teacher.profile.update'), [
                'designation' => 'Professor',
                'phone' => '9999999999',
                'specialization' => 'Marketing',
                'qualification' => 'PhD',
            ])
            ->assertRedirect(route('teacher.profile'))
            ->assertSessionHas('error', 'Profile updates are locked because this teacher profile is not active.');

        $teacher->refresh();
        $this->assertSame('Assistant Professor', $teacher->designation);
        $this->assertSame('2222222222', $teacher->phone);
        $this->assertSame('Finance', $teacher->specialization);
        $this->assertSame('MBA', $teacher->qualification);
    }

    public function test_active_student_and_teacher_profiles_remain_editable(): void
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $studentUser = User::factory()->create(['name' => 'Active Student User']);
        $studentUser->assignRole('student');
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'status' => 'active',
            'phone' => '1234567890',
        ]);

        $teacherUser = User::factory()->create();
        $teacherUser->assignRole('teacher');
        $teacher = Teacher::factory()->create([
            'user_id' => $teacherUser->id,
            'status' => 'active',
            'designation' => 'Lecturer',
        ]);

        $this->actingAs($studentUser)
            ->patch(route('student.profile.update'), [
                'name' => 'Active Student Updated',
                'phone' => '7777777777',
                'address' => 'Updated student address',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($teacherUser)
            ->put(route('teacher.profile.update'), [
                'designation' => 'Senior Lecturer',
                'phone' => '6666666666',
                'specialization' => 'Analytics',
                'qualification' => 'PhD',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('Active Student Updated', $studentUser->fresh()->name);
        $this->assertSame('7777777777', $student->fresh()->phone);
        $teacher->refresh();
        $this->assertSame('Senior Lecturer', $teacher->designation);
        $this->assertSame('6666666666', $teacher->phone);
    }
}
