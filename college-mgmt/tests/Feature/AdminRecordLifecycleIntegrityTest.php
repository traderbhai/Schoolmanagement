<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\Department;
use App\Models\ParentProfile;
use App\Models\Program;
use App\Models\Specialization;
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

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_broad_academic_role_cannot_manage_global_student_master_records(): void
    {
        $programChair = $this->userWithRole('program_chair');
        $student = Student::factory()->create(['status' => 'active']);

        $this->actingAs($programChair)->get(route('admin.students.index'))->assertForbidden();
        $this->actingAs($programChair)->get(route('admin.students.create'))->assertForbidden();
        $this->actingAs($programChair)->get(route('admin.students.show', $student))->assertForbidden();
        $this->actingAs($programChair)->get(route('admin.students.edit', $student))->assertForbidden();

        $this->actingAs($programChair)
            ->post(route('admin.students.store'), [
                'name' => 'Unauthorized Student',
                'email' => 'unauthorized-student@example.test',
                'password' => 'password123',
                'department_id' => $student->department_id,
                'course_id' => $student->course_id,
                'enrollment_number' => 'UNAUTH-STU',
            ])
            ->assertForbidden();

        $this->actingAs($programChair)
            ->put(route('admin.students.update', $student), [
                'name' => 'Changed Student',
                'email' => $student->user->email,
                'department_id' => $student->department_id,
                'course_id' => $student->course_id,
                'status' => 'active',
            ])
            ->assertForbidden();

        $this->actingAs($programChair)->delete(route('admin.students.destroy', $student))->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'unauthorized-student@example.test']);
        $this->assertSame('active', $student->fresh()->status);
        $this->assertNotSame('Changed Student', $student->user->fresh()->name);
    }

    public function test_broad_academic_role_cannot_manage_global_teacher_master_records(): void
    {
        $programChair = $this->userWithRole('program_chair');
        $teacher = Teacher::factory()->create(['status' => 'active']);

        $this->actingAs($programChair)->get(route('admin.teachers.index'))->assertForbidden();
        $this->actingAs($programChair)->get(route('admin.teachers.create'))->assertForbidden();
        $this->actingAs($programChair)->get(route('admin.teachers.show', $teacher))->assertForbidden();
        $this->actingAs($programChair)->get(route('admin.teachers.edit', $teacher))->assertForbidden();

        $this->actingAs($programChair)
            ->post(route('admin.teachers.store'), [
                'name' => 'Unauthorized Teacher',
                'email' => 'unauthorized-teacher@example.test',
                'password' => 'password123',
                'department_id' => $teacher->department_id,
                'employee_id' => 'UNAUTH-TCH',
                'employment_type' => 'full_time',
            ])
            ->assertForbidden();

        $this->actingAs($programChair)
            ->put(route('admin.teachers.update', $teacher), [
                'name' => 'Changed Teacher',
                'email' => $teacher->user->email,
                'department_id' => $teacher->department_id,
                'designation' => $teacher->designation,
                'qualification' => $teacher->qualification,
                'specialization' => $teacher->specialization,
                'phone' => $teacher->phone,
                'status' => 'active',
            ])
            ->assertForbidden();

        $this->actingAs($programChair)->delete(route('admin.teachers.destroy', $teacher))->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'unauthorized-teacher@example.test']);
        $this->assertSame('active', $teacher->fresh()->status);
        $this->assertNotSame('Changed Teacher', $teacher->user->fresh()->name);
    }

    public function test_dean_keeps_global_student_and_teacher_master_access(): void
    {
        $dean = $this->userWithRole('dean_academics');

        $this->actingAs($dean)->get(route('admin.students.index'))->assertOk();
        $this->actingAs($dean)->get(route('admin.teachers.index'))->assertOk();
    }

    public function test_broad_academic_role_cannot_manage_global_parent_master_records(): void
    {
        $programChair = $this->userWithRole('program_chair');
        $student = Student::factory()->create(['status' => 'active']);
        $parentUser = User::factory()->create(['name' => 'Original Parent', 'email' => 'original-parent@example.test']);
        Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);
        $parentUser->assignRole('parent');
        $parent = ParentProfile::create([
            'user_id' => $parentUser->id,
            'relation' => 'guardian',
            'phone' => '9999999999',
        ]);
        $parent->students()->attach($student->id);

        $this->actingAs($programChair)->get(route('admin.parents.index'))->assertForbidden();
        $this->actingAs($programChair)->get(route('admin.parents.create'))->assertForbidden();
        $this->actingAs($programChair)->get(route('admin.parents.show', $parent))->assertForbidden();
        $this->actingAs($programChair)->get(route('admin.parents.edit', $parent))->assertForbidden();

        $this->actingAs($programChair)
            ->post(route('admin.parents.store'), [
                'name' => 'Unauthorized Parent',
                'email' => 'unauthorized-parent@example.test',
                'password' => 'password123',
                'relation' => 'guardian',
                'student_ids' => [$student->id],
            ])
            ->assertForbidden();

        $this->actingAs($programChair)
            ->put(route('admin.parents.update', $parent), [
                'name' => 'Changed Parent',
                'email' => 'changed-parent@example.test',
                'relation' => 'father',
                'phone' => '1111111111',
                'student_ids' => [$student->id],
            ])
            ->assertForbidden();

        $this->actingAs($programChair)->delete(route('admin.parents.destroy', $parent))->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'unauthorized-parent@example.test']);
        $this->assertSame('Original Parent', $parentUser->fresh()->name);
        $this->assertSame('guardian', $parent->fresh()->relation);
        $this->assertFalse(ParentProfile::withTrashed()->findOrFail($parent->id)->trashed());
        $this->assertTrue($parentUser->fresh()->hasRole('parent'));
    }

    public function test_dean_keeps_global_parent_master_access(): void
    {
        $dean = $this->userWithRole('dean_academics');

        $this->actingAs($dean)->get(route('admin.parents.index'))->assertOk();
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

    public function test_admin_student_edit_cannot_bypass_archive_or_rewrite_archived_profile(): void
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $admin = $this->admin();
        $student = Student::factory()->create([
            'status' => 'active',
            'phone' => '1111111111',
        ]);
        $student->user->assignRole('student');

        $payload = [
            'name' => $student->user->name,
            'email' => $student->user->email,
            'department_id' => $student->department_id,
            'course_id' => $student->course_id,
            'status' => 'inactive',
        ];

        $this->actingAs($admin)
            ->from(route('admin.students.edit', $student))
            ->put(route('admin.students.update', $student), $payload)
            ->assertRedirect(route('admin.students.edit', $student))
            ->assertSessionHas('error', 'Use the archive action to deactivate a student so roles and audit history are preserved.');

        $student->refresh();
        $this->assertSame('active', $student->status);
        $this->assertTrue($student->user->fresh()->hasRole('student'));
        $this->assertFalse(ActivityLog::where('action', 'archived')->where('model_type', Student::class)->where('model_id', $student->id)->exists());

        $student->update(['status' => 'inactive']);
        $this->actingAs($admin)
            ->from(route('admin.students.edit', $student))
            ->put(route('admin.students.update', $student), array_merge($payload, [
                'name' => 'Rewritten Archived Student',
                'phone' => '9999999999',
                'status' => 'active',
            ]))
            ->assertRedirect(route('admin.students.edit', $student))
            ->assertSessionHas('error', 'Archived student profiles are locked. Use a dedicated audited reactivation or correction workflow instead of ordinary edit.');

        $student->refresh();
        $this->assertSame('inactive', $student->status);
        $this->assertSame('1111111111', $student->phone);
        $this->assertNotSame('Rewritten Archived Student', $student->user->fresh()->name);
    }

    public function test_admin_student_create_and_update_require_active_department_and_course(): void
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $admin = $this->admin();
        $activeDepartment = Department::factory()->create(['is_active' => true]);
        $inactiveDepartment = Department::factory()->create(['is_active' => false]);
        $activeCourse = Course::factory()->create(['department_id' => $activeDepartment->id, 'is_active' => true]);
        $inactiveCourse = Course::factory()->create(['department_id' => $activeDepartment->id, 'is_active' => false]);

        $this->actingAs($admin)
            ->from(route('admin.students.create'))
            ->post(route('admin.students.store'), [
                'name' => 'Inactive Department Student',
                'email' => 'inactive-dept-student@example.test',
                'password' => 'password123',
                'department_id' => $inactiveDepartment->id,
                'course_id' => $activeCourse->id,
                'enrollment_number' => 'INACTIVE-DEPT-STU',
            ])
            ->assertRedirect(route('admin.students.create'))
            ->assertSessionHasErrors('department_id');

        $this->actingAs($admin)
            ->from(route('admin.students.create'))
            ->post(route('admin.students.store'), [
                'name' => 'Inactive Course Student',
                'email' => 'inactive-course-student@example.test',
                'password' => 'password123',
                'department_id' => $activeDepartment->id,
                'course_id' => $inactiveCourse->id,
                'enrollment_number' => 'INACTIVE-COURSE-STU',
            ])
            ->assertRedirect(route('admin.students.create'))
            ->assertSessionHasErrors('course_id');

        $student = Student::factory()->create([
            'department_id' => $activeDepartment->id,
            'course_id' => $activeCourse->id,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.students.edit', $student))
            ->put(route('admin.students.update', $student), [
                'name' => $student->user->name,
                'email' => $student->user->email,
                'department_id' => $activeDepartment->id,
                'course_id' => $inactiveCourse->id,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.students.edit', $student))
            ->assertSessionHasErrors('course_id');

        $this->assertDatabaseMissing('users', ['email' => 'inactive-dept-student@example.test']);
        $this->assertDatabaseMissing('users', ['email' => 'inactive-course-student@example.test']);
        $this->assertSame($activeCourse->id, $student->fresh()->course_id);
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

    public function test_admin_teacher_edit_cannot_bypass_archive_or_rewrite_archived_profile(): void
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        $admin = $this->admin();
        $teacher = Teacher::factory()->create([
            'status' => 'active',
            'designation' => 'Assistant Professor',
            'phone' => '2222222222',
        ]);
        $teacher->user->assignRole('teacher');

        $payload = [
            'name' => $teacher->user->name,
            'email' => $teacher->user->email,
            'department_id' => $teacher->department_id,
            'designation' => $teacher->designation,
            'qualification' => $teacher->qualification,
            'specialization' => $teacher->specialization,
            'phone' => $teacher->phone,
            'status' => 'inactive',
        ];

        $this->actingAs($admin)
            ->from(route('admin.teachers.edit', $teacher))
            ->put(route('admin.teachers.update', $teacher), $payload)
            ->assertRedirect(route('admin.teachers.edit', $teacher))
            ->assertSessionHas('error', 'Use the archive action to deactivate a teacher so roles and audit history are preserved.');

        $teacher->refresh();
        $this->assertSame('active', $teacher->status);
        $this->assertTrue($teacher->user->fresh()->hasRole('teacher'));
        $this->assertFalse(ActivityLog::where('action', 'archived')->where('model_type', Teacher::class)->where('model_id', $teacher->id)->exists());

        $teacher->update(['status' => 'inactive']);
        $this->actingAs($admin)
            ->from(route('admin.teachers.edit', $teacher))
            ->put(route('admin.teachers.update', $teacher), array_merge($payload, [
                'name' => 'Rewritten Archived Teacher',
                'designation' => 'Professor',
                'phone' => '9999999999',
                'status' => 'active',
            ]))
            ->assertRedirect(route('admin.teachers.edit', $teacher))
            ->assertSessionHas('error', 'Archived teacher profiles are locked. Use a dedicated audited reactivation or correction workflow instead of ordinary edit.');

        $teacher->refresh();
        $this->assertSame('inactive', $teacher->status);
        $this->assertSame('Assistant Professor', $teacher->designation);
        $this->assertSame('2222222222', $teacher->phone);
        $this->assertNotSame('Rewritten Archived Teacher', $teacher->user->fresh()->name);
    }

    public function test_admin_teacher_create_and_update_require_active_department(): void
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        $admin = $this->admin();
        $activeDepartment = Department::factory()->create(['is_active' => true]);
        $inactiveDepartment = Department::factory()->create(['is_active' => false]);

        $this->actingAs($admin)
            ->from(route('admin.teachers.create'))
            ->post(route('admin.teachers.store'), [
                'name' => 'Inactive Department Teacher',
                'email' => 'inactive-dept-teacher@example.test',
                'password' => 'password123',
                'department_id' => $inactiveDepartment->id,
                'employee_id' => 'INACTIVE-DEPT-TCH',
                'employment_type' => 'full_time',
            ])
            ->assertRedirect(route('admin.teachers.create'))
            ->assertSessionHasErrors('department_id');

        $teacher = Teacher::factory()->create([
            'department_id' => $activeDepartment->id,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.teachers.edit', $teacher))
            ->put(route('admin.teachers.update', $teacher), [
                'name' => $teacher->user->name,
                'email' => $teacher->user->email,
                'department_id' => $inactiveDepartment->id,
                'designation' => $teacher->designation,
                'qualification' => $teacher->qualification,
                'specialization' => $teacher->specialization,
                'phone' => $teacher->phone,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.teachers.edit', $teacher))
            ->assertSessionHasErrors('department_id');

        $this->assertDatabaseMissing('users', ['email' => 'inactive-dept-teacher@example.test']);
        $this->assertSame($activeDepartment->id, $teacher->fresh()->department_id);
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

    public function test_admin_parent_create_rejects_new_links_to_inactive_students(): void
    {
        Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);
        $admin = $this->admin();
        $activeStudent = Student::factory()->create(['status' => 'active']);
        $inactiveStudent = Student::factory()->create(['status' => 'inactive']);

        $this->actingAs($admin)
            ->from(route('admin.parents.create'))
            ->post(route('admin.parents.store'), [
                'name' => 'Inactive Link Parent',
                'email' => 'inactive-link-parent@example.test',
                'password' => 'password123',
                'relation' => 'guardian',
                'student_ids' => [$inactiveStudent->id],
            ])
            ->assertRedirect(route('admin.parents.create'))
            ->assertSessionHasErrors('student_ids.0');

        $this->assertDatabaseMissing('users', ['email' => 'inactive-link-parent@example.test']);
        $this->assertDatabaseMissing('parent_student', ['student_id' => $inactiveStudent->id]);

        $this->actingAs($admin)
            ->post(route('admin.parents.store'), [
                'name' => 'Active Link Parent',
                'email' => 'active-link-parent@example.test',
                'password' => 'password123',
                'relation' => 'guardian',
                'student_ids' => [$activeStudent->id],
            ])
            ->assertRedirect(route('admin.parents.index'))
            ->assertSessionHas('success');

        $parent = ParentProfile::whereHas('user', fn($query) => $query->where('email', 'active-link-parent@example.test'))->firstOrFail();
        $this->assertDatabaseHas('parent_student', [
            'parent_id' => $parent->id,
            'student_id' => $activeStudent->id,
        ]);
    }

    public function test_admin_parent_update_preserves_existing_inactive_link_but_blocks_new_inactive_link(): void
    {
        Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);
        $admin = $this->admin();
        $parentUser = User::factory()->create(['name' => 'Parent Link Owner', 'email' => 'parent-link-owner@example.test']);
        $parentUser->assignRole('parent');
        $existingInactiveStudent = Student::factory()->create(['status' => 'inactive']);
        $newInactiveStudent = Student::factory()->create(['status' => 'inactive']);
        $activeStudent = Student::factory()->create(['status' => 'active']);
        $parent = ParentProfile::create([
            'user_id' => $parentUser->id,
            'relation' => 'guardian',
            'phone' => '9999999999',
        ]);
        $parent->students()->attach($existingInactiveStudent->id);

        $this->actingAs($admin)
            ->from(route('admin.parents.edit', $parent))
            ->put(route('admin.parents.update', $parent), [
                'name' => 'Parent Link Owner',
                'email' => 'parent-link-owner@example.test',
                'relation' => 'guardian',
                'phone' => '9999999999',
                'student_ids' => [$existingInactiveStudent->id, $newInactiveStudent->id],
            ])
            ->assertRedirect(route('admin.parents.edit', $parent))
            ->assertSessionHasErrors('student_ids');

        $this->assertDatabaseHas('parent_student', [
            'parent_id' => $parent->id,
            'student_id' => $existingInactiveStudent->id,
        ]);
        $this->assertDatabaseMissing('parent_student', [
            'parent_id' => $parent->id,
            'student_id' => $newInactiveStudent->id,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.parents.update', $parent), [
                'name' => 'Parent Link Owner Updated',
                'email' => 'parent-link-owner@example.test',
                'relation' => 'guardian',
                'phone' => '9999999999',
                'student_ids' => [$existingInactiveStudent->id, $activeStudent->id],
            ])
            ->assertRedirect(route('admin.parents.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('parent_student', [
            'parent_id' => $parent->id,
            'student_id' => $existingInactiveStudent->id,
        ]);
        $this->assertDatabaseHas('parent_student', [
            'parent_id' => $parent->id,
            'student_id' => $activeStudent->id,
        ]);
        $this->assertSame('Parent Link Owner Updated', $parentUser->fresh()->name);
    }

    public function test_admin_cannot_delete_specialization_with_student_history(): void
    {
        $admin = $this->admin();
        $program = Program::factory()->create();
        $specialization = Specialization::create([
            'program_id' => $program->id,
            'name' => 'Business Analytics',
            'code' => 'BA-SPEC',
            'is_active' => true,
        ]);
        $student = Student::factory()->create([
            'program_id' => $program->id,
            'specialization_id' => $specialization->id,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.programs.show', $program))
            ->delete(route('admin.specializations.destroy', $specialization))
            ->assertRedirect(route('admin.programs.show', $program))
            ->assertSessionHas('error', 'Specializations assigned to students cannot be deleted. Deactivate or rename the specialization instead so student history is preserved.');

        $this->assertDatabaseHas('specializations', ['id' => $specialization->id]);
        $this->assertSame($specialization->id, $student->fresh()->specialization_id);
    }
}
