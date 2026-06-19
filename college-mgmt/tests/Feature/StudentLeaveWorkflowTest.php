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

    public function test_inactive_student_can_view_leave_history_but_cannot_submit_new_leave(): void
    {
        $fixture = $this->studentFixture();
        $fixture['student']->update(['status' => 'inactive']);

        LeaveApplication::create([
            'student_id' => $fixture['student']->id,
            'leave_type' => 'student',
            'from_date' => now()->addDays(5)->toDateString(),
            'to_date' => now()->addDays(6)->toDateString(),
            'days' => 2,
            'reason' => 'Historical leave',
            'status' => 'approved',
        ]);

        $this->actingAs($fixture['user'])
            ->get(route('student.leave.index'))
            ->assertOk()
            ->assertSee('Historical leave')
            ->assertSee('Active students only')
            ->assertDontSee('Apply for Leave');

        $this->actingAs($fixture['user'])
            ->get(route('student.leave.create'))
            ->assertRedirect(route('student.leave.index'))
            ->assertSessionHas('error', 'Leave applications can be submitted only by active students.');

        $this->actingAs($fixture['user'])
            ->post(route('student.leave.store'), [
                'from_date' => now()->addDays(8)->toDateString(),
                'to_date' => now()->addDays(9)->toDateString(),
                'reason' => 'Inactive direct leave',
                'description' => 'Should not create a new leave request.',
            ])
            ->assertRedirect(route('student.leave.index'))
            ->assertSessionHas('error', 'Leave applications can be submitted only by active students.');

        $this->assertDatabaseMissing('leave_applications', [
            'student_id' => $fixture['student']->id,
            'reason' => 'Inactive direct leave',
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

    public function test_inactive_teacher_can_view_leave_history_but_cannot_submit_or_cancel_leave(): void
    {
        $teacherUser = $this->userWithRole('teacher');
        $teacher = Teacher::factory()->create([
            'user_id' => $teacherUser->id,
            'status' => 'inactive',
        ]);

        $leave = LeaveApplication::create([
            'teacher_id' => $teacher->id,
            'leave_type' => 'casual',
            'from_date' => now()->addDays(5)->toDateString(),
            'to_date' => now()->addDays(6)->toDateString(),
            'days' => 2,
            'reason' => 'Historical teacher leave',
            'status' => 'pending',
        ]);

        $this->actingAs($teacherUser)
            ->get(route('teacher.leaves.index'))
            ->assertOk()
            ->assertSee('Historical teacher leave')
            ->assertSee('Active teachers only')
            ->assertDontSee('Apply for Leave');

        $this->actingAs($teacherUser)
            ->get(route('teacher.leaves.create'))
            ->assertRedirect(route('teacher.leaves.index'))
            ->assertSessionHas('error', 'Leave applications can be submitted only by active teachers.');

        $this->actingAs($teacherUser)
            ->post(route('teacher.leaves.store'), [
                'leave_type' => 'medical',
                'from_date' => now()->addDays(8)->toDateString(),
                'to_date' => now()->addDays(9)->toDateString(),
                'reason' => 'Inactive teacher direct leave',
            ])
            ->assertRedirect(route('teacher.leaves.index'))
            ->assertSessionHas('error', 'Leave applications can be submitted only by active teachers.');

        $this->actingAs($teacherUser)
            ->delete(route('teacher.leaves.destroy', $leave))
            ->assertRedirect()
            ->assertSessionHas('error', 'Cannot cancel this leave application.');

        $this->assertDatabaseMissing('leave_applications', [
            'teacher_id' => $teacher->id,
            'reason' => 'Inactive teacher direct leave',
        ]);
        $this->assertDatabaseHas('leave_applications', [
            'id' => $leave->id,
            'status' => 'pending',
        ]);
    }

    public function test_active_teacher_cancel_preserves_leave_history(): void
    {
        $teacherUser = $this->userWithRole('teacher');
        $teacher = Teacher::factory()->create([
            'user_id' => $teacherUser->id,
            'status' => 'active',
        ]);
        $leave = LeaveApplication::create([
            'teacher_id' => $teacher->id,
            'leave_type' => 'casual',
            'from_date' => now()->addDays(5)->toDateString(),
            'to_date' => now()->addDays(5)->toDateString(),
            'days' => 1,
            'reason' => 'Teacher cancelled pending leave',
            'status' => 'pending',
        ]);

        $this->actingAs($teacherUser)
            ->delete(route('teacher.leaves.destroy', $leave))
            ->assertRedirect()
            ->assertSessionHas('success', 'Leave application cancelled.');

        $this->assertDatabaseHas('leave_applications', [
            'id' => $leave->id,
            'teacher_id' => $teacher->id,
            'status' => 'rejected',
            'reviewed_by' => $teacherUser->id,
            'admin_remarks' => 'Cancelled by teacher before review.',
        ]);
        $this->assertNotNull($leave->fresh()->reviewed_at);
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

    public function test_admin_pending_leave_delete_preserves_history(): void
    {
        $admin = $this->userWithRole('admin');
        $teacher = Teacher::factory()->create();
        $leave = LeaveApplication::create([
            'teacher_id' => $teacher->id,
            'leave_type' => 'casual',
            'from_date' => now()->addDays(3)->toDateString(),
            'to_date' => now()->addDays(3)->toDateString(),
            'days' => 1,
            'reason' => 'Pending leave to cancel',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.leaves.destroy', $leave))
            ->assertRedirect(route('admin.leaves.index'))
            ->assertSessionHas('success', 'Leave application cancelled and retained for audit.');

        $this->assertDatabaseHas('leave_applications', [
            'id' => $leave->id,
            'teacher_id' => $teacher->id,
            'status' => 'rejected',
            'approved_by' => $admin->id,
            'admin_remarks' => 'Cancelled by admin before review.',
        ]);
        $this->assertNotNull($leave->fresh()->approved_at);
    }

    public function test_admin_leave_pages_render_student_leave_requester(): void
    {
        $admin = $this->userWithRole('admin');
        $studentUser = User::factory()->create(['name' => 'Student Leave Requester']);
        $student = Student::factory()->create(['user_id' => $studentUser->id]);
        $leave = LeaveApplication::create([
            'student_id' => $student->id,
            'leave_type' => 'medical',
            'from_date' => now()->addDays(3)->toDateString(),
            'to_date' => now()->addDays(4)->toDateString(),
            'days' => 2,
            'reason' => 'Student leave visible to admin',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.leaves.index'))
            ->assertOk()
            ->assertSee('Student Leave Requester')
            ->assertSee('Student')
            ->assertSee('Student leave visible to admin');

        $this->actingAs($admin)
            ->get(route('admin.leaves.show', $leave))
            ->assertOk()
            ->assertSee('Requester')
            ->assertSee('Student Leave Requester')
            ->assertSee('Requester Type')
            ->assertSee('Student');
    }

    public function test_program_chair_cannot_use_global_admin_leave_queue(): void
    {
        $chair = $this->userWithRole('program_chair');
        $student = Student::factory()->create();
        $pendingLeave = LeaveApplication::create([
            'student_id' => $student->id,
            'leave_type' => 'medical',
            'from_date' => now()->addDays(3)->toDateString(),
            'to_date' => now()->addDays(3)->toDateString(),
            'days' => 1,
            'reason' => 'Global admin queue should be protected',
            'status' => 'pending',
        ]);

        $this->actingAs($chair)
            ->get(route('admin.leaves.index'))
            ->assertForbidden();

        $this->actingAs($chair)
            ->get(route('admin.leaves.show', $pendingLeave))
            ->assertForbidden();

        $this->actingAs($chair)
            ->patch(route('admin.leaves.approve', $pendingLeave), [
                'admin_remarks' => 'Unauthorized approval.',
            ])
            ->assertForbidden();

        $this->actingAs($chair)
            ->patch(route('admin.leaves.reject', $pendingLeave), [
                'admin_remarks' => 'Unauthorized rejection.',
            ])
            ->assertForbidden();

        $this->actingAs($chair)
            ->delete(route('admin.leaves.destroy', $pendingLeave))
            ->assertForbidden();

        $pendingLeave->refresh();
        $this->assertSame('pending', $pendingLeave->status);
        $this->assertNull($pendingLeave->approved_by);
        $this->assertNull($pendingLeave->approved_at);
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

    public function test_hod_can_review_pending_leave_with_schema_backed_remarks(): void
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
            'reason' => 'Pending HOD review',
            'status' => 'pending',
        ]);

        $this->actingAs($hod)
            ->from(route('hod.leaves'))
            ->post(route('hod.leaves.review', $leave), [
                'action' => 'approved',
                'remarks' => 'HOD verified attendance impact.',
            ])
            ->assertRedirect(route('hod.leaves'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('leave_applications', [
            'id' => $leave->id,
            'status' => 'approved',
            'reviewed_by' => $hod->id,
            'admin_remarks' => 'HOD verified attendance impact.',
        ]);
        $this->assertNotNull($leave->fresh()->reviewed_at);
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

    public function test_program_chair_can_review_pending_leave_with_schema_backed_remarks(): void
    {
        $program = Program::factory()->create();
        $chair = $this->userWithRole('program_chair');
        RoleProgramAssignment::create([
            'user_id' => $chair->id,
            'role_name' => 'program_chair',
            'program_id' => $program->id,
            'is_active' => true,
            'assigned_by' => $chair->id,
            'assigned_at' => now(),
        ]);
        $student = Student::factory()->create(['program_id' => $program->id]);
        $leave = LeaveApplication::create([
            'student_id' => $student->id,
            'leave_type' => 'medical',
            'from_date' => now()->addDays(4)->toDateString(),
            'to_date' => now()->addDays(4)->toDateString(),
            'days' => 1,
            'reason' => 'Pending PMC review',
            'status' => 'pending',
        ]);

        $this->actingAs($chair)
            ->from(route('chair.students.leaves'))
            ->post(route('chair.students.leaves.reject', $leave), [
                'remarks' => 'Program office needs revised documents.',
            ])
            ->assertRedirect(route('chair.students.leaves'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('leave_applications', [
            'id' => $leave->id,
            'status' => 'rejected',
            'reviewed_by' => $chair->id,
            'admin_remarks' => 'Program office needs revised documents.',
        ]);
        $this->assertNotNull($leave->fresh()->reviewed_at);
    }
}
