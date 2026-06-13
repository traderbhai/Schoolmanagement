<?php

namespace Tests\Feature;

use App\Models\AlumniProfile;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AlumniWorkflowGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function student(?Program $program = null, array $extra = []): Student
    {
        $user = $this->userWithRole($extra['role'] ?? 'student');
        unset($extra['role']);

        return Student::factory()->create(array_merge([
            'user_id' => $user->id,
            'program_id' => ($program ?? Program::factory()->create())->id,
            'status' => 'graduated',
        ], $extra));
    }

    private function alumni(Student $student, array $extra = []): AlumniProfile
    {
        return AlumniProfile::create(array_merge([
            'student_id' => $student->id,
            'graduation_year' => 2024,
            'current_employer' => 'Deloitte',
            'current_role' => 'Analyst',
            'current_salary' => 900000,
            'linkedin_url' => 'https://linkedin.com/in/demo-alumni',
            'city' => 'Mumbai',
            'country' => 'India',
            'feedback' => 'Helpful alumni mentor.',
            'is_verified' => false,
        ], $extra));
    }

    public function test_cmc_alumni_index_prioritizes_unverified_profiles(): void
    {
        $this->alumni($this->student());

        $this->actingAs($this->userWithRole('cmc'))
            ->get(route('cmc.alumni.index'))
            ->assertStatus(200)
            ->assertSee('Alumni Priority')
            ->assertSee('Verify 1 alumni profile')
            ->assertSee('Needs Verification')
            ->assertSee('Rs. 900,000')
            ->assertSee('Deloitte');
    }

    public function test_cmc_can_create_profile_with_country_default_and_verify_once(): void
    {
        $student = $this->student();

        $this->actingAs($this->userWithRole('cmc'))
            ->post(route('cmc.alumni.store'), [
                'student_id' => $student->id,
                'graduation_year' => 2024,
                'current_employer' => 'Goldman Sachs',
                'current_role' => 'Associate',
                'current_salary' => 1200000,
                'city' => 'Bengaluru',
                'linkedin_url' => 'https://linkedin.com/in/goldman-demo',
                'feedback' => 'Open to mentoring students.',
            ])
            ->assertRedirect(route('cmc.alumni.index'))
            ->assertSessionHas('success', 'Alumni profile saved.');

        $profile = AlumniProfile::where('student_id', $student->id)->firstOrFail();
        $this->assertSame('India', $profile->country);
        $this->assertFalse($profile->is_verified);

        $this->actingAs($this->userWithRole('cmc'))
            ->post(route('cmc.alumni.verify', $profile))
            ->assertRedirect()
            ->assertSessionHas('success', 'Alumni profile verified.');

        $this->actingAs($this->userWithRole('cmc'))
            ->post(route('cmc.alumni.verify', $profile->fresh()))
            ->assertRedirect()
            ->assertSessionHas('success', 'Alumni profile is already verified.');
    }

    public function test_student_alumni_network_defaults_to_verified_same_program_profiles(): void
    {
        $program = Program::factory()->create(['name' => 'MBA']);
        $otherProgram = Program::factory()->create(['name' => 'BCA']);
        $currentStudent = $this->student($program, ['status' => 'active']);
        $sameProgramAlumni = $this->student($program, ['status' => 'graduated']);
        $otherProgramAlumni = $this->student($otherProgram, ['status' => 'graduated']);
        $unverifiedSameProgram = $this->student($program, ['status' => 'graduated']);

        $this->alumni($sameProgramAlumni, ['is_verified' => true, 'current_employer' => 'Deloitte']);
        $this->alumni($otherProgramAlumni, ['is_verified' => true, 'current_employer' => 'Infosys']);
        $this->alumni($unverifiedSameProgram, ['is_verified' => false, 'current_employer' => 'Hidden Employer']);

        $this->actingAs($currentStudent->user)
            ->get(route('student.alumni.index'))
            ->assertStatus(200)
            ->assertSee('1 alumni from your program')
            ->assertSee('Deloitte')
            ->assertDontSee('Infosys')
            ->assertDontSee('Hidden Employer');

        $this->actingAs($currentStudent->user)
            ->get(route('student.alumni.index', ['all_programs' => 1]))
            ->assertStatus(200)
            ->assertSee('2 verified alumni available')
            ->assertSee('Deloitte')
            ->assertSee('Infosys')
            ->assertDontSee('Hidden Employer');
    }
}
