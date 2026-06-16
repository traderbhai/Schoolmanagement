<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\RoleProgramAssignment;
use App\Models\Student;
use App\Models\StudentGrievance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GrievanceWorkflowGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function student(array $extra = []): Student
    {
        $user = $this->userWithRole('student');

        return Student::factory()->create(array_merge([
            'user_id' => $user->id,
            'program_id' => Program::factory()->create()->id,
            'status' => 'active',
        ], $extra));
    }

    private function grievance(Student $student, array $extra = []): StudentGrievance
    {
        return StudentGrievance::create(array_merge([
            'student_id' => $student->id,
            'program_id' => $student->program_id,
            'category' => 'academic',
            'title' => 'Marks not updated',
            'description' => 'Internal assessment marks are missing from the portal.',
            'status' => 'open',
            'priority' => 'urgent',
        ], $extra));
    }

    public function test_student_grievance_page_surfaces_priority_and_clean_status(): void
    {
        $student = $this->student();
        $this->grievance($student, ['status' => 'escalated']);

        $this->actingAs($student->user)
            ->get(route('student.grievances.index'))
            ->assertStatus(200)
            ->assertSee('Grievance Priority')
            ->assertSee('A grievance has been escalated')
            ->assertSee('Open Grievance')
            ->assertSee('Marks not updated')
            ->assertSee('Escalated');
    }

    public function test_admin_grievance_queue_uses_real_fields_and_priority_guidance(): void
    {
        $student = $this->student();
        $this->grievance($student, ['created_at' => now()->subDays(8)]);

        $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.grievances.index'))
            ->assertStatus(200)
            ->assertSee('Grievance Priority')
            ->assertSee('Handle 1 urgent grievance')
            ->assertSee('Marks not updated')
            ->assertSee('Urgent Active')
            ->assertSee('Title');
    }

    public function test_admin_can_resolve_with_resolution_notes_and_assignment(): void
    {
        $student = $this->student();
        $grievance = $this->grievance($student);
        $assignee = $this->userWithRole('hod');

        $this->actingAs($this->userWithRole('admin'))
            ->patch(route('admin.grievances.update', $grievance), [
                'status' => 'resolved',
                'assigned_to' => $assignee->id,
                'resolution_notes' => 'Marks were updated after exam-cell verification.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Grievance updated successfully.');

        $grievance->refresh();
        $this->assertSame('resolved', $grievance->status);
        $this->assertSame($assignee->id, $grievance->assigned_to);
        $this->assertSame('Marks were updated after exam-cell verification.', $grievance->resolution_notes);
        $this->assertNotNull($grievance->resolved_at);
        $this->assertNotNull($grievance->resolved_by);

        $this->actingAs($student->user)
            ->get(route('student.grievances.show', $grievance))
            ->assertStatus(200)
            ->assertSee('Resolution:')
            ->assertSee('Marks were updated after exam-cell verification.');
    }

    public function test_admin_cannot_resolve_or_close_without_resolution_notes(): void
    {
        $student = $this->student();
        $grievance = $this->grievance($student);

        $this->actingAs($this->userWithRole('admin'))
            ->patch(route('admin.grievances.update', $grievance), [
                'status' => 'resolved',
            ])
            ->assertSessionHasErrors('resolution_notes');

        $this->actingAs($this->userWithRole('admin'))
            ->patch(route('admin.grievances.update', $grievance), [
                'status' => 'closed',
                'resolution_notes' => '   ',
            ])
            ->assertSessionHasErrors('resolution_notes');

        $grievance->refresh();
        $this->assertSame('open', $grievance->status);
        $this->assertNull($grievance->resolved_at);
        $this->assertNull($grievance->resolved_by);
    }

    public function test_admin_reopening_grievance_clears_resolution_metadata(): void
    {
        $student = $this->student();
        $admin = $this->userWithRole('admin');
        $grievance = $this->grievance($student, [
            'status' => 'resolved',
            'resolution_notes' => 'Initial resolution.',
            'resolved_at' => now(),
            'resolved_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.grievances.update', $grievance), [
                'status' => 'under_review',
                'resolution_notes' => 'Reopened because student submitted additional evidence.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Grievance updated successfully.');

        $grievance->refresh();
        $this->assertSame('under_review', $grievance->status);
        $this->assertSame('Reopened because student submitted additional evidence.', $grievance->resolution_notes);
        $this->assertNull($grievance->resolved_at);
        $this->assertNull($grievance->resolved_by);
    }

    public function test_student_can_close_resolved_grievance_once(): void
    {
        $student = $this->student();
        $grievance = $this->grievance($student, [
            'status' => 'resolved',
            'resolution_notes' => 'Issue fixed.',
            'resolved_at' => now(),
        ]);

        $this->actingAs($student->user)
            ->post(route('student.grievances.close', $grievance))
            ->assertRedirect()
            ->assertSessionHas('success', 'Grievance closed.');

        $this->assertSame('closed', $grievance->fresh()->status);

        $this->actingAs($student->user)
            ->post(route('student.grievances.close', $grievance))
            ->assertStatus(422);
    }

    public function test_student_cannot_close_unresolved_grievance(): void
    {
        $student = $this->student();
        $grievance = $this->grievance($student, ['status' => 'under_review']);

        $this->actingAs($student->user)
            ->post(route('student.grievances.close', $grievance))
            ->assertStatus(422);

        $this->assertSame('under_review', $grievance->fresh()->status);
        $this->assertNull($grievance->fresh()->resolved_at);
    }

    public function test_department_can_resolve_open_grievance_but_cannot_resolve_closed_history(): void
    {
        $student = $this->student();
        $hod = $this->userWithRole('hod');
        $open = $this->grievance($student, ['status' => 'under_review']);
        $closed = $this->grievance($student, [
            'title' => 'Closed grievance',
            'status' => 'closed',
            'resolution_notes' => 'Already closed by student.',
            'resolved_at' => now(),
            'resolved_by' => $hod->id,
        ]);

        $this->actingAs($hod)
            ->post(route('hod.grievances.resolve', $open), [
                'resolution_notes' => 'Department verified the marks and updated the record.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Grievance resolved.');

        $open->refresh();
        $this->assertSame('resolved', $open->status);
        $this->assertSame($hod->id, $open->resolved_by);
        $this->assertSame('Department verified the marks and updated the record.', $open->resolution_notes);
        $this->assertNotNull($open->resolved_at);

        $this->actingAs($hod)
            ->post(route('hod.grievances.resolve', $closed), [
                'resolution_notes' => 'Trying to overwrite closed history.',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Only open, under review, or escalated grievances can be resolved by the department.');

        $closed->refresh();
        $this->assertSame('closed', $closed->status);
        $this->assertSame('Already closed by student.', $closed->resolution_notes);
    }

    public function test_department_can_escalate_active_grievance_but_not_resolved_or_closed_records(): void
    {
        $student = $this->student();
        $hod = $this->userWithRole('hod');
        $open = $this->grievance($student, ['status' => 'open']);
        $resolved = $this->grievance($student, [
            'title' => 'Resolved grievance',
            'status' => 'resolved',
            'resolution_notes' => 'Resolved by department.',
            'resolved_at' => now(),
            'resolved_by' => $hod->id,
        ]);
        $closed = $this->grievance($student, [
            'title' => 'Closed grievance',
            'status' => 'closed',
            'resolution_notes' => 'Closed by student.',
            'resolved_at' => now(),
            'resolved_by' => $hod->id,
        ]);

        $this->actingAs($hod)
            ->post(route('hod.grievances.escalate', $open))
            ->assertRedirect()
            ->assertSessionHas('success', 'Grievance escalated to Dean.');

        $this->assertSame('escalated', $open->fresh()->status);

        foreach ([$resolved, $closed] as $grievance) {
            $this->actingAs($hod)
                ->post(route('hod.grievances.escalate', $grievance))
                ->assertRedirect()
                ->assertSessionHas('error', 'Only open or under review grievances can be escalated.');
        }

        $this->assertSame('resolved', $resolved->fresh()->status);
        $this->assertSame('closed', $closed->fresh()->status);
    }

    public function test_program_chair_can_update_assigned_program_grievance_with_lifecycle_rules(): void
    {
        $chair = $this->userWithRole('program_chair');
        $admin = $this->userWithRole('admin');
        $student = $this->student();

        RoleProgramAssignment::create([
            'user_id' => $chair->id,
            'role_name' => 'program_chair',
            'program_id' => $student->program_id,
            'is_active' => true,
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
        ]);

        $grievance = $this->grievance($student, ['status' => 'under_review']);

        $this->actingAs($chair)
            ->post(route('chair.students.grievances.update', $grievance), [
                'status' => 'resolved',
            ])
            ->assertSessionHasErrors('resolution_notes');

        $this->actingAs($chair)
            ->post(route('chair.students.grievances.update', $grievance), [
                'status' => 'resolved',
                'resolution_notes' => 'Program chair verified the academic record and resolved the issue.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Grievance updated.');

        $grievance->refresh();
        $this->assertSame('resolved', $grievance->status);
        $this->assertSame($chair->id, $grievance->resolved_by);
        $this->assertSame('Program chair verified the academic record and resolved the issue.', $grievance->resolution_notes);
        $this->assertNotNull($grievance->resolved_at);
    }

    public function test_program_chair_cannot_update_out_of_scope_or_locked_grievance_history(): void
    {
        $chair = $this->userWithRole('program_chair');
        $admin = $this->userWithRole('admin');
        $assignedStudent = $this->student();
        $otherStudent = $this->student();

        RoleProgramAssignment::create([
            'user_id' => $chair->id,
            'role_name' => 'program_chair',
            'program_id' => $assignedStudent->program_id,
            'is_active' => true,
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
        ]);

        $outOfScope = $this->grievance($otherStudent, ['status' => 'open']);
        $resolved = $this->grievance($assignedStudent, [
            'status' => 'resolved',
            'resolution_notes' => 'Already resolved.',
            'resolved_at' => now(),
            'resolved_by' => $admin->id,
        ]);
        $closed = $this->grievance($assignedStudent, [
            'title' => 'Closed grievance',
            'status' => 'closed',
            'resolution_notes' => 'Student accepted resolution.',
            'resolved_at' => now(),
            'resolved_by' => $admin->id,
        ]);

        $this->actingAs($chair)
            ->post(route('chair.students.grievances.update', $outOfScope), [
                'status' => 'under_review',
            ])
            ->assertForbidden();

        foreach ([$resolved, $closed] as $grievance) {
            $this->actingAs($chair)
                ->post(route('chair.students.grievances.update', $grievance), [
                    'status' => 'open',
                    'resolution_notes' => 'Trying to reopen locked history.',
                ])
                ->assertRedirect()
                ->assertSessionHas('error', 'Resolved or closed grievance history cannot be changed from Program Chair operations.');
        }

        $this->assertSame('open', $outOfScope->fresh()->status);
        $this->assertSame('resolved', $resolved->fresh()->status);
        $this->assertSame('Already resolved.', $resolved->fresh()->resolution_notes);
        $this->assertSame('closed', $closed->fresh()->status);
        $this->assertSame('Student accepted resolution.', $closed->fresh()->resolution_notes);
    }
}
