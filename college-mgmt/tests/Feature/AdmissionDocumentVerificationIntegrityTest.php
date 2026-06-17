<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApplicantDocument;
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

}
