<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Placement;
use App\Models\PlacementDrive;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentPlacementGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private function student(): Student
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $user = User::factory()->create(['name' => 'Placement Student']);
        $user->assignRole('student');

        return Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => Program::factory()->create(['name' => 'Placement Program'])->id,
            'status' => 'active',
        ]);
    }

    private function company(): Company
    {
        return Company::create([
            'name' => 'Campus Recruiter',
            'industry' => 'Technology',
            'is_active' => true,
        ]);
    }

    private function drive(array $extra = []): PlacementDrive
    {
        return PlacementDrive::create(array_merge([
            'company_id' => $this->company()->id,
            'title' => 'Associate Consultant Drive',
            'job_role' => 'Associate Consultant',
            'package' => '6 LPA',
            'eligibility' => 'No active backlogs.',
            'drive_date' => now()->addWeek()->toDateString(),
            'last_apply_date' => now()->addDays(2)->toDateString(),
            'location' => 'Campus',
            'status' => 'upcoming',
            'vacancies' => 12,
        ], $extra));
    }

    public function test_student_placement_page_surfaces_deadline_priority_and_next_action(): void
    {
        $student = $this->student();
        $this->drive();

        $this->actingAs($student->user)
            ->get(route('student.placements'))
            ->assertStatus(200)
            ->assertSee('Placement Priority')
            ->assertSee('Placement deadline is near')
            ->assertSee('Associate Consultant Drive')
            ->assertSee('Apply by:')
            ->assertSee('No active backlogs.')
            ->assertSee('Apply Now');
    }

    public function test_student_cannot_apply_to_completed_or_expired_drive(): void
    {
        $student = $this->student();
        $completedDrive = $this->drive(['status' => 'completed']);
        $expiredDrive = $this->drive([
            'status' => 'ongoing',
            'last_apply_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($student->user)
            ->post(route('student.placements.apply', $completedDrive))
            ->assertRedirect(route('student.placements'))
            ->assertSessionHas('error', 'This placement drive is not open for applications.');

        $this->actingAs($student->user)
            ->post(route('student.placements.apply', $expiredDrive))
            ->assertRedirect(route('student.placements'))
            ->assertSessionHas('error', 'The application deadline for this drive has passed.');

        $this->assertSame(0, Placement::where('student_id', $student->id)->count());
    }

    public function test_student_application_tracking_explains_next_step_and_package(): void
    {
        $student = $this->student();
        $drive = $this->drive(['status' => 'ongoing']);

        Placement::create([
            'drive_id' => $drive->id,
            'student_id' => $student->id,
            'application_status' => 'interview',
            'offered_package' => 7.25,
        ]);

        $this->actingAs($student->user)
            ->get(route('student.placements.applications'))
            ->assertStatus(200)
            ->assertSee('My Placement Applications')
            ->assertSee('Interview')
            ->assertSee('7.3 LPA')
            ->assertSee('Watch for interview schedule and instructions.');
    }
}
