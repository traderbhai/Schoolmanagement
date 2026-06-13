<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Internship;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InternshipWorkflowGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function student(): Student
    {
        $user = $this->userWithRole('student');

        return Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => Program::factory()->create()->id,
            'status' => 'active',
        ]);
    }

    private function company(): Company
    {
        return Company::create([
            'name' => 'Internship Partner',
            'industry' => 'Consulting',
            'is_active' => true,
        ]);
    }

    private function internship(Student $student, array $extra = []): Internship
    {
        return Internship::create(array_merge([
            'student_id' => $student->id,
            'company_id' => $this->company()->id,
            'company_name' => 'Internship Partner',
            'role_title' => 'Research Intern',
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
            'type' => 'internship',
            'status' => 'ongoing',
            'stipend' => 15000,
            'supervisor_name' => 'Industry Mentor',
            'supervisor_email' => 'mentor@example.com',
            'description' => 'Assist with research and weekly client reporting.',
            'approved_by' => User::factory()->create()->id,
        ], $extra));
    }

    public function test_cmc_internship_index_prioritizes_overdue_completions(): void
    {
        $this->internship($this->student());

        $this->actingAs($this->userWithRole('cmc'))
            ->get(route('cmc.internships.index'))
            ->assertStatus(200)
            ->assertSee('Internship Priority')
            ->assertSee('Close 1 overdue internship')
            ->assertSee('Past planned end date')
            ->assertSee('Rs. 15,000/mo')
            ->assertSee('Research Intern');
    }

    public function test_cmc_completion_requires_valid_ongoing_internship_end_date(): void
    {
        $internship = $this->internship($this->student(), [
            'start_date' => now()->toDateString(),
            'end_date' => null,
        ]);

        $this->actingAs($this->userWithRole('cmc'))
            ->post(route('cmc.internships.complete', $internship), [
                'end_date' => now()->subDay()->toDateString(),
                'feedback' => 'Too early',
                'rating' => 4,
            ])
            ->assertSessionHasErrors('end_date');

        $this->assertSame('ongoing', $internship->fresh()->status);

        $this->actingAs($this->userWithRole('cmc'))
            ->post(route('cmc.internships.complete', $internship), [
                'end_date' => now()->addDay()->toDateString(),
                'feedback' => 'Completed successfully.',
                'rating' => 5,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Internship marked as completed.');

        $internship->refresh();
        $this->assertSame('completed', $internship->status);
        $this->assertSame('Completed successfully.', $internship->feedback);
        $this->assertSame(5, $internship->rating);

        $this->actingAs($this->userWithRole('cmc'))
            ->post(route('cmc.internships.complete', $internship), [
                'end_date' => now()->addDays(2)->toDateString(),
            ])
            ->assertSessionHas('error', 'Only ongoing internships can be marked as completed.');
    }

    public function test_student_internship_page_explains_status_and_feedback(): void
    {
        $student = $this->student();
        $this->internship($student, [
            'status' => 'completed',
            'end_date' => now()->toDateString(),
            'feedback' => 'Strong performance.',
            'rating' => 5,
        ]);

        $this->actingAs($student->user)
            ->get(route('student.internships.index'))
            ->assertStatus(200)
            ->assertSee('Internship Priority')
            ->assertSee('Internship record completed')
            ->assertSee('Research Intern')
            ->assertSee('Rs. 15,000/mo')
            ->assertSee('Strong performance.')
            ->assertSee('Rating: 5/5');
    }
}
