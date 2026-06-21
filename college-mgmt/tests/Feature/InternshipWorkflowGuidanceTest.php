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

    public function test_cmc_can_register_internship_only_for_active_student(): void
    {
        $student = $this->student();
        $company = $this->company();

        $this->actingAs($this->userWithRole('cmc'))
            ->post(route('cmc.internships.store'), [
                'student_id' => $student->id,
                'company_id' => $company->id,
                'company_name' => $company->name,
                'role_title' => 'Research Intern',
                'start_date' => now()->toDateString(),
                'end_date' => now()->addMonth()->toDateString(),
                'type' => 'internship',
                'stipend' => 15000,
            ])
            ->assertRedirect(route('cmc.internships.index'))
            ->assertSessionHas('success', 'Internship registered.');

        $this->assertDatabaseHas('internships', [
            'student_id' => $student->id,
            'company_id' => $company->id,
            'status' => 'ongoing',
        ]);

        $inactive = $this->student();
        $inactive->update(['status' => 'inactive']);

        $this->actingAs($this->userWithRole('cmc'))
            ->from(route('cmc.internships.create'))
            ->post(route('cmc.internships.store'), [
                'student_id' => $inactive->id,
                'company_id' => $company->id,
                'company_name' => $company->name,
                'role_title' => 'Archived Student Intern',
                'start_date' => now()->toDateString(),
                'end_date' => now()->addMonth()->toDateString(),
                'type' => 'internship',
                'stipend' => 15000,
            ])
            ->assertRedirect(route('cmc.internships.create'))
            ->assertSessionHasErrors('student_id');

        $this->assertDatabaseMissing('internships', [
            'student_id' => $inactive->id,
            'role_title' => 'Archived Student Intern',
        ]);
    }

    public function test_cmc_cannot_register_internship_with_inactive_company_partner(): void
    {
        $student = $this->student();
        $company = $this->company();
        $company->update(['is_active' => false]);

        $this->actingAs($this->userWithRole('cmc'))
            ->get(route('cmc.internships.create'))
            ->assertStatus(200)
            ->assertDontSee('<option value="' . $company->id . '">Internship Partner</option>', false);

        $this->actingAs($this->userWithRole('cmc'))
            ->from(route('cmc.internships.create'))
            ->post(route('cmc.internships.store'), [
                'student_id' => $student->id,
                'company_id' => $company->id,
                'company_name' => $company->name,
                'role_title' => 'Inactive Partner Intern',
                'start_date' => now()->toDateString(),
                'end_date' => now()->addMonth()->toDateString(),
                'type' => 'internship',
                'stipend' => 15000,
            ])
            ->assertRedirect(route('cmc.internships.create'))
            ->assertSessionHasErrors('company_id');

        $this->assertDatabaseMissing('internships', [
            'student_id' => $student->id,
            'company_id' => $company->id,
            'role_title' => 'Inactive Partner Intern',
        ]);
    }

    public function test_cmc_registration_uses_canonical_company_name_when_partner_is_selected(): void
    {
        $student = $this->student();
        $company = $this->company();

        $this->actingAs($this->userWithRole('cmc'))
            ->post(route('cmc.internships.store'), [
                'student_id' => $student->id,
                'company_id' => $company->id,
                'company_name' => 'Forged Partner Name',
                'role_title' => 'Research Intern',
                'start_date' => now()->toDateString(),
                'end_date' => now()->addMonth()->toDateString(),
                'type' => 'internship',
                'stipend' => 15000,
            ])
            ->assertRedirect(route('cmc.internships.index'))
            ->assertSessionHas('success', 'Internship registered.');

        $this->assertDatabaseHas('internships', [
            'student_id' => $student->id,
            'company_id' => $company->id,
            'company_name' => $company->name,
            'status' => 'ongoing',
        ]);
        $this->assertDatabaseMissing('internships', [
            'student_id' => $student->id,
            'company_id' => $company->id,
            'company_name' => 'Forged Partner Name',
        ]);
    }

    public function test_cmc_cannot_register_overlapping_ongoing_internship_for_same_student(): void
    {
        $student = $this->student();
        $company = $this->company();

        $this->internship($student, [
            'company_id' => $company->id,
            'company_name' => $company->name,
            'start_date' => now()->subWeek()->toDateString(),
            'end_date' => now()->addWeeks(3)->toDateString(),
            'status' => 'ongoing',
        ]);

        $this->actingAs($this->userWithRole('cmc'))
            ->from(route('cmc.internships.create'))
            ->post(route('cmc.internships.store'), [
                'student_id' => $student->id,
                'company_id' => $company->id,
                'company_name' => $company->name,
                'role_title' => 'Duplicate Active Internship',
                'start_date' => now()->addWeek()->toDateString(),
                'end_date' => now()->addMonth()->toDateString(),
                'type' => 'live_project',
                'stipend' => 12000,
            ])
            ->assertRedirect(route('cmc.internships.create'))
            ->assertSessionHasErrors('start_date');

        $this->assertSame(1, Internship::where('student_id', $student->id)->where('status', 'ongoing')->count());
        $this->assertDatabaseMissing('internships', [
            'student_id' => $student->id,
            'role_title' => 'Duplicate Active Internship',
        ]);
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
                'feedback' => 'Future completion should not be accepted.',
                'rating' => 4,
            ])
            ->assertSessionHasErrors('end_date');

        $this->assertSame('ongoing', $internship->fresh()->status);

        $this->actingAs($this->userWithRole('cmc'))
            ->post(route('cmc.internships.complete', $internship), [
                'end_date' => now()->toDateString(),
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
                'end_date' => now()->toDateString(),
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

    public function test_student_internship_empty_state_explains_cmc_record_source(): void
    {
        $student = $this->student();

        $this->actingAs($student->user)
            ->get(route('student.internships.index'))
            ->assertOk()
            ->assertSee('No internship records are linked to your profile yet')
            ->assertSee('CMC adds internships, industrial training, and live projects')
            ->assertSee('role, dates, supervisor, and approval are confirmed')
            ->assertSee('completion feedback, and rating')
            ->assertDontSee('No internship records found. Internships are assigned by the placement cell.');
    }
}
