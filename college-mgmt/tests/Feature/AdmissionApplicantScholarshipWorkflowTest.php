<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\ApplicantScholarship;
use App\Models\Batch;
use App\Models\Program;
use App\Models\RequiredDocument;
use App\Models\ScholarshipScheme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdmissionApplicantScholarshipWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function admissionOfficer(): User
    {
        Role::firstOrCreate(['name' => 'admission_officer', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admission_officer');

        return $user;
    }

    private function applicant(array $overrides = []): Applicant
    {
        $program = $overrides['program'] ?? Program::factory()->create(['is_active' => true]);
        $batch = $overrides['batch'] ?? Batch::factory()->create(['program_id' => $program->id]);
        Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('applicant');

        return Applicant::factory()->create(array_merge([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'academic_data' => ['cgpa' => 8.2],
            'family_data' => ['annual_income' => '300000'],
        ], array_diff_key($overrides, ['program' => true, 'batch' => true])));
    }

    private function scheme(array $overrides = []): ScholarshipScheme
    {
        return ScholarshipScheme::create(array_merge([
            'program_id' => null,
            'name' => 'Applicant Merit Scholarship',
            'scheme_code' => 'AMS-'.uniqid(),
            'type' => 'merit',
            'criteria' => 'Admission merit and verified proof.',
            'max_amount' => 25000,
            'available_seats' => 2,
            'is_active' => true,
        ], $overrides));
    }

    public function test_admission_cannot_award_inactive_or_wrong_program_scholarship(): void
    {
        $officer = $this->admissionOfficer();
        $program = Program::factory()->create(['is_active' => true]);
        $applicant = $this->applicant(['program' => $program]);
        $inactive = $this->scheme(['is_active' => false]);
        $wrongProgram = $this->scheme(['program_id' => Program::factory()->create(['is_active' => true])->id]);

        $this->actingAs($officer)
            ->post(route('admission.applicants.scholarships.store', $applicant), [
                'scheme_id' => $inactive->id,
                'awarded_amount' => 1000,
            ])
            ->assertSessionHasErrors('scheme_id');

        $this->actingAs($officer)
            ->post(route('admission.applicants.scholarships.store', $applicant), [
                'scheme_id' => $wrongProgram->id,
                'awarded_amount' => 1000,
            ])
            ->assertSessionHasErrors('scheme_id');

        $this->assertDatabaseCount('applicant_scholarships', 0);
    }

    public function test_admission_award_respects_structured_cgpa_and_income_eligibility(): void
    {
        $officer = $this->admissionOfficer();
        $applicant = $this->applicant([
            'academic_data' => ['cgpa' => 6.8],
            'family_data' => ['annual_income' => '800000'],
        ]);
        $scheme = $this->scheme([
            'min_cgpa' => 7.5,
            'max_family_income' => 500000,
        ]);

        $this->actingAs($officer)
            ->post(route('admission.applicants.scholarships.store', $applicant), [
                'scheme_id' => $scheme->id,
                'awarded_amount' => 1000,
            ])
            ->assertSessionHasErrors('scheme_id');

        $applicant->update([
            'academic_data' => ['cgpa' => 8.1],
            'family_data' => ['annual_income' => '800000'],
        ]);

        $this->actingAs($officer)
            ->post(route('admission.applicants.scholarships.store', $applicant), [
                'scheme_id' => $scheme->id,
                'awarded_amount' => 1000,
            ])
            ->assertSessionHasErrors('scheme_id');

        $this->assertDatabaseCount('applicant_scholarships', 0);
    }

    public function test_required_applicant_scholarship_proof_must_be_verified_before_award(): void
    {
        $officer = $this->admissionOfficer();
        $applicant = $this->applicant();
        $scheme = $this->scheme(['requires_document' => true]);

        $this->actingAs($officer)
            ->post(route('admission.applicants.scholarships.store', $applicant), [
                'scheme_id' => $scheme->id,
                'awarded_amount' => 1000,
            ])
            ->assertSessionHasErrors('scheme_id');

        $required = RequiredDocument::create([
            'program_id' => $applicant->program_id,
            'name' => 'Income Proof',
            'document_type' => 'income',
            'is_mandatory' => false,
            'is_active' => true,
        ]);

        ApplicantDocument::create([
            'applicant_id' => $applicant->id,
            'required_document_id' => $required->id,
            'file_path' => 'applicant-documents/proof.pdf',
            'original_name' => 'proof.pdf',
            'file_size_kb' => 10,
            'status' => 'verified',
            'verified_by' => $officer->id,
            'verified_at' => now(),
            'uploaded_at' => now(),
            'version' => 1,
        ]);

        $this->actingAs($officer)
            ->post(route('admission.applicants.scholarships.store', $applicant), [
                'scheme_id' => $scheme->id,
                'awarded_amount' => 1000,
                'notes' => 'Verified income proof.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('applicant_scholarships', [
            'applicant_id' => $applicant->id,
            'scheme_id' => $scheme->id,
            'status' => 'awarded',
            'awarded_amount' => 1000,
        ]);
    }

    public function test_admission_can_award_valid_applicant_scholarship_and_prevent_duplicate_award(): void
    {
        $officer = $this->admissionOfficer();
        $applicant = $this->applicant();
        $scheme = $this->scheme(['min_cgpa' => 7.5, 'max_family_income' => 500000]);

        $this->actingAs($officer)
            ->post(route('admission.applicants.scholarships.store', $applicant), [
                'scheme_id' => $scheme->id,
                'awarded_amount' => 12000,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, ApplicantScholarship::count());

        $this->actingAs($officer)
            ->post(route('admission.applicants.scholarships.store', $applicant), [
                'scheme_id' => $scheme->id,
                'awarded_amount' => 12000,
            ])
            ->assertSessionHasErrors('scheme_id');

        $this->assertSame(1, ApplicantScholarship::count());
    }
}
