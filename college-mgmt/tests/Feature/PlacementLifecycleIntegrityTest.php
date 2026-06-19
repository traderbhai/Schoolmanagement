<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ActivityLog;
use App\Models\Internship;
use App\Models\Placement;
use App\Models\PlacementDrive;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlacementLifecycleIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function company(): Company
    {
        return Company::create([
            'name' => 'Lifecycle Recruiter',
            'industry' => 'Technology',
            'is_active' => true,
        ]);
    }

    private function drive(array $extra = []): PlacementDrive
    {
        return PlacementDrive::create(array_merge($this->drivePayload(), $extra));
    }

    private function drivePayload(array $extra = []): array
    {
        return array_merge([
            'company_id' => $this->company()->id,
            'title' => 'Lifecycle Drive',
            'job_role' => 'Analyst',
            'package' => '6 LPA',
            'min_cgpa' => null,
            'eligibility' => 'Open to eligible students.',
            'drive_date' => now()->addWeek()->toDateString(),
            'last_apply_date' => now()->addDays(2)->toDateString(),
            'location' => 'Campus',
            'status' => 'ongoing',
            'vacancies' => 1,
            'description' => 'Campus hiring.',
        ], $extra);
    }

    public function test_cmc_cannot_delete_drive_after_student_applications_exist(): void
    {
        $drive = $this->drive();
        $student = Student::factory()->create();
        Placement::create([
            'drive_id' => $drive->id,
            'student_id' => $student->id,
            'application_status' => 'applied',
        ]);

        $this->actingAs($this->userWithRole('cmc'))
            ->delete(route('cmc.drives.destroy', $drive))
            ->assertSessionHas('error', 'Cannot delete a placement drive after students have applied. Cancel or complete it instead to preserve application history.');

        $this->assertDatabaseHas('placement_drives', ['id' => $drive->id]);
        $this->assertDatabaseHas('placements', ['drive_id' => $drive->id, 'student_id' => $student->id]);
    }

    public function test_broad_academic_role_cannot_mutate_admin_placement_or_company_routes(): void
    {
        $programChair = $this->userWithRole('program_chair');
        $company = $this->company();
        $drive = $this->drive(['company_id' => $company->id, 'status' => 'upcoming']);
        $student = Student::factory()->create(['status' => 'active']);
        $placement = Placement::create([
            'drive_id' => $drive->id,
            'student_id' => $student->id,
            'application_status' => 'applied',
        ]);

        $this->actingAs($programChair)->get(route('admin.companies.index'))->assertForbidden();
        $this->actingAs($programChair)->get(route('admin.companies.create'))->assertForbidden();
        $this->actingAs($programChair)->post(route('admin.companies.store'), [
            'name' => 'Unauthorized Recruiter',
            'is_active' => '1',
        ])->assertForbidden();
        $this->actingAs($programChair)->put(route('admin.companies.update', $company), [
            'name' => 'Changed Recruiter',
            'is_active' => '1',
        ])->assertForbidden();
        $this->actingAs($programChair)->delete(route('admin.companies.destroy', $company))->assertForbidden();

        $this->actingAs($programChair)->get(route('admin.placement-drives.index'))->assertForbidden();
        $this->actingAs($programChair)->get(route('admin.placement-drives.show', $drive))->assertForbidden();
        $this->actingAs($programChair)->post(route('admin.placement-drives.store'), $this->drivePayload([
            'company_id' => $company->id,
            'title' => 'Unauthorized Drive',
        ]))->assertForbidden();
        $this->actingAs($programChair)->put(route('admin.placement-drives.update', $drive), $this->drivePayload([
            'company_id' => $company->id,
            'title' => 'Changed Drive',
        ]))->assertForbidden();
        $this->actingAs($programChair)->post(route('admin.placement-drives.apply', $drive), [
            'student_id' => $student->id,
        ])->assertForbidden();
        $this->actingAs($programChair)->patch(route('admin.placements.update-status', $placement), [
            'application_status' => 'selected',
        ])->assertForbidden();
        $this->actingAs($programChair)->delete(route('admin.placement-drives.destroy', $drive))->assertForbidden();

        $this->assertDatabaseMissing('companies', ['name' => 'Unauthorized Recruiter']);
        $this->assertDatabaseMissing('placement_drives', ['title' => 'Unauthorized Drive']);
        $this->assertSame($company->name, $company->fresh()->name);
        $this->assertSame('upcoming', $drive->fresh()->status);
        $this->assertSame('applied', $placement->fresh()->application_status);
    }

    public function test_cmc_keeps_admin_placement_operating_access(): void
    {
        $cmc = $this->userWithRole('cmc');
        $company = $this->company();
        $drive = $this->drive(['company_id' => $company->id, 'status' => 'ongoing']);
        $student = Student::factory()->create(['status' => 'active']);

        $this->actingAs($cmc)
            ->get(route('admin.placement-drives.index'))
            ->assertOk();

        $this->actingAs($cmc)
            ->post(route('admin.placement-drives.apply', $drive), [
                'student_id' => $student->id,
            ])
            ->assertRedirect(route('admin.placement-drives.show', $drive));

        $this->assertDatabaseHas('placements', [
            'drive_id' => $drive->id,
            'student_id' => $student->id,
            'application_status' => 'applied',
        ]);
    }

    public function test_placement_drives_cannot_be_created_for_inactive_company_partners(): void
    {
        $inactiveCompany = $this->company();
        $inactiveCompany->update(['is_active' => false]);

        $this->actingAs($this->userWithRole('cmc'))
            ->from(route('cmc.drives.create'))
            ->post(route('cmc.drives.store'), $this->drivePayload([
                'company_id' => $inactiveCompany->id,
                'title' => 'Inactive CMC Company Drive',
            ]))
            ->assertRedirect(route('cmc.drives.create'))
            ->assertSessionHasErrors('placement_drive');

        $this->actingAs($this->userWithRole('admin'))
            ->from(route('admin.placement-drives.create'))
            ->post(route('admin.placement-drives.store'), $this->drivePayload([
                'company_id' => $inactiveCompany->id,
                'title' => 'Inactive Admin Company Drive',
            ]))
            ->assertRedirect(route('admin.placement-drives.create'))
            ->assertSessionHasErrors('placement_drive');

        $this->assertDatabaseMissing('placement_drives', ['title' => 'Inactive CMC Company Drive']);
        $this->assertDatabaseMissing('placement_drives', ['title' => 'Inactive Admin Company Drive']);
    }

    public function test_placement_drive_cannot_be_moved_to_inactive_company_partner(): void
    {
        $activeCompany = $this->company();
        $inactiveCompany = $this->company();
        $inactiveCompany->update(['is_active' => false]);
        $drive = $this->drive(['company_id' => $activeCompany->id, 'status' => 'upcoming']);

        $this->actingAs($this->userWithRole('admin'))
            ->from(route('admin.placement-drives.edit', $drive))
            ->put(route('admin.placement-drives.update', $drive), $this->drivePayload([
                'company_id' => $inactiveCompany->id,
                'status' => 'upcoming',
            ]))
            ->assertRedirect(route('admin.placement-drives.edit', $drive))
            ->assertSessionHasErrors('placement_drive');

        $this->assertSame($activeCompany->id, $drive->fresh()->company_id);
    }

    public function test_cmc_cannot_complete_drive_with_open_applications_or_cancel_after_selection(): void
    {
        $drive = $this->drive(['status' => 'ongoing']);
        Placement::create([
            'drive_id' => $drive->id,
            'student_id' => Student::factory()->create()->id,
            'application_status' => 'interview',
        ]);

        $this->actingAs($this->userWithRole('cmc'))
            ->put(route('cmc.drives.update', $drive), $this->drivePayload([
                'company_id' => $drive->company_id,
                'status' => 'completed',
            ]))
            ->assertSessionHasErrors('placement_drive');

        $selectedDrive = $this->drive(['status' => 'ongoing']);
        Placement::create([
            'drive_id' => $selectedDrive->id,
            'student_id' => Student::factory()->create()->id,
            'application_status' => 'selected',
        ]);

        $this->actingAs($this->userWithRole('cmc'))
            ->put(route('cmc.drives.update', $selectedDrive), $this->drivePayload([
                'company_id' => $selectedDrive->company_id,
                'status' => 'cancelled',
            ]))
            ->assertSessionHasErrors('placement_drive');

        $this->assertSame('ongoing', $drive->fresh()->status);
        $this->assertSame('ongoing', $selectedDrive->fresh()->status);
    }

    public function test_vacancies_cannot_be_reduced_below_selected_offer_count(): void
    {
        $drive = $this->drive(['status' => 'ongoing', 'vacancies' => 3]);
        foreach (range(1, 2) as $ignored) {
            Placement::create([
                'drive_id' => $drive->id,
                'student_id' => Student::factory()->create()->id,
                'application_status' => 'selected',
            ]);
        }

        $this->actingAs($this->userWithRole('cmc'))
            ->put(route('cmc.drives.update', $drive), $this->drivePayload([
                'company_id' => $drive->company_id,
                'vacancies' => 1,
            ]))
            ->assertSessionHasErrors('placement_drive');

        $this->assertSame(3, $drive->fresh()->vacancies);
    }

    public function test_placement_drive_contract_fields_are_locked_after_applications_exist(): void
    {
        $drive = $this->drive(['status' => 'ongoing', 'package' => '6 LPA', 'vacancies' => 3]);
        $replacementCompany = $this->company();
        Placement::create([
            'drive_id' => $drive->id,
            'student_id' => Student::factory()->create()->id,
            'application_status' => 'applied',
        ]);

        $this->actingAs($this->userWithRole('cmc'))
            ->from(route('cmc.drives.edit', $drive))
            ->put(route('cmc.drives.update', $drive), $this->drivePayload([
                'company_id' => $replacementCompany->id,
                'title' => 'Rewritten Drive Title',
                'job_role' => 'Different Role',
                'package' => '9 LPA',
                'drive_date' => now()->addWeeks(2)->toDateString(),
                'last_apply_date' => now()->addWeek()->toDateString(),
                'vacancies' => 4,
                'status' => 'ongoing',
            ]))
            ->assertRedirect(route('cmc.drives.edit', $drive))
            ->assertSessionHasErrors('placement_drive');

        $drive->refresh();
        $this->assertNotSame($replacementCompany->id, $drive->company_id);
        $this->assertSame('Lifecycle Drive', $drive->title);
        $this->assertSame('Analyst', $drive->job_role);
        $this->assertSame('6 LPA', $drive->package);
        $this->assertSame(3, $drive->vacancies);

        $this->actingAs($this->userWithRole('admin'))
            ->from(route('admin.placement-drives.edit', $drive))
            ->put(route('admin.placement-drives.update', $drive), $this->drivePayload([
                'company_id' => $drive->company_id,
                'title' => $drive->title,
                'job_role' => 'Admin Rewritten Role',
                'package' => $drive->package,
                'drive_date' => $drive->drive_date->toDateString(),
                'last_apply_date' => $drive->last_apply_date->toDateString(),
                'vacancies' => $drive->vacancies,
                'status' => $drive->status,
            ]))
            ->assertRedirect(route('admin.placement-drives.edit', $drive))
            ->assertSessionHasErrors('placement_drive');

        $this->assertSame('Analyst', $drive->fresh()->job_role);
    }

    public function test_safe_placement_drive_operational_notes_remain_editable_after_applications_exist(): void
    {
        $drive = $this->drive(['status' => 'ongoing']);
        Placement::create([
            'drive_id' => $drive->id,
            'student_id' => Student::factory()->create()->id,
            'application_status' => 'applied',
        ]);

        $this->actingAs($this->userWithRole('cmc'))
            ->put(route('cmc.drives.update', $drive), $this->drivePayload([
                'company_id' => $drive->company_id,
                'title' => $drive->title,
                'job_role' => $drive->job_role,
                'package' => $drive->package,
                'min_cgpa' => $drive->min_cgpa,
                'drive_date' => $drive->drive_date->toDateString(),
                'last_apply_date' => $drive->last_apply_date->toDateString(),
                'vacancies' => $drive->vacancies,
                'eligibility' => $drive->eligibility,
                'location' => 'Updated Seminar Hall',
                'description' => 'Updated reporting instructions only.',
                'status' => $drive->status,
            ]))
            ->assertRedirect(route('cmc.drives'))
            ->assertSessionHas('success', 'Drive updated.');

        $drive->refresh();
        $this->assertSame('Updated Seminar Hall', $drive->location);
        $this->assertSame('Updated reporting instructions only.', $drive->description);
    }

    public function test_final_application_decisions_cannot_be_changed_through_direct_status_routes(): void
    {
        $placement = Placement::create([
            'drive_id' => $this->drive()->id,
            'student_id' => Student::factory()->create()->id,
            'application_status' => 'selected',
            'offered_package' => 7.5,
        ]);

        $this->actingAs($this->userWithRole('cmc'))
            ->patch(route('cmc.placements.update-status', $placement), [
                'application_status' => 'rejected',
                'offered_package' => 7.5,
                'remarks' => 'Trying to reverse final decision.',
            ])
            ->assertSessionHasErrors('application_status');

        $this->assertSame('selected', $placement->fresh()->application_status);
    }

    public function test_selected_count_cannot_exceed_drive_vacancies(): void
    {
        $drive = $this->drive(['vacancies' => 1]);
        Placement::create([
            'drive_id' => $drive->id,
            'student_id' => Student::factory()->create()->id,
            'application_status' => 'selected',
        ]);
        $placement = Placement::create([
            'drive_id' => $drive->id,
            'student_id' => Student::factory()->create()->id,
            'application_status' => 'interview',
        ]);

        $this->actingAs($this->userWithRole('cmc'))
            ->patch(route('cmc.placements.update-status', $placement), [
                'application_status' => 'selected',
                'offered_package' => 6.5,
                'remarks' => 'Second offer.',
            ])
            ->assertSessionHasErrors('application_status');

        $this->assertSame('interview', $placement->fresh()->application_status);
    }

    public function test_selected_placement_requires_positive_offered_package(): void
    {
        $placement = Placement::create([
            'drive_id' => $this->drive(['vacancies' => 2])->id,
            'student_id' => Student::factory()->create()->id,
            'application_status' => 'interview',
        ]);

        $this->actingAs($this->userWithRole('cmc'))
            ->patch(route('cmc.placements.update-status', $placement), [
                'application_status' => 'selected',
                'offered_package' => null,
                'remarks' => 'Trying to select without package.',
            ])
            ->assertSessionHasErrors('application_status');

        $this->assertSame('interview', $placement->fresh()->application_status);
        $this->assertNull($placement->fresh()->offered_package);

        $this->actingAs($this->userWithRole('cmc'))
            ->patch(route('cmc.placements.update-status', $placement), [
                'application_status' => 'selected',
                'offered_package' => 6.5,
                'remarks' => 'Offer confirmed.',
            ])
            ->assertRedirect();

        $placement->refresh();
        $this->assertSame('selected', $placement->application_status);
        $this->assertEquals(6.5, $placement->offered_package);
    }

    public function test_admin_direct_add_student_respects_drive_lifecycle_and_duplicate_rules(): void
    {
        $student = Student::factory()->create(['status' => 'active']);
        $expiredDrive = $this->drive([
            'status' => 'ongoing',
            'last_apply_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('admin.placement-drives.apply', $expiredDrive), ['student_id' => $student->id])
            ->assertSessionHas('error', 'The application deadline for this drive has passed.');

        $openDrive = $this->drive(['status' => 'ongoing']);

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('admin.placement-drives.apply', $openDrive), ['student_id' => $student->id])
            ->assertRedirect(route('admin.placement-drives.show', $openDrive))
            ->assertSessionHas('success', 'Student added to drive.');

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('admin.placement-drives.apply', $openDrive), ['student_id' => $student->id])
            ->assertSessionHas('error', 'This student has already applied to this drive.');

        $this->assertSame(1, Placement::where('drive_id', $openDrive->id)->where('student_id', $student->id)->count());
    }

    public function test_admin_company_delete_archives_company_with_placement_history_instead_of_cascading(): void
    {
        $admin = $this->userWithRole('admin');
        $company = $this->company();
        $drive = $this->drive(['company_id' => $company->id]);
        $student = Student::factory()->create();
        Placement::create([
            'drive_id' => $drive->id,
            'student_id' => $student->id,
            'application_status' => 'selected',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.companies.destroy', $company))
            ->assertRedirect(route('admin.companies.index'))
            ->assertSessionHas('success', 'Company archived. Placement and internship history was preserved.');

        $this->assertDatabaseHas('companies', ['id' => $company->id, 'is_active' => false]);
        $this->assertDatabaseHas('placement_drives', ['id' => $drive->id, 'company_id' => $company->id]);
        $this->assertDatabaseHas('placements', ['drive_id' => $drive->id, 'student_id' => $student->id, 'application_status' => 'selected']);
        $this->assertTrue(ActivityLog::where('action', 'archived')->where('model_type', Company::class)->where('model_id', $company->id)->exists());
    }

    public function test_company_name_is_locked_after_placement_or_internship_history_exists(): void
    {
        $company = $this->company();
        $this->drive(['company_id' => $company->id]);

        $this->actingAs($this->userWithRole('cmc'))
            ->from(route('cmc.companies.edit', $company))
            ->put(route('cmc.companies.update', $company), [
                'name' => 'Renamed Recruiter',
                'industry' => 'Technology',
                'website' => 'https://example.com',
                'contact_person' => 'Recruiter One',
                'contact_email' => 'recruiter@example.com',
                'contact_phone' => '9999999999',
                'description' => 'Updated contact context.',
                'is_active' => '1',
            ])
            ->assertRedirect(route('cmc.companies.edit', $company))
            ->assertSessionHasErrors('name');

        $this->assertSame('Lifecycle Recruiter', $company->fresh()->name);

        $internshipCompany = $this->company();
        Internship::create([
            'student_id' => Student::factory()->create()->id,
            'company_id' => $internshipCompany->id,
            'company_name' => $internshipCompany->name,
            'role_title' => 'Intern',
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'type' => 'internship',
            'status' => 'ongoing',
        ]);

        $this->actingAs($this->userWithRole('admin'))
            ->from(route('admin.companies.edit', $internshipCompany))
            ->put(route('admin.companies.update', $internshipCompany), [
                'name' => 'Renamed Internship Recruiter',
                'industry' => 'Technology',
                'website' => 'https://example.org',
                'contact_person' => 'Recruiter Two',
                'contact_email' => 'recruiter2@example.com',
                'contact_phone' => '8888888888',
                'description' => 'Updated contact context.',
                'logo_url' => null,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.companies.edit', $internshipCompany))
            ->assertSessionHasErrors('name');

        $this->assertSame('Lifecycle Recruiter', $internshipCompany->fresh()->name);
    }

    public function test_company_with_active_drives_cannot_be_deactivated_but_safe_contact_edits_are_allowed(): void
    {
        $company = $this->company();
        $this->drive(['company_id' => $company->id, 'status' => 'ongoing']);

        $this->actingAs($this->userWithRole('cmc'))
            ->from(route('cmc.companies.edit', $company))
            ->put(route('cmc.companies.update', $company), [
                'name' => $company->name,
                'industry' => 'Technology',
                'website' => 'https://example.com',
                'contact_person' => 'Recruiter One',
                'contact_email' => 'recruiter@example.com',
                'contact_phone' => '9999999999',
                'description' => 'Updated contact context.',
            ])
            ->assertRedirect(route('cmc.companies.edit', $company))
            ->assertSessionHasErrors('is_active');

        $this->assertTrue((bool) $company->fresh()->is_active);

        $this->actingAs($this->userWithRole('cmc'))
            ->put(route('cmc.companies.update', $company), [
                'name' => $company->name,
                'industry' => 'Technology',
                'website' => 'https://example.com',
                'contact_person' => 'Updated Recruiter',
                'contact_email' => 'updated@example.com',
                'contact_phone' => '7777777777',
                'description' => 'Updated contact context.',
                'is_active' => '1',
            ])
            ->assertRedirect(route('cmc.companies'))
            ->assertSessionHas('success', 'Company updated.');

        $company->refresh();
        $this->assertTrue((bool) $company->is_active);
        $this->assertSame('Updated Recruiter', $company->contact_person);
        $this->assertSame('updated@example.com', $company->contact_email);
    }

    public function test_admin_company_delete_archives_company_with_internship_history(): void
    {
        $admin = $this->userWithRole('admin');
        $company = $this->company();
        $student = Student::factory()->create();
        Internship::create([
            'student_id' => $student->id,
            'company_id' => $company->id,
            'company_name' => $company->name,
            'role_title' => 'Intern',
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'type' => 'internship',
            'status' => 'ongoing',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.companies.destroy', $company))
            ->assertRedirect(route('admin.companies.index'))
            ->assertSessionHas('success', 'Company archived. Placement and internship history was preserved.');

        $this->assertDatabaseHas('companies', ['id' => $company->id, 'is_active' => false]);
        $this->assertDatabaseHas('internships', ['company_id' => $company->id, 'student_id' => $student->id]);
    }

    public function test_admin_can_delete_empty_company_setup_record(): void
    {
        $company = $this->company();

        $this->actingAs($this->userWithRole('admin'))
            ->delete(route('admin.companies.destroy', $company))
            ->assertRedirect(route('admin.companies.index'))
            ->assertSessionHas('success', 'Company deleted.');

        $this->assertDatabaseMissing('companies', ['id' => $company->id]);
    }
}
