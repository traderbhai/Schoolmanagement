<?php

namespace Tests\Feature;

use App\Models\LeaveApplication;
use App\Models\Department;
use App\Models\Program;
use App\Models\RoleProgramAssignment;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentLeaveWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function studentFixture(): array
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $user = User::factory()->create(['name' => 'Leave Workflow Student']);
        $user->assignRole('student');
        $student = Student::factory()->create(['user_id' => $user->id]);

        return compact('user', 'student');
    }

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_student_can_submit_leave_with_computed_days(): void
    {
        $fixture = $this->studentFixture();

        $this->actingAs($fixture['user'])
            ->post(route('student.leave.store'), [
                'from_date' => now()->addDays(2)->toDateString(),
                'to_date' => now()->addDays(4)->toDateString(),
                'reason' => 'Medical appointment',
                'description' => 'Doctor advised rest for a few days.',
            ])
            ->assertRedirect(route('student.leave.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('leave_applications', [
            'student_id' => $fixture['student']->id,
            'reason' => 'Medical appointment',
            'days' => 3,
            'status' => 'pending',
        ]);
    }

    public function test_student_cannot_submit_overlapping_open_leave_request(): void
    {
        $fixture = $this->studentFixture();
        LeaveApplication::create([
            'student_id' => $fixture['student']->id,
            'leave_type' => 'medical',
            'from_date' => now()->addDays(5)->toDateString(),
            'to_date' => now()->addDays(7)->toDateString(),
            'days' => 3,
            'reason' => 'Existing medical leave',
            'status' => 'pending',
        ]);

        $this->actingAs($fixture['user'])
            ->from(route('student.leave.create'))
            ->post(route('student.leave.store'), [
                'from_date' => now()->addDays(6)->toDateString(),
                'to_date' => now()->addDays(8)->toDateString(),
                'reason' => 'Overlapping request',
                'description' => 'This overlaps the existing request.',
            ])
            ->assertRedirect(route('student.leave.create'))
            ->assertSessionHasErrors('from_date');

        $this->assertSame(1, LeaveApplication::where('student_id', $fixture['student']->id)->count());
    }

    public function test_student_can_submit_new_leave_when_overlapping_history_is_rejected(): void
    {
        $fixture = $this->studentFixture();
        LeaveApplication::create([
            'student_id' => $fixture['student']->id,
            'leave_type' => 'medical',
            'from_date' => now()->addDays(5)->toDateString(),
            'to_date' => now()->addDays(7)->toDateString(),
            'days' => 3,
            'reason' => 'Rejected historical leave',
            'status' => 'rejected',
        ]);

        $this->actingAs($fixture['user'])
            ->post(route('student.leave.store'), [
                'from_date' => now()->addDays(6)->toDateString(),
                'to_date' => now()->addDays(8)->toDateString(),
                'reason' => 'Fresh request after rejection',
                'description' => 'The earlier request was rejected.',
            ])
            ->assertRedirect(route('student.leave.index'));

        $this->assertSame(2, LeaveApplication::where('student_id', $fixture['student']->id)->count());
        $this->assertDatabaseHas('leave_applications', [
            'student_id' => $fixture['student']->id,
            'reason' => 'Fresh request after rejection',
            'status' => 'pending',
        ]);
    }

    public function test_teacher_cannot_submit_overlapping_open_leave_request(): void
    {
        $teacherUser = $this->userWithRole('teacher');
        $teacher = Teacher::factory()->create(['user_id' => $teacherUser->id]);

        LeaveApplication::create([
            'teacher_id' => $teacher->id,
            'leave_type' => 'casual',
            'from_date' => now()->addDays(5)->toDateString(),
            'to_date' => now()->addDays(6)->toDateString(),
            'days' => 2,
            'reason' => 'Existing leave',
            'status' => 'approved',
        ]);

        $this->actingAs($teacherUser)
            ->from(route('teacher.leaves.create'))
            ->post(route('teacher.leaves.store'), [
                'leave_type' => 'medical',
                'from_date' => now()->addDays(6)->toDateString(),
                'to_date' => now()->addDays(7)->toDateString(),
                'reason' => 'Overlapping leave',
            ])
            ->assertRedirect(route('teacher.leaves.create'))
            ->assertSessionHasErrors('from_date');

        $this->assertSame(1, LeaveApplication::where('teacher_id', $teacher->id)->count());
    }

    public function test_admin_cannot_reapprove_reject_or_delete_reviewed_leave_history(): void
    {
        $admin = $this->userWithRole('admin');
        $teacher = Teacher::factory()->create();
        $leave = LeaveApplication::create([
            'teacher_id' => $teacher->id,
            'leave_type' => 'casual',
            'from_date' => now()->addDays(3)->toDateString(),
            'to_date' => now()->addDays(3)->toDateString(),
            'days' => 1,
            'reason' => 'Reviewed leave',
            'status' => 'approved',
            'approved_by' => $admin->id,
            'approved_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->from(route('admin.leaves.show', $leave))
            ->patch(route('admin.leaves.approve', $leave), ['admin_remarks' => 'Changing history'])
            ->assertRedirect(route('admin.leaves.show', $leave))
            ->assertSessionHas('error');

        $this->actingAs($admin)
            ->from(route('admin.leaves.show', $leave))
            ->patch(route('admin.leaves.reject', $leave), ['admin_remarks' => 'Changing history'])
            ->assertRedirect(route('admin.leaves.show', $leave))
            ->assertSessionHas('error');

        $this->actingAs($admin)
            ->from(route('admin.leaves.show', $leave))
            ->delete(route('admin.leaves.destroy', $leave))
            ->assertRedirect(route('admin.leaves.show', $leave))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('leave_applications', [
            'id' => $leave->id,
            'status' => 'approved',
        ]);
    }

    public function test_hod_cannot_review_non_pending_leave_history(): void
    {
        $department = Department::factory()->create();
        $program = Program::factory()->create(['department_id' => $department->id]);
        $student = Student::factory()->create([
            'program_id' => $program->id,
            'department_id' => $department->id,
        ]);
        $hod = $this->userWithRole('hod');
        Teacher::factory()->create([
            'user_id' => $hod->id,
            'department_id' => $department->id,
        ]);
        $leave = LeaveApplication::create([
            'student_id' => $student->id,
            'leave_type' => 'medical',
            'from_date' => now()->addDays(4)->toDateString(),
            'to_date' => now()->addDays(4)->toDateString(),
            'days' => 1,
            'reason' => 'Already reviewed',
            'status' => 'approved',
            'reviewed_by' => $hod->id,
            'reviewed_at' => now()->subDay(),
        ]);

        $this->actingAs($hod)
            ->from(route('hod.leaves'))
            ->post(route('hod.leaves.review', $leave), [
                'action' => 'rejected',
                'remarks' => 'Changing history',
            ])
            ->assertRedirect(route('hod.leaves'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('leave_applications', [
            'id' => $leave->id,
            'status' => 'approved',
        ]);
    }

    public function test_program_chair_leave_review_is_scoped_and_pending_only(): void
    {
        $assignedProgram = Program::factory()->create();
        $otherProgram = Program::factory()->create();
        $chair = $this->userWithRole('program_chair');

        RoleProgramAssignment::create([
            'user_id' => $chair->id,
            'role_name' => 'program_chair',
            'program_id' => $assignedProgram->id,
            'is_active' => true,
            'assigned_by' => $chair->id,
            'assigned_at' => now(),
        ]);

        $foreignStudent = Student::factory()->create(['program_id' => $otherProgram->id]);
        $foreignLeave = LeaveApplication::create([
            'student_id' => $foreignStudent->id,
            'leave_type' => 'medical',
            'from_date' => now()->addDays(4)->toDateString(),
            'to_date' => now()->addDays(4)->toDateString(),
            'days' => 1,
            'reason' => 'Out of scope',
            'status' => 'pending',
        ]);

        $assignedStudent = Student::factory()->create(['program_id' => $assignedProgram->id]);
        $lockedLeave = LeaveApplication::create([
            'student_id' => $assignedStudent->id,
            'leave_type' => 'medical',
            'from_date' => now()->addDays(5)->toDateString(),
            'to_date' => now()->addDays(5)->toDateString(),
            'days' => 1,
            'reason' => 'Already reviewed',
            'status' => 'approved',
        ]);

        $this->actingAs($chair)
            ->post(route('chair.students.leaves.approve', $foreignLeave), ['remarks' => 'Out of scope'])
            ->assertForbidden();

        $this->actingAs($chair)
            ->from(route('chair.students.leaves'))
            ->post(route('chair.students.leaves.reject', $lockedLeave), ['remarks' => 'Changing history'])
            ->assertRedirect(route('chair.students.leaves'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('leave_applications', [
            'id' => $foreignLeave->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('leave_applications', [
            'id' => $lockedLeave->id,
            'status' => 'approved',
        ]);
    }
}
