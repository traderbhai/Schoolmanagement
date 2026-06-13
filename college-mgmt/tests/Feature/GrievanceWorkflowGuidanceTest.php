<?php

namespace Tests\Feature;

use App\Models\Program;
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
}
