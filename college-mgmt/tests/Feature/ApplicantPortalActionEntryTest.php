<?php

namespace Tests\Feature;

use App\Models\AdmissionFeeInstallment;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\RequiredDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApplicantPortalActionEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_final_state_applicant_sees_document_upload_locked_state(): void
    {
        $applicant = $this->applicant('enrolled');
        $requiredDocument = $this->requiredDocumentFor($applicant);
        ApplicantDocument::create([
            'applicant_id' => $applicant->id,
            'required_document_id' => $requiredDocument->id,
            'file_path' => 'applicant-documents/existing.pdf',
            'original_name' => 'existing.pdf',
            'file_size_kb' => 20,
            'status' => 'pending',
            'uploaded_at' => now(),
            'version' => 1,
        ]);

        $this->actingAs($applicant->user)
            ->get(route('applicant.documents.index'))
            ->assertOk()
            ->assertSee('Document uploads are locked because your application status is')
            ->assertDontSee('Click to browse or drag &amp; drop', false)
            ->assertDontSee('Remove');
    }

    public function test_final_state_applicant_sees_fee_proof_submission_closed_reason(): void
    {
        $applicant = $this->applicant('enrolled');
        $this->installmentFor($applicant);

        $this->actingAs($applicant->user)
            ->get(route('applicant.fees.index'))
            ->assertOk()
            ->assertSee('Admission fee proof submission is closed because your application is in a final state.')
            ->assertDontSee('Submit Payment');
    }

    public function test_selected_applicant_still_sees_fee_proof_submission_action(): void
    {
        $applicant = $this->applicant('selected');
        $this->installmentFor($applicant);

        $this->actingAs($applicant->user)
            ->get(route('applicant.fees.index'))
            ->assertOk()
            ->assertSee('Submit Payment')
            ->assertDontSee('Admission fee proof submission is closed because your application is in a final state.');
    }

    private function applicant(string $status): Applicant
    {
        Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);

        $applicant = Applicant::factory()->create(['status' => $status]);
        $applicant->user->assignRole('applicant');

        return $applicant;
    }

    private function requiredDocumentFor(Applicant $applicant): RequiredDocument
    {
        return RequiredDocument::create([
            'program_id' => $applicant->program_id,
            'name' => 'Identity Proof',
            'description' => 'Government identity proof',
            'is_mandatory' => true,
            'accepted_formats' => 'pdf',
            'is_active' => true,
        ]);
    }

    private function installmentFor(Applicant $applicant): AdmissionFeeInstallment
    {
        return AdmissionFeeInstallment::create([
            'program_id' => $applicant->program_id,
            'batch_id' => $applicant->batch_id,
            'name' => 'Admission Confirmation Fee',
            'amount' => 15000,
            'installment_number' => 1,
            'due_date' => now()->addDays(5)->toDateString(),
            'is_active' => true,
        ]);
    }
}
