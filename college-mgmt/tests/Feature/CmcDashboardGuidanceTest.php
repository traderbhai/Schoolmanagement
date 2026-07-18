<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CareerEvent;
use App\Models\CareerEventRegistration;
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
            ->assertSee(route('cmc.drives', ['status' => 'active']), false)
            ->assertSee(route('cmc.placements'), false)
            ->assertSee(route('cmc.analytics'), false)
            ->assertSee('Graduate Engineer Trainee')
            ->assertSee('Ongoing')
            ->assertSee('0%');
    }

    public function test_cmc_active_drives_kpi_opens_matching_active_drive_list_and_export(): void
    {
        $company = $this->company(['name' => 'Active Recruiter']);
        $upcoming = $this->drive($company, ['title' => 'Upcoming Active Drive', 'status' => 'upcoming']);
        $ongoing = $this->drive($company, ['title' => 'Ongoing Active Drive', 'status' => 'ongoing']);
        $completed = $this->drive($company, ['title' => 'Completed Historical Drive', 'status' => 'completed']);
        $cmc = $this->cmcUser();

        $this->actingAs($cmc)
            ->get(route('cmc.dashboard'))
            ->assertOk()
            ->assertSee('Active Drives')
            ->assertSee('Open active drives')
            ->assertSee(route('cmc.drives', ['status' => 'active']), false);

        $this->actingAs($cmc)
            ->get(route('cmc.drives', ['status' => 'active']))
            ->assertOk()
            ->assertSee('Active (upcoming or ongoing)')
            ->assertSee('Showing 2 drive(s)')
            ->assertSee($upcoming->title)
            ->assertSee($ongoing->title)
            ->assertDontSee($completed->title);

        $csv = $this->actingAs($cmc)
            ->get(route('cmc.drives.export', ['status' => 'active']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Upcoming Active Drive', $csv);
        $this->assertStringContainsString('Ongoing Active Drive', $csv);
        $this->assertStringNotContainsString('Completed Historical Drive', $csv);
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

    public function test_cmc_drives_export_matches_filtered_current_view(): void
    {
        $company = $this->company(['name' => 'Filtered Recruiter']);
        $matching = $this->drive($company, ['title' => 'Filtered Drive', 'status' => 'ongoing']);
        $this->drive($this->company(['name' => 'Archived Recruiter']), ['title' => 'Completed Drive', 'status' => 'completed']);

        $this->actingAs($this->cmcUser())
            ->get(route('cmc.drives', ['status' => 'ongoing', 'company_id' => $company->id]))
            ->assertStatus(200)
            ->assertSee('Export Current View')
            ->assertSee(route('cmc.drives.export', ['status' => 'ongoing', 'company_id' => $company->id]))
            ->assertSee('Showing 1 drive(s)')
            ->assertSee($matching->title)
            ->assertDontSee('Completed Drive');

        $response = $this->actingAs($this->cmcUser())
            ->get(route('cmc.drives.export', ['status' => 'ongoing', 'company_id' => $company->id]));

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Filtered Drive', $csv);
        $this->assertStringNotContainsString('Completed Drive', $csv);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'export',
            'description' => 'CMC placement drives exported: 1 rows; filters={"status":"ongoing","company_id":"' . $company->id . '"}',
        ]);
    }

    public function test_cmc_companies_and_events_expose_filtered_exports(): void
    {
        $company = $this->company(['name' => 'Searchable Recruiter']);
        $this->company(['name' => 'Other Recruiter']);
        $cmc = $this->cmcUser();
        $event = CareerEvent::create([
            'title' => 'Mock Interview Clinic',
            'event_type' => 'mock_interview',
            'organizer_id' => $cmc->id,
            'event_date' => now()->addWeek()->toDateString(),
            'is_published' => true,
        ]);
        CareerEvent::create([
            'title' => 'Resume Workshop',
            'event_type' => 'workshop',
            'organizer_id' => $cmc->id,
            'event_date' => now()->addWeek()->toDateString(),
            'is_published' => true,
        ]);

        $this->actingAs($cmc)
            ->get(route('cmc.companies', ['search' => 'Searchable']))
            ->assertStatus(200)
            ->assertSee(route('cmc.companies.export', ['search' => 'Searchable']))
            ->assertSee('Search companies')
            ->assertSee('Export Current View')
            ->assertSee('Showing 1 company record(s)')
            ->assertSee($company->name)
            ->assertDontSee('Other Recruiter');

        $companyCsv = $this->actingAs($cmc)
            ->get(route('cmc.companies.export', ['search' => 'Searchable']))
            ->streamedContent();
        $this->assertStringContainsString('Searchable Recruiter', $companyCsv);
        $this->assertStringNotContainsString('Other Recruiter', $companyCsv);

        $this->actingAs($cmc)
            ->get(route('cmc.events', ['type' => 'mock_interview']))
            ->assertStatus(200)
            ->assertSee(route('cmc.events.export', ['type' => 'mock_interview']))
            ->assertSee('Showing 1 career event(s)')
            ->assertSee($event->title)
            ->assertDontSee('Resume Workshop');

        $eventCsv = $this->actingAs($cmc)
            ->get(route('cmc.events.export', ['type' => 'mock_interview']))
            ->streamedContent();
        $this->assertStringContainsString('Mock Interview Clinic', $eventCsv);
        $this->assertStringNotContainsString('Resume Workshop', $eventCsv);
    }

    public function test_cmc_detail_lists_and_selected_placements_export_current_rows(): void
    {
        $drive = $this->drive($this->company(['name' => 'Detail Recruiter']), ['title' => 'Detail Drive', 'vacancies' => 2]);
        $student = Student::factory()->create();
        $selected = Placement::create([
            'drive_id' => $drive->id,
            'student_id' => $student->id,
            'application_status' => 'selected',
            'offered_package' => 8.5,
        ]);
        Placement::create([
            'drive_id' => $drive->id,
            'student_id' => Student::factory()->create()->id,
            'application_status' => 'applied',
        ]);
        $event = CareerEvent::create([
            'title' => 'Career Fair',
            'event_type' => 'career_fair',
            'organizer_id' => $this->cmcUser()->id,
            'event_date' => now()->subDay()->toDateString(),
            'is_published' => true,
        ]);
        CareerEventRegistration::create([
            'career_event_id' => $event->id,
            'student_id' => $student->id,
            'status' => 'registered',
            'attended' => true,
        ]);

        $this->actingAs($this->cmcUser())
            ->get(route('cmc.drives.applications', $drive))
            ->assertStatus(200)
            ->assertSee(route('cmc.drives.applications.export', $drive), false)
            ->assertSee('Showing 2 application record(s) for this drive.');

        $applicationsCsv = $this->actingAs($this->cmcUser())
            ->get(route('cmc.drives.applications.export', $drive))
            ->streamedContent();
        $this->assertStringContainsString($student->user->name, $applicationsCsv);
        $this->assertStringContainsString('selected', $applicationsCsv);

        $this->actingAs($this->cmcUser())
            ->get(route('cmc.placements'))
            ->assertStatus(200)
            ->assertSee(route('cmc.placements.export'), false);

        $placementsCsv = $this->actingAs($this->cmcUser())
            ->get(route('cmc.placements.export'))
            ->streamedContent();
        $this->assertStringContainsString((string) $selected->offered_package, $placementsCsv);
        $this->assertStringContainsString('Detail Drive', $placementsCsv);
        $this->assertStringNotContainsString('applied', $placementsCsv);

        $this->actingAs($this->cmcUser())
            ->get(route('cmc.events.registrations', $event))
            ->assertStatus(200)
            ->assertSee('Venue pending')
            ->assertDontSee('TBD')
            ->assertSee(route('cmc.events.registrations.export', $event), false);

        $registrationsCsv = $this->actingAs($this->cmcUser())
            ->get(route('cmc.events.registrations.export', $event))
            ->streamedContent();
        $this->assertStringContainsString($student->user->name, $registrationsCsv);
        $this->assertStringContainsString('registered', $registrationsCsv);
    }

    public function test_cmc_create_and_edit_forms_explain_public_workflow_impact(): void
    {
        $cmc = $this->cmcUser();
        $company = $this->company(['name' => 'Guidance Recruiter']);
        $drive = $this->drive($company, ['title' => 'Guidance Drive', 'status' => 'ongoing']);
        $event = CareerEvent::create([
            'title' => 'Guidance Event',
            'event_type' => 'workshop',
            'organizer_id' => $cmc->id,
            'event_date' => now()->addWeek()->toDateString(),
            'is_published' => true,
        ]);

        $this->actingAs($cmc)
            ->get(route('cmc.drives.create'))
            ->assertOk()
            ->assertSee('Published drive details become student-facing')
            ->assertSee('Confirm company, eligibility, application deadline, student visibility, and communication readiness before saving.', false);

        $this->actingAs($cmc)
            ->get(route('cmc.drives.edit', $drive))
            ->assertOk()
            ->assertSee('Changing an active drive can affect student applications')
            ->assertSee('Confirm recruiter, dates, eligibility, application status, and student communication impact before updating this drive.', false);

        $this->actingAs($cmc)
            ->get(route('cmc.companies.create'))
            ->assertOk()
            ->assertSee('verified recruiter contact details')
            ->assertSee('Confirm recruiter identity, contact details, industry, and future drive/application traceability before saving.', false);

        $this->actingAs($cmc)
            ->get(route('cmc.companies.edit', $company))
            ->assertOk()
            ->assertSee('Deactivating a recruiter is blocked while active drives exist')
            ->assertSee('Confirm recruiter identity, active-drive restrictions, contact changes, and placement/internship history impact before updating.', false);

        $this->actingAs($cmc)
            ->get(route('cmc.events.create'))
            ->assertOk()
            ->assertSee('Published events are visible to students')
            ->assertSee('Confirm date, venue, seats, registration deadline, student visibility, and communication readiness before saving.', false);

        $this->actingAs($cmc)
            ->get(route('cmc.events.edit', $event))
            ->assertOk()
            ->assertSee('If students have registered, date/type/venue/registration deadline changes are restricted')
            ->assertSee('Confirm registrations, event date/type/venue, registration deadline, and published student visibility before updating.', false);
    }
}
