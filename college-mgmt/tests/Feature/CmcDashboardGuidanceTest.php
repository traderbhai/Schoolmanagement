<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Placement;
use App\Models\PlacementDrive;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CmcDashboardGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private function cmcUser(): User
    {
        Role::firstOrCreate(['name' => 'cmc', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('cmc');

        return $user;
    }

    private function company(array $extra = []): Company
    {
        return Company::create(array_merge([
            'name' => 'Acme Campus Hiring',
            'industry' => 'Technology',
            'is_active' => true,
        ], $extra));
    }

    private function drive(?Company $company = null, array $extra = []): PlacementDrive
    {
        return PlacementDrive::create(array_merge([
            'company_id' => ($company ?? $this->company())->id,
            'title' => 'Graduate Engineer Trainee Drive',
            'job_role' => 'Graduate Engineer Trainee',
            'drive_date' => now()->addWeek()->toDateString(),
            'last_apply_date' => now()->addDays(3)->toDateString(),
            'status' => 'ongoing',
            'vacancies' => 10,
        ], $extra));
    }

    public function test_cmc_dashboard_prioritizes_missing_recruiter_pipeline(): void
    {
        $this->actingAs($this->cmcUser())
            ->get(route('cmc.dashboard'))
            ->assertStatus(200)
            ->assertSee('CMC Priority')
            ->assertSee('Build the recruiter pipeline')
            ->assertSee('Add Company')
            ->assertSee(route('cmc.companies.create'), false);
    }

    public function test_cmc_dashboard_counts_schema_statuses_and_prioritizes_open_applications(): void
    {
        $company = $this->company();
        $drive = $this->drive($company, ['status' => 'ongoing']);
        $student = Student::factory()->create();

        Placement::create([
            'drive_id' => $drive->id,
            'student_id' => $student->id,
            'application_status' => 'applied',
        ]);

        $this->actingAs($this->cmcUser())
            ->get(route('cmc.dashboard'))
            ->assertStatus(200)
            ->assertSee('Review 1 open placement application')
            ->assertSee('Review Applications')
            ->assertSee('Graduate Engineer Trainee')
            ->assertSee('Ongoing')
            ->assertSee('0%');
    }

    public function test_cmc_can_move_application_to_interview_status(): void
    {
        $placement = Placement::create([
            'drive_id' => $this->drive()->id,
            'student_id' => Student::factory()->create()->id,
            'application_status' => 'applied',
        ]);

        $this->actingAs($this->cmcUser())
            ->patch(route('cmc.placements.update-status', $placement), [
                'application_status' => 'interview',
                'offered_package' => 7.5,
                'remarks' => 'Technical interview scheduled.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Application status updated.');

        $placement->refresh();
        $this->assertSame('interview', $placement->application_status);
        $this->assertEquals(7.5, (float) $placement->offered_package);
        $this->assertSame('Technical interview scheduled.', $placement->remarks);
    }

    public function test_drive_applications_modal_uses_route_backed_safe_dataset_values(): void
    {
        $drive = $this->drive();
        $student = Student::factory()->create();
        $placement = Placement::create([
            'drive_id' => $drive->id,
            'student_id' => $student->id,
            'application_status' => 'shortlisted',
            'offered_package' => 6.75,
            'remarks' => 'Candidate said "yes" and it\'s final.',
        ]);

        $this->actingAs($this->cmcUser())
            ->get(route('cmc.drives.applications', $drive))
            ->assertStatus(200)
            ->assertSee(route('cmc.placements.update-status', $placement), false)
            ->assertSee('data-status="shortlisted"', false)
            ->assertSee('data-package="6.75"', false)
            ->assertSee('Candidate said &quot;yes&quot; and it&#039;s final.', false)
            ->assertSee('onclick="openUpdate(this)"', false);
    }

    public function test_cmc_placement_stats_use_selected_placements_and_drive_company(): void
    {
        $company = $this->company(['name' => 'FutureSoft']);
        $drive = $this->drive($company, ['status' => 'upcoming']);
        $student = Student::factory()->create(['status' => 'active']);

        Placement::create([
            'drive_id' => $drive->id,
            'student_id' => $student->id,
            'application_status' => 'selected',
            'offered_package' => 8.25,
        ]);

        $this->actingAs($this->cmcUser())
            ->get(route('cmc.placement-stats'))
            ->assertStatus(200)
            ->assertSee('Placement Statistics')
            ->assertSee('100%')
            ->assertSee('8.3 LPA')
            ->assertSee('FutureSoft')
            ->assertSee('1 placed');
    }
}
