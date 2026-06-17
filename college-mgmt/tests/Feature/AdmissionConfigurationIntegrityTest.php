<?php

namespace Tests\Feature;

use App\Models\AdmissionFeeInstallment;
use App\Models\AdmissionPayment;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\ApplicantScore;
use App\Models\RequiredDocument;
use App\Models\SelectionProcessStep;
use App\Models\SelectionSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdmissionConfigurationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_required_document_with_applicant_upload_history_cannot_be_deleted_or_recontracted(): void
    {
        $admin = $this->admin();
        $applicant = Applicant::factory()->create(['status' => 'submitted']);
        $document = RequiredDocument::create([
            'program_id' => $applicant->program_id,
            'name' => 'Identity Proof',
            'description' => 'Government ID',
            'is_mandatory' => true,
            'accepted_formats' => 'pdf,jpg',
            'max_size_kb' => 2048,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        ApplicantDocument::create([
            'applicant_id' => $applicant->id,
            'required_document_id' => $document->id,
            'file_path' => 'applicant-documents/id.pdf',
            'original_name' => 'id.pdf',
            'file_size_kb' => 120,
            'status' => 'verified',
            'verified_by' => $admin->id,
            'verified_at' => now(),
            'uploaded_at' => now(),
            'version' => 1,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.admission-config.documents.destroy', $document))
            ->assertRedirect()
            ->assertSessionHas('error', 'This document requirement is linked to applicant uploads and cannot be deleted.');

        $this->assertDatabaseHas('required_documents', ['id' => $document->id]);
        $this->assertDatabaseHas('applicant_documents', ['required_document_id' => $document->id]);

        $this->actingAs($admin)
            ->put(route('admin.admission-config.documents.update', $document), [
                'name' => 'Changed Identity Proof',
                'description' => 'Updated label',
                'is_mandatory' => false,
                'accepted_formats' => 'pdf',
                'max_size_kb' => 1024,
                'sort_order' => 2,
                'is_active' => false,
            ])
            ->assertSessionHasErrors('required_document');

        $document->refresh();
        $this->assertSame('Identity Proof', $document->name);
        $this->assertTrue((bool) $document->is_mandatory);
        $this->assertSame('pdf,jpg', $document->accepted_formats);
        $this->assertTrue((bool) $document->is_active);
    }

    public function test_used_required_document_can_still_receive_safe_description_updates(): void
    {
        $admin = $this->admin();
        $applicant = Applicant::factory()->create();
        $document = RequiredDocument::create([
            'program_id' => $applicant->program_id,
            'name' => 'Address Proof',
            'description' => 'Old note',
            'is_mandatory' => false,
            'accepted_formats' => 'pdf',
            'max_size_kb' => 2048,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        ApplicantDocument::create([
            'applicant_id' => $applicant->id,
            'required_document_id' => $document->id,
            'file_path' => 'applicant-documents/address.pdf',
            'original_name' => 'address.pdf',
            'file_size_kb' => 100,
            'status' => 'pending',
            'uploaded_at' => now(),
            'version' => 1,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.admission-config.documents.update', $document), [
                'name' => 'Address Proof',
                'description' => 'Clarified instructions for applicants.',
                'is_mandatory' => false,
                'accepted_formats' => 'pdf',
                'max_size_kb' => 2048,
                'sort_order' => 3,
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertSame('Clarified instructions for applicants.', $document->fresh()->description);
        $this->assertSame(3, $document->fresh()->sort_order);
    }

    public function test_selection_step_with_sessions_or_scores_cannot_be_deleted_or_restructured(): void
    {
        $admin = $this->admin();
        $applicant = Applicant::factory()->create(['status' => 'shortlisted']);
        $step = SelectionProcessStep::create([
            'program_id' => $applicant->program_id,
            'name' => 'Personal Interview',
            'type' => 'pi',
            'step_order' => 1,
            'max_score' => 100,
            'weightage' => 50,
            'instructions' => 'Interview round',
            'is_active' => true,
        ]);
        $session = SelectionSession::create([
            'selection_process_step_id' => $step->id,
            'program_id' => $applicant->program_id,
            'batch_id' => $applicant->batch_id,
            'session_name' => 'PI Panel A',
            'scheduled_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'completed',
            'created_by' => $admin->id,
        ]);
        ApplicantScore::create([
            'applicant_id' => $applicant->id,
            'selection_session_id' => $session->id,
            'selection_process_step_id' => $step->id,
            'scored_by' => $admin->id,
            'total_score' => 80,
            'max_possible_score' => 100,
            'percentage' => 80,
            'is_final' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.admission-config.steps.destroy', $step))
            ->assertRedirect()
            ->assertSessionHas('error', 'This selection step has sessions or scores and cannot be deleted.');

        $this->assertDatabaseHas('selection_process_steps', ['id' => $step->id]);
        $this->assertDatabaseHas('selection_sessions', ['id' => $session->id]);
        $this->assertDatabaseHas('applicant_scores', ['selection_process_step_id' => $step->id]);

        $this->actingAs($admin)
            ->put(route('admin.admission-config.steps.update', $step), [
                'name' => 'Personal Interview',
                'type' => 'gd',
                'step_order' => 2,
                'max_score' => 200,
                'weightage' => 60,
                'instructions' => 'Trying to restructure completed scoring.',
                'is_active' => false,
            ])
            ->assertSessionHasErrors('selection_step');

        $step->refresh();
        $this->assertSame('pi', $step->type);
        $this->assertSame(1, $step->step_order);
        $this->assertSame(100, $step->max_score);
        $this->assertTrue((bool) $step->is_active);
    }

    public function test_admission_fee_installment_with_payment_history_cannot_be_deleted_or_financially_changed(): void
    {
        $admin = $this->admin();
        $applicant = Applicant::factory()->create(['status' => 'selected']);
        $installment = AdmissionFeeInstallment::create([
            'program_id' => $applicant->program_id,
            'batch_id' => $applicant->batch_id,
            'name' => 'Admission Confirmation Fee',
            'amount' => 15000,
            'installment_number' => 1,
            'due_date' => now()->addDays(7)->toDateString(),
            'is_active' => true,
        ]);
        AdmissionPayment::create([
            'applicant_id' => $applicant->id,
            'admission_fee_installment_id' => $installment->id,
            'amount_paid' => 15000,
            'payment_date' => now()->toDateString(),
            'payment_mode' => 'cash',
            'transaction_reference' => 'ADM-CONFIG-LOCK',
            'status' => 'verified',
            'verified_by' => $admin->id,
            'verified_at' => now(),
            'submitted_by' => $applicant->user_id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.admission-config.fee.destroy', $installment))
            ->assertRedirect()
            ->assertSessionHas('error', 'This installment is linked to admission payments and cannot be deleted.');

        $this->assertDatabaseHas('admission_fee_installments', ['id' => $installment->id]);
        $this->assertDatabaseHas('admission_payments', ['transaction_reference' => 'ADM-CONFIG-LOCK']);

        $this->actingAs($admin)
            ->put(route('admin.admission-config.fee.update', $installment), [
                'name' => 'Admission Confirmation Fee',
                'amount' => 20000,
                'installment_number' => 2,
                'batch_id' => null,
                'due_date' => now()->addDays(10)->toDateString(),
                'description' => 'Trying to change paid installment.',
                'is_active' => false,
            ])
            ->assertSessionHasErrors('admission_fee_installment');

        $installment->refresh();
        $this->assertSame('15000.00', number_format((float) $installment->amount, 2, '.', ''));
        $this->assertSame(1, $installment->installment_number);
        $this->assertSame($applicant->batch_id, $installment->batch_id);
        $this->assertTrue((bool) $installment->is_active);

        $this->actingAs($admin)
            ->put(route('admission.fee-installments.update', $installment), [
                'name' => 'Admission Confirmation Fee',
                'amount' => 20000,
                'installment_number' => 1,
                'batch_id' => $applicant->batch_id,
                'due_date' => now()->addDays(10)->toDateString(),
                'description' => 'Staff route bypass attempt.',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('admission_fee_installment');

        $this->assertSame('15000.00', number_format((float) $installment->fresh()->amount, 2, '.', ''));
    }
}
