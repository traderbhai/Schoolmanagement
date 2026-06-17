<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ParentProfile;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminRecordLifecycleIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_admin_student_delete_archives_profile_without_destroying_history_owner(): void
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $admin = $this->admin();
        $student = Student::factory()->create(['status' => 'active']);
        $student->user->assignRole('student');

        $this->actingAs($admin)
            ->delete(route('admin.students.destroy', $student))
            ->assertRedirect(route('admin.students.index'))
            ->assertSessionHas('success', 'Student archived. Academic, fee, attendance, and result history was preserved.');

        $student->refresh();

        $this->assertSame('inactive', $student->status);
        $this->assertDatabaseHas('users', ['id' => $student->user_id]);
        $this->assertDatabaseHas('students', ['id' => $student->id]);
        $this->assertFalse($student->user->fresh()->hasRole('student'));
        $this->assertTrue(ActivityLog::where('action', 'archived')->where('model_type', Student::class)->where('model_id', $student->id)->exists());
    }

    public function test_admin_teacher_delete_archives_profile_without_destroying_history_owner(): void
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        $admin = $this->admin();
        $teacher = Teacher::factory()->create(['status' => 'active']);
        $teacher->user->assignRole('teacher');

        $this->actingAs($admin)
            ->delete(route('admin.teachers.destroy', $teacher))
            ->assertRedirect(route('admin.teachers.index'))
            ->assertSessionHas('success', 'Teacher archived. Timetable, attendance, leave, and academic history was preserved.');

        $teacher->refresh();

        $this->assertSame('inactive', $teacher->status);
        $this->assertDatabaseHas('users', ['id' => $teacher->user_id]);
        $this->assertDatabaseHas('teachers', ['id' => $teacher->id]);
        $this->assertFalse($teacher->user->fresh()->hasRole('teacher'));
        $this->assertTrue(ActivityLog::where('action', 'archived')->where('model_type', Teacher::class)->where('model_id', $teacher->id)->exists());
    }

    public function test_admin_parent_delete_archives_profile_without_destroying_student_linkage_history(): void
    {
        Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);
        $admin = $this->admin();
        $student = Student::factory()->create(['status' => 'active']);
        $parentUser = User::factory()->create();
        $parentUser->assignRole('parent');
        $parent = ParentProfile::create([
            'user_id' => $parentUser->id,
            'relation' => 'father',
            'phone' => '9999999999',
            'occupation' => 'Engineer',
            'annual_income' => '600000',
            'address' => 'Demo address',
        ]);
        $parent->students()->attach($student->id);

        $this->actingAs($admin)
            ->delete(route('admin.parents.destroy', $parent))
            ->assertRedirect(route('admin.parents.index'))
            ->assertSessionHas('success', 'Parent archived. Student linkage and portal history was preserved.');

        $this->assertDatabaseHas('users', ['id' => $parentUser->id]);
        $this->assertSoftDeleted('parents', ['id' => $parent->id]);
        $this->assertDatabaseHas('parent_student', [
            'parent_id' => $parent->id,
            'student_id' => $student->id,
        ]);
        $this->assertFalse($parentUser->fresh()->hasRole('parent'));
        $this->assertTrue(ParentProfile::withTrashed()->whereKey($parent->id)->exists());
        $this->assertTrue(DB::table('parent_student')->where('parent_id', $parent->id)->where('student_id', $student->id)->exists());
        $this->assertTrue(ActivityLog::where('action', 'archived')->where('model_type', ParentProfile::class)->where('model_id', $parent->id)->exists());
    }
}
