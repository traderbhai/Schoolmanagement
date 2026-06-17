<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApprovalWorkflow;
use App\Models\Batch;
use App\Models\Program;
use App\Models\ProgramSubject;
use App\Models\RoleProgramAssignment;
use App\Models\Student;
use App\Models\StudentSubjectEnrollment;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProgramChairDashboardGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private function chairUser(?Program $program = null): User
    {
        Role::firstOrCreate(['name' => 'program_chair', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('program_chair');

        if ($program) {
            RoleProgramAssignment::create([
                'user_id' => $user->id,
                'role_name' => 'program_chair',
                'program_id' => $program->id,
                'is_active' => true,
                'assigned_by' => $user->id,
                'assigned_at' => now(),
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
            'approver_role' => 'program_chair',
            'status' => 'pending',
        ]);
    }

    public function test_dashboard_shows_assignment_needed_when_program_chair_has_no_program_scope(): void
    {
        $user = $this->chairUser();
        Student::factory()->count(2)->create();

        $this->actingAs($user)
            ->get(route('chair.dashboard'))
            ->assertStatus(200)
            ->assertSee('Program Chair Priority')
            ->assertSee('Program assignment needed')
            ->assertSee('No active program is assigned')
            ->assertSee('>0<', false);
    }

    public function test_dashboard_prioritizes_pending_approvals_for_assigned_program_only(): void
    {
        $assignedProgram = Program::factory()->create(['name' => 'Assigned Program']);
        $otherProgram = Program::factory()->create(['name' => 'Other Program']);
        $user = $this->chairUser($assignedProgram);

        $this->pendingApprovalFor($assignedProgram);
        $this->pendingApprovalFor($otherProgram);

        $this->actingAs($user)
            ->get(route('chair.dashboard'))
            ->assertStatus(200)
            ->assertSee('Review 1 pending approval')
            ->assertSee('Open Approvals')
            ->assertSee(route('chair.approvals'), false);

        $this->actingAs($user)
            ->get(route('chair.approvals'))
            ->assertStatus(200)
            ->assertSee('Assigned Program')
            ->assertDontSee('Other Program');
    }

    public function test_program_chair_cannot_approve_another_program_approval(): void
    {
        $assignedProgram = Program::factory()->create();
        $otherProgram = Program::factory()->create();
        $user = $this->chairUser($assignedProgram);
        $foreignApproval = $this->pendingApprovalFor($otherProgram);

        $this->actingAs($user)
            ->post(route('chair.approve', $foreignApproval), ['remarks' => 'Looks fine'])
            ->assertForbidden();
    }

    public function test_program_chair_cannot_reapprove_or_reject_finalized_approval(): void
    {
        $program = Program::factory()->create();
        $user = $this->chairUser($program);
        $approval = $this->pendingApprovalFor($program);

        $this->actingAs($user)
            ->post(route('chair.approve', $approval), ['remarks' => 'Approved once'])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('chair.reject', $approval->fresh()), ['rejection_reason' => 'Trying to reverse'])
            ->assertForbidden();

        $this->assertDatabaseHas('approval_workflows', [
            'id' => $approval->id,
            'status' => 'approved',
            'remarks' => 'Approved once',
        ]);
    }

    public function test_program_chair_cannot_action_hod_queue_even_for_same_program(): void
    {
        $program = Program::factory()->create();
        $user = $this->chairUser($program);
        $applicant = Applicant::factory()->create(['program_id' => $program->id]);
        $approval = ApprovalWorkflow::create([
            'approvable_type' => Applicant::class,
            'approvable_id' => $applicant->id,
            'approver_role' => 'hod',
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->post(route('chair.approve', $approval), ['remarks' => 'Wrong queue'])
            ->assertForbidden();

        $this->assertDatabaseHas('approval_workflows', [
            'id' => $approval->id,
            'status' => 'pending',
        ]);
    }

    public function test_program_chair_can_override_assigned_program_elective_with_audit_reason(): void
    {
        $program = Program::factory()->create();
        $term = Term::factory()->create(['program_id' => $program->id, 'term_number' => 1, 'is_current' => true]);
        $oldSubject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'Old Elective']);
        $newSubject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'New Elective']);
        ProgramSubject::create([
            'program_id' => $program->id,
            'subject_id' => $oldSubject->id,
            'term_id' => $term->id,
            'type' => 'elective',
            'credits' => 3,
            'is_active' => true,
        ]);
        ProgramSubject::create([
            'program_id' => $program->id,
            'subject_id' => $newSubject->id,
            'term_id' => $term->id,
            'type' => 'elective',
            'credits' => 3,
            'is_active' => true,
        ]);
        $student = Student::factory()->create(['program_id' => $program->id, 'current_term_id' => $term->id]);
        $enrollment = StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $oldSubject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'elective',
            'status' => 'active',
        ]);
        $chair = $this->chairUser($program);

        $this->actingAs($chair)
            ->from(route('chair.students.elective-override'))
            ->post(route('chair.students.elective-override.change', $enrollment), [
                'new_subject_id' => $newSubject->id,
                'reason' => 'Student shifted elective after counselling.',
            ])
            ->assertRedirect(route('chair.students.elective-override'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('student_subject_enrollments', [
            'id' => $enrollment->id,
            'subject_id' => $newSubject->id,
            'previous_subject_id' => $oldSubject->id,
            'override_reason' => 'Student shifted elective after counselling.',
            'overridden_by' => $chair->id,
        ]);
    }

    public function test_program_chair_mentor_assignment_stores_teacher_user_identity(): void
    {
        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $chair = $this->chairUser($program);
        $mentor = Teacher::factory()->create(['status' => 'active']);
        $student = Student::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'active',
        ]);
        $batchStudent = Student::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'active',
        ]);

        $this->actingAs($chair)
            ->get(route('chair.students.mentors'))
            ->assertOk();

        $this->actingAs($chair)
            ->post(route('chair.students.mentors.assign'), [
                'student_id' => $student->id,
                'mentor_id' => $mentor->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'mentor_id' => $mentor->user_id,
        ]);

        $this->actingAs($chair)
            ->post(route('chair.students.mentors.bulk'), [
                'batch_id' => $batch->id,
                'mentor_id' => $mentor->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('students', [
            'id' => $batchStudent->id,
            'mentor_id' => $mentor->user_id,
        ]);
    }

    public function test_program_chair_elective_override_blocks_out_of_scope_invalid_and_duplicate_changes(): void
    {
        $assignedProgram = Program::factory()->create();
        $foreignProgram = Program::factory()->create();
        $term = Term::factory()->create(['program_id' => $assignedProgram->id, 'term_number' => 1, 'is_current' => true]);
        $assignedSubject = Subject::factory()->create(['program_id' => $assignedProgram->id]);
        $replacementSubject = Subject::factory()->create(['program_id' => $assignedProgram->id]);
        $duplicateSubject = Subject::factory()->create(['program_id' => $assignedProgram->id]);
        $foreignSubject = Subject::factory()->create(['program_id' => $foreignProgram->id]);

        foreach ([$assignedSubject, $replacementSubject, $duplicateSubject] as $subject) {
            ProgramSubject::create([
                'program_id' => $assignedProgram->id,
                'subject_id' => $subject->id,
                'term_id' => $term->id,
                'type' => 'elective',
                'credits' => 3,
                'is_active' => true,
            ]);
        }

        $student = Student::factory()->create(['program_id' => $assignedProgram->id, 'current_term_id' => $term->id]);
        $enrollment = StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $assignedSubject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'elective',
            'status' => 'active',
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $duplicateSubject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'elective',
            'status' => 'active',
        ]);

        $foreignStudent = Student::factory()->create(['program_id' => $foreignProgram->id]);
        $foreignEnrollment = StudentSubjectEnrollment::create([
            'student_id' => $foreignStudent->id,
            'subject_id' => $foreignSubject->id,
            'term_id' => null,
            'enrollment_type' => 'elective',
            'status' => 'active',
        ]);
        $chair = $this->chairUser($assignedProgram);

        $this->actingAs($chair)
            ->post(route('chair.students.elective-override.change', $foreignEnrollment), [
                'new_subject_id' => $replacementSubject->id,
                'reason' => 'Out of scope.',
            ])
            ->assertForbidden();

        $this->actingAs($chair)
            ->from(route('chair.students.elective-override'))
            ->post(route('chair.students.elective-override.change', $enrollment), [
                'new_subject_id' => $foreignSubject->id,
                'reason' => 'Wrong program.',
            ])
            ->assertRedirect(route('chair.students.elective-override'))
            ->assertSessionHasErrors('new_subject_id');

        $this->actingAs($chair)
            ->from(route('chair.students.elective-override'))
            ->post(route('chair.students.elective-override.change', $enrollment), [
                'new_subject_id' => $duplicateSubject->id,
                'reason' => 'Duplicate active subject.',
            ])
            ->assertRedirect(route('chair.students.elective-override'))
            ->assertSessionHasErrors('new_subject_id');

        $this->assertDatabaseHas('student_subject_enrollments', [
            'id' => $enrollment->id,
            'subject_id' => $assignedSubject->id,
        ]);
    }
}
