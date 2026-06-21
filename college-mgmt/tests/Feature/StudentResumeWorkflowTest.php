<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentResume;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentResumeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function student(string $status = 'active'): Student
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $user = User::factory()->create(['name' => 'Resume Student']);
        $user->assignRole('student');

        return Student::factory()->create([
            'user_id' => $user->id,
            'status' => $status,
        ]);
    }

    public function test_active_student_can_save_resume(): void
    {
        $student = $this->student();

        $this->actingAs($student->user)
            ->post(route('student.resume.save'), [
                'headline' => 'Final year analytics student',
                'objective' => 'Looking for analytics roles.',
                'skills' => 'SQL, Python, Laravel',
                'languages' => 'English, Hindi',
                'linkedin_url' => 'https://linkedin.com/in/resume-student',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Resume saved successfully.');

        $resume = StudentResume::where('student_id', $student->id)->firstOrFail();
        $this->assertSame('Final year analytics student', $resume->headline);
        $this->assertSame(['SQL', 'Python', 'Laravel'], $resume->skills);
        $this->assertTrue($resume->is_complete);
    }

    public function test_resume_saves_structured_sections_from_visible_form_fields(): void
    {
        $student = $this->student();

        $this->actingAs($student->user)
            ->post(route('student.resume.save'), [
                'headline' => 'Final year product student',
                'objective' => 'Looking for product analyst roles.',
                'skills' => 'SQL, Excel',
                'languages' => 'English, Hindi',
                'projects' => [
                    [
                        'title' => 'Admissions Dashboard',
                        'tech' => 'Laravel, SQL',
                        'description' => 'Built a KPI dashboard for admission conversion tracking.',
                        'url' => 'https://example.com/project',
                    ],
                ],
                'experience' => [
                    [
                        'company' => 'Campus Incubation Cell',
                        'role' => 'Intern',
                        'from' => '2026-01',
                        'to' => '2026-03',
                        'description' => 'Supported market research and reporting.',
                    ],
                ],
                'certifications' => [
                    [
                        'name' => 'Business Analytics Foundation',
                        'issuer' => 'Demo Academy',
                        'date' => '2026-02',
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Resume saved successfully.');

        $resume = StudentResume::where('student_id', $student->id)->firstOrFail();

        $this->assertSame('Admissions Dashboard', $resume->projects[0]['title']);
        $this->assertSame('Campus Incubation Cell', $resume->experience[0]['company']);
        $this->assertSame('Business Analytics Foundation', $resume->certifications[0]['name']);
    }

    public function test_resume_page_guides_empty_career_readiness_sections(): void
    {
        $student = $this->student();

        $this->actingAs($student->user)
            ->get(route('student.resume.index'))
            ->assertOk()
            ->assertSee('Build the resume CMC and placement teams will use')
            ->assertSee('No projects added yet')
            ->assertSee('Add academic, capstone, lab, competition, or self-learning projects')
            ->assertSee('No internship or work experience added yet')
            ->assertSee('Freshers can leave this empty and strengthen projects/certifications instead')
            ->assertSee('No certifications added yet')
            ->assertDontSee('No projects added yet.')
            ->assertDontSee('No experience added yet.')
            ->assertDontSee('No certifications added yet.');
    }

    public function test_inactive_student_can_view_resume_but_cannot_update_it(): void
    {
        $student = $this->student('inactive');
        StudentResume::create([
            'student_id' => $student->id,
            'headline' => 'Archived saved headline',
            'objective' => 'Original saved objective.',
            'skills' => ['Excel'],
            'languages' => ['English'],
            'is_complete' => true,
        ]);

        $this->actingAs($student->user)
            ->get(route('student.resume.index'))
            ->assertOk()
            ->assertSee('Resume updates are locked')
            ->assertSee('Archived saved headline')
            ->assertSee('Active students only')
            ->assertDontSee('Save Resume');

        $this->actingAs($student->user)
            ->post(route('student.resume.save'), [
                'headline' => 'Inactive overwrite attempt',
                'objective' => 'This should not be saved.',
                'skills' => 'PHP, React',
                'languages' => 'English',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Resume updates are available only for active students. Contact the placement office for archived records.');

        $resume = StudentResume::where('student_id', $student->id)->firstOrFail();
        $this->assertSame('Archived saved headline', $resume->headline);
        $this->assertSame('Original saved objective.', $resume->objective);
        $this->assertSame(['Excel'], $resume->skills);
        $this->assertDatabaseMissing('student_resumes', [
            'student_id' => $student->id,
            'headline' => 'Inactive overwrite attempt',
        ]);
    }
}
