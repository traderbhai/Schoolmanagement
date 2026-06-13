<?php

namespace Tests\Feature;

use App\Models\{User, Program, Batch, Applicant};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdmissionFlowTest extends TestCase
{
    use RefreshDatabase;

    private function admissionOfficer(): User
    {
        Role::firstOrCreate(['name' => 'admission_officer', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admission_officer');
        return $user;
    }

    private function makeApplicant(?Program $program = null): array
    {
        $program = $program ?? Program::factory()->create(['is_active' => true]);
        $batch   = Batch::factory()->create(['program_id' => $program->id]);
        Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);
        $appUser = User::factory()->create();
        $appUser->assignRole('applicant');
        $applicant = Applicant::factory()->create([
            'user_id'    => $appUser->id,
            'program_id' => $program->id,
            'batch_id'   => $batch->id,
            'status'     => 'draft',
        ]);
        return [$appUser, $applicant, $program, $batch];
    }

    public function test_applicant_can_view_dashboard(): void
    {
        [$appUser] = $this->makeApplicant();
        $this->actingAs($appUser)->get(route('applicant.dashboard'))->assertStatus(200);
    }

    public function test_applicant_can_view_status_page(): void
    {
        [$appUser] = $this->makeApplicant();
        $this->actingAs($appUser)->get(route('applicant.status'))->assertStatus(200);
    }

    public function test_admission_officer_can_view_leads(): void
    {
        $officer = $this->admissionOfficer();
        $this->actingAs($officer)->get(route('admission.leads.index'))->assertStatus(200);
    }

    public function test_admission_officer_can_view_applicants(): void
    {
        $officer = $this->admissionOfficer();
        $this->actingAs($officer)->get(route('admission.applicants.index'))->assertStatus(200);
    }

    public function test_admission_officer_can_view_applicant_detail(): void
    {
        [, $applicant] = $this->makeApplicant();
        $officer = $this->admissionOfficer();
        $this->actingAs($officer)->get(route('admission.applicants.show', $applicant))->assertStatus(200);
    }

    public function test_admission_head_can_change_applicant_status(): void
    {
        $program   = Program::factory()->create(['is_active' => true]);
        $batch     = Batch::factory()->create(['program_id' => $program->id]);
        Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);
        $appUser   = User::factory()->create();
        $appUser->assignRole('applicant');
        $applicant = Applicant::factory()->create([
            'user_id'    => $appUser->id,
            'program_id' => $program->id,
            'batch_id'   => $batch->id,
            'status'     => 'submitted',  // must be submitted to transition to under_review
        ]);

        Role::firstOrCreate(['name' => 'admission_head', 'guard_name' => 'web']);
        $head = User::factory()->create();
        $head->assignRole('admission_head');
        $response = $this->actingAs($head)->post(route('admission.applicants.status', $applicant), [
            'status' => 'under_review',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('applicants', ['id' => $applicant->id, 'status' => 'under_review']);
    }
}
