<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApprovalWorkflow;
use App\Models\Department;
use App\Models\Program;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Models\LeaveApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HodDashboardGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private function hodUser(?Department $department = null): User
    {
        Role::firstOrCreate(['name' => 'hod', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('hod');

        if ($department) {
            Teacher::factory()->create([
                'user_id' => $user->id,
                'department_id' => $department->id,
            ]);
        }

        return $user;
    }

    private function pendingApprovalFor(Program $program): ApprovalWorkflow
    {
        $applicant = Applicant::factory()->create(['program_id' => $program->id]);

        return ApprovalWorkflow::create([
            'approvable_type' => Applicant::class,
            'approvable_id' => $applicant->id,
            'approver_role' => 'hod',
            'status' => 'pending',
        ]);
    }

    public function test_hod_dashboard_requires_department_profile_for_hod_users(): void
    {
        $user = $this->hodUser();
        Student::factory()->count(2)->create();

        $this->actingAs($user)
            ->get(route('hod.dashboard'))
            ->assertStatus(200)
            ->assertSee('HOD Priority')
            ->assertSee('Department profile needed')
            ->assertSee('Your HOD account is not linked to a teacher department profile')
            ->assertSee('>0<', false);
    }

    public function test_hod_dashboard_and_approvals_are_scoped_to_department(): void
    {
        $department = Department::factory()->create(['name' => 'Computer Science']);
        $otherDepartment = Department::factory()->create(['name' => 'Mechanical']);
        $program = Program::factory()->create(['department_id' => $department->id, 'name' => 'BCA']);
        $otherProgram = Program::factory()->create(['department_id' => $otherDepartment->id, 'name' => 'BME']);
        $user = $this->hodUser($department);

        $this->pendingApprovalFor($program);
        $this->pendingApprovalFor($otherProgram);

        $this->actingAs($user)
            ->get(route('hod.dashboard'))
            ->assertStatus(200)
            ->assertSee('Review 1 department approval')
            ->assertSee('Open Approvals')
            ->assertSee(route('hod.approvals'), false);

        $this->actingAs($user)
            ->get(route('hod.approvals'))
            ->assertStatus(200)
            ->assertSee('BCA')
            ->assertDontSee('BME');
    }

    public function test_hod_cannot_approve_another_department_approval(): void
    {
        $department = Department::factory()->create();
        $otherDepartment = Department::factory()->create();
        $program = Program::factory()->create(['department_id' => $department->id]);
        $otherProgram = Program::factory()->create(['department_id' => $otherDepartment->id]);
        $user = $this->hodUser($department);
        $foreignApproval = $this->pendingApprovalFor($otherProgram);

        $this->pendingApprovalFor($program);

        $this->actingAs($user)
            ->post(route('hod.approve', $foreignApproval), ['remarks' => 'Approved'])
            ->assertForbidden();
    }

    public function test_hod_leave_review_modal_uses_named_route_action(): void
    {
        $department = Department::factory()->create(['name' => 'Computer Science']);
        $program = Program::factory()->create(['department_id' => $department->id]);
        $student = Student::factory()->create(['program_id' => $program->id]);
        $faculty = Teacher::factory()->create(['department_id' => $department->id]);
        $leave = LeaveApplication::create([
            'student_id' => $student->id,
            'teacher_id' => $faculty->id,
            'leave_type' => 'medical',
            'from_date' => now()->addDay()->toDateString(),
            'to_date' => now()->addDays(2)->toDateString(),
            'days' => 2,
            'reason' => 'Medical consultation.',
            'status' => 'pending',
        ]);
        $user = $this->hodUser($department);

        $this->actingAs($user)
            ->get(route('hod.leaves'))
            ->assertStatus(200)
            ->assertSee(route('hod.leaves.review', $leave), false)
            ->assertSee('data-review-action="approved"', false)
            ->assertSee('data-review-action="rejected"', false)
            ->assertSee('onclick="openReview(this)"', false);
    }
}
