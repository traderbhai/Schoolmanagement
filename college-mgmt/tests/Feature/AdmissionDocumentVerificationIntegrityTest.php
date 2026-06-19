<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\Program;
use App\Models\RequiredDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdmissionDocumentVerificationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function admissionUser(): User
    {
        Role::firstOrCreate(['name' => 'admission_head', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admission_head');

        return $user;
    }

    private function document(string $status = 'pending'): ApplicantDocument
    {
        $applicant = Applicant::factory()->create(['status' => 'submitted']);
        $required = RequiredDocument::create([
            'program_id' => $applicant->program_id,
            'name' => 'Academic Proof',
            'description' => 'Academic proof document',
            'is_mandatory' => true,
            'is_active' => true,
        ]);

        return ApplicantDocument::create([
            'applicant_id' => $applicant->id,
            'required_document_id' => $required->id,
            'file_path' => 'applicant-documents/proof.pdf',
            'original_name' => 'proof.pdf',
            'file_size_kb' => 20,
            'status' => $status,
            'rejection_reason' => $status === 'rejected' ? 'Existing rejection reason.' : null,
            'verified_by' => in_array($status, ['verified', 'rejected'], true) ? $this->admissionUser()->id : null,
            'verified_at' => in_array($status, ['verified', 'rejected'], true) ? now() : null,
            'uploaded_at' => now(),
            'version' => 1,
        ]);
    }

    public function test_verified_document_cannot_be_rejected_or_reverified_from_queue_routes(): void
    {
        $staff = $this->admissionUser();
        $document = $this->document('verified');
        $verifiedAt = $document->verified_at;

        $this->actingAs($staff)
            ->post(route('admission.documents.reject', $document), [
                'rejection_reason' => 'Trying to reverse verified document.',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Only pending applicant documents can be rejected.');

        $document->refresh();
        $this->assertSame('verified', $document->status);
        $this->assertNull($document->rejection_reason);
        $this->assertTrue($document->verified_at->equalTo($verifiedAt));

        $this->actingAs($staff)
            ->post(route('admission.documents.verify', $document))
            ->assertRedirect()
            ->assertSessionHas('error', 'Only pending applicant documents can be verified.');

        $this->assertSame('verified', $document->fresh()->status);
    }

    public function test_rejected_document_cannot_be_verified_from_queue_route(): void
    {
        $staff = $this->admissionUser();
        $document = $this->document('rejected');

        $this->actingAs($staff)
            ->post(route('admission.documents.verify', $document))
            ->assertRedirect()
            ->assertSessionHas('error', 'Only pending applicant documents can be verified.');

        $document->refresh();
        $this->assertSame('rejected', $document->status);
        $this->assertSame('Existing rejection reason.', $document->rejection_reason);
    }

    public function test_applicant_cannot_replace_verified_document_through_self_service_upload(): void
    {
        Storage::fake('local');
        Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);
        $document = $this->document('verified');
        $applicant = $document->applicant()->with('user')->firstOrFail();
        $applicant->user->assignRole('applicant');
        Storage::disk('local')->put($document->file_path, 'verified-file');

        $verifiedAt = $document->verified_at;
        $verifiedBy = $document->verified_by;

        $this->actingAs($applicant->user)
            ->post(route('applicant.documents.store', $document->requiredDocument), [
                'document' => UploadedFile::fake()->create('replacement.pdf', 40, 'application/pdf'),
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Cannot replace a verified document. Contact the admission office if a correction is required.');

        $document->refresh();

        $this->assertSame('verified', $document->status);
        $this->assertSame('applicant-documents/proof.pdf', $document->file_path);
        $this->assertSame('proof.pdf', $document->original_name);
        $this->assertSame(1, $document->version);
        $this->assertSame($verifiedBy, $document->verified_by);
        $this->assertTrue($document->verified_at->equalTo($verifiedAt));
        Storage::disk('local')->assertExists($document->file_path);
        $this->assertSame(1, ApplicantDocument::where('applicant_id', $applicant->id)->where('required_document_id', $document->required_document_id)->count());
    }

    public function test_applicant_cannot_upload_document_against_wrong_or_inactive_requirement(): void
    {
        Storage::fake('local');
        Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);
        $applicant = Applicant::factory()->create(['status' => 'submitted']);
        $applicant->user->assignRole('applicant');

        $otherProgramRequirement = RequiredDocument::create([
            'program_id' => Program::factory()->create()->id,
            'name' => 'Other Program Proof',
            'description' => 'This belongs to another program.',
            'is_mandatory' => true,
            'is_active' => true,
        ]);

        $inactiveRequirement = RequiredDocument::create([
            'program_id' => $applicant->program_id,
            'name' => 'Archived Proof Requirement',
            'description' => 'This requirement is no longer active.',
            'is_mandatory' => true,
            'is_active' => false,
        ]);

        foreach ([$otherProgramRequirement, $inactiveRequirement] as $requiredDocument) {
            $this->actingAs($applicant->user)
                ->from(route('applicant.documents.index'))
                ->post(route('applicant.documents.store', $requiredDocument), [
                    'document' => UploadedFile::fake()->create('proof.pdf', 40, 'application/pdf'),
                ])
                ->assertRedirect(route('applicant.documents.index'))
                ->assertSessionHas('error', 'This document requirement is not available for your application.');
        }

        $this->assertDatabaseMissing('applicant_documents', [
            'applicant_id' => $applicant->id,
            'required_document_id' => $otherProgramRequirement->id,
        ]);
        $this->assertDatabaseMissing('applicant_documents', [
            'applicant_id' => $applicant->id,
            'required_document_id' => $inactiveRequirement->id,
        ]);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_enrolled_applicant_cannot_change_documents_through_self_service_routes(): void
    {
        Storage::fake('local');
        Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);
        $document = $this->document('pending');
        $applicant = $document->applicant()->with('user')->firstOrFail();
        $applicant->update(['status' => 'enrolled']);
        $applicant->user->assignRole('applicant');
        Storage::disk('local')->put($document->file_path, 'pending-file');

        $this->actingAs($applicant->user)
            ->get(route('applicant.documents.index'))
            ->assertOk()
            ->assertSee('Document uploads are locked because your application status is')
            ->assertDontSee('Click to browse or drag &amp; drop', false)
            ->assertDontSee('Remove');

        $this->actingAs($applicant->user)
            ->post(route('applicant.documents.store', $document->requiredDocument), [
                'document' => UploadedFile::fake()->create('replacement.pdf', 40, 'application/pdf'),
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'You cannot upload documents at this stage.');

        $document->refresh();
        $this->assertSame('pending', $document->status);
        $this->assertSame('applicant-documents/proof.pdf', $document->file_path);
        $this->assertSame(1, $document->version);
        Storage::disk('local')->assertExists($document->file_path);

        $this->actingAs($applicant->user)
            ->delete(route('applicant.documents.destroy', $document))
            ->assertRedirect()
            ->assertSessionHas('error', 'You cannot remove documents at this stage.');

        $this->assertDatabaseHas('applicant_documents', [
            'id' => $document->id,
            'status' => 'pending',
            'file_path' => 'applicant-documents/proof.pdf',
        ]);
        Storage::disk('local')->assertExists($document->file_path);
    }

}
