<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Applicant;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminLegacyAdmissionAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_institution_leadership_can_still_open_legacy_admin_admission_surfaces(): void
    {
        foreach (['admin', 'director', 'dean_academics'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('admin.applicants.index'))->assertOk();
            $this->actingAs($user)->get(route('admin.admissions.index'))->assertOk();
        }
    }

    public function test_broad_admin_group_roles_cannot_read_legacy_admission_records(): void
    {
        $applicant = Applicant::factory()->create();
        $admission = $this->admission();

        foreach (['program_chair', 'hod', 'exam_cell', 'accounts_officer', 'cmc'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('admin.applicants.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.applicants.show', $applicant))->assertForbidden();
            $this->actingAs($user)->get(route('admin.admissions.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.admissions.show', $admission))->assertForbidden();
        }
    }

    public function test_broad_admin_group_roles_cannot_mutate_legacy_admission_records(): void
    {
        $applicant = Applicant::factory()->create(['notes' => 'Original notes']);
        $admission = $this->admission(['status' => 'shortlisted']);
        $activeCourse = Course::factory()->create(['is_active' => true]);

        foreach (['program_chair', 'hod', 'exam_cell', 'accounts_officer', 'cmc'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)
                ->post(route('admin.applicants.store'), [
                    'name' => "Blocked {$role}",
                    'email' => "blocked-{$role}@example.test",
                    'program_id' => $applicant->program_id,
                    'batch_id' => $applicant->batch_id,
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.applicants.notes', $applicant), ['notes' => "Changed by {$role}"])
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.admissions.store'), [
                    'applicant_name' => "Blocked {$role}",
                    'email' => "blocked-admission-{$role}@example.test",
                    'course_id' => $activeCourse->id,
                    'status' => 'enquiry',
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->patch(route('admin.admissions.status', $admission), [
                    'status' => 'withdrawn',
                    'remarks' => "Changed by {$role}",
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.admissions.convert', $admission), [
                    'enrollment_number' => "BLOCKED-{$role}",
                    'department_id' => 999,
                ])
                ->assertForbidden();
        }

        $this->assertSame('Original notes', $applicant->refresh()->notes);
        $this->assertSame('shortlisted', $admission->refresh()->status);
        $this->assertNull($admission->converted_student_id);
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseMissing('admissions', ['email' => 'blocked-admission-program_chair@example.test']);
    }

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function admission(array $overrides = []): Admission
    {
        $course = Course::factory()->create(['is_active' => true]);

        return Admission::create(array_merge([
            'applicant_name' => 'Legacy Applicant',
            'email' => 'legacy-applicant-' . uniqid() . '@example.test',
            'phone' => '9876543210',
            'course_id' => $course->id,
            'application_date' => now()->toDateString(),
            'status' => 'shortlisted',
            'remarks' => 'Ready for access-control test.',
        ], $overrides));
    }
}
