<?php

namespace Tests\Feature;

use App\Mail\EnrollmentConfirmed;
use App\Models\{
    ActivityLog,
    AdmissionFeeInstallment,
    AdmissionPayment,
    Applicant,
    ApplicantDocument,
    Batch,
    Course,
    EnrollmentConfirmation,
    Program,
    RequiredDocument,
    Student,
    User
};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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

    private function makeEnrollmentReadyApplicant(?Program $program = null, ?Batch $batch = null): array
    {
        foreach (['applicant', 'admission_officer', 'student'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $program = $program ?? Program::factory()->create(['is_active' => true]);
        $batch = $batch ?? Batch::factory()->create(['program_id' => $program->id]);
        Course::firstOrCreate(
            ['code' => $program->code],
            [
                'department_id' => $program->department_id,
                'name' => $program->name . ' Course',
                'description' => 'Course mapped for admission enrollment tests.',
                'duration_years' => $program->duration_years,
                'total_semesters' => $program->total_terms,
                'is_active' => true,
            ]
        );

        $installment = AdmissionFeeInstallment::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'name' => 'Enrollment Readiness Fee',
            'amount' => 25000,
            'installment_number' => 1,
            'due_date' => now()->addDays(7),
            'is_active' => true,
        ]);

        $applicantUser = User::factory()->create();
        $applicantUser->assignRole('applicant');

        $applicant = Applicant::factory()->create([
            'user_id' => $applicantUser->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'selected',
            'personal_data' => [
                'full_name' => $applicantUser->name,
                'email' => $applicantUser->email,
            ],
        ]);

        AdmissionPayment::create([
            'applicant_id' => $applicant->id,
            'admission_fee_installment_id' => $installment->id,
            'amount_paid' => 25000,
            'payment_date' => now()->toDateString(),
            'payment_mode' => 'upi',
            'transaction_reference' => 'READY-' . $applicant->id,
            'status' => 'verified',
            'submitted_by' => $applicantUser->id,
            'verified_by' => null,
            'verified_at' => now(),
        ]);

        return [$applicantUser, $applicant, $program, $batch];
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

    public function test_applicant_payment_document_verification_and_enrollment_flow(): void
    {
        Mail::fake();

        foreach (['applicant', 'admission_officer', 'student'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $program = Program::factory()->create(['is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        Course::factory()->create([
            'department_id' => $program->department_id,
            'code' => $program->code,
        ]);

        $installment = AdmissionFeeInstallment::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'name' => 'Admission Confirmation Fee',
            'amount' => 25000,
            'installment_number' => 1,
            'due_date' => now()->addDays(7),
            'is_active' => true,
        ]);

        $requiredDocument = RequiredDocument::create([
            'program_id' => $program->id,
            'name' => '12th Marksheet',
            'description' => 'Required academic proof',
            'is_mandatory' => true,
            'accepted_formats' => 'pdf',
            'max_size_kb' => 2048,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $applicantUser = User::factory()->create([
            'name' => 'Priya Sharma',
            'email' => 'priya.launch@example.com',
        ]);
        $applicantUser->assignRole('applicant');

        $applicant = Applicant::factory()->create([
            'user_id' => $applicantUser->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'shortlisted',
            'personal_data' => [
                'full_name' => 'Priya Sharma',
                'email' => $applicantUser->email,
                'phone' => '9876543210',
                'gender' => 'female',
                'address' => 'Delhi',
            ],
            'family_data' => [
                'guardian_name' => 'Ravi Sharma',
                'guardian_phone' => '9876500000',
            ],
        ]);

        $document = ApplicantDocument::create([
            'applicant_id' => $applicant->id,
            'required_document_id' => $requiredDocument->id,
            'file_path' => "applicant-documents/{$applicant->id}/marksheet.pdf",
            'original_name' => 'marksheet.pdf',
            'file_size_kb' => 120,
            'status' => 'pending',
            'uploaded_at' => now(),
            'version' => 1,
        ]);

        $this->actingAs($applicantUser)
            ->post(route('applicant.fees.store', $installment), [
                'amount_paid' => 25000,
                'payment_date' => now()->toDateString(),
                'payment_mode' => 'upi',
                'transaction_reference' => 'UPI-ADMISSION-001',
                'bank_name' => 'Demo Bank',
            ])
            ->assertRedirect(route('applicant.fees.index'));

        $payment = AdmissionPayment::firstOrFail();
        $this->assertSame('pending', $payment->status);

        $officer = $this->admissionOfficer();

        $this->actingAs($officer)
            ->post(route('admission.payments.verify', $payment), [
                'verification_notes' => 'Bank reference matched.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('admission_payments', [
            'id' => $payment->id,
            'status' => 'verified',
            'verified_by' => $officer->id,
        ]);
        $this->assertDatabaseHas('applicants', [
            'id' => $applicant->id,
            'status' => 'selected',
        ]);

        $this->actingAs($officer)
            ->get(route('admission.enrollment.create', $applicant))
            ->assertStatus(200)
            ->assertSee('<h6>Enroll Applicant</h6>', false)
            ->assertDontSee('<h6>Dashboard</h6>', false)
            ->assertSee('Enrollment is locked until every required check is complete')
            ->assertSee('Missing verified mandatory documents')
            ->assertSee('12th Marksheet')
            ->assertSee('Confirm Enrollment')
            ->assertSee('disabled', false);

        $this->actingAs($officer)
            ->post(route('admission.enrollment.store', $applicant), [
                'roll_number' => 'MBA-2026-001',
                'notes' => 'Attempt before mandatory document verification.',
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('enrollment_confirmations', 0);

        $this->actingAs($officer)
            ->post(route('admission.documents.verify', $document))
            ->assertRedirect();

        $this->actingAs($officer)
            ->get(route('admission.enrollment.create', $applicant))
            ->assertStatus(200)
            ->assertSee('Priya Sharma')
            ->assertSee('Ready for enrollment')
            ->assertSee('Mandatory documents verified');

        $this->actingAs($officer)
            ->post(route('admission.enrollment.store', $applicant), [
                'roll_number' => 'MBA-2026-001',
                'notes' => 'Enrollment completed after payment and document verification.',
            ])
            ->assertRedirect();

        $student = Student::firstOrFail();
        $confirmation = EnrollmentConfirmation::firstOrFail();

        $this->assertSame($applicantUser->id, $student->user_id);
        $this->assertSame($program->id, $student->program_id);
        $this->assertSame($batch->id, $student->batch_id);
        $this->assertSame('MBA-2026-001', $student->roll_number);
        $this->assertSame('active', $student->status);
        $this->assertSame($student->id, $confirmation->student_id);
        $this->assertSame('completed', $confirmation->status);
        $this->assertTrue($applicantUser->fresh()->hasRole('student'));
        $this->assertFalse($applicantUser->fresh()->hasRole('applicant'));

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'verify_payment',
            'model_type' => AdmissionPayment::class,
            'model_id' => $payment->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'enrollment',
            'model_type' => EnrollmentConfirmation::class,
            'model_id' => $confirmation->id,
        ]);

        Mail::assertQueued(EnrollmentConfirmed::class);
    }

    public function test_completed_enrollment_cannot_be_created_twice(): void
    {
        Mail::fake();

        [, $applicant] = $this->makeEnrollmentReadyApplicant();
        $officer = $this->admissionOfficer();

        $this->actingAs($officer)
            ->post(route('admission.enrollment.store', $applicant), [
                'roll_number' => 'ENR-LOCK-001',
                'notes' => 'First enrollment.',
            ])
            ->assertRedirect();

        $this->actingAs($officer)
            ->post(route('admission.enrollment.store', $applicant), [
                'roll_number' => 'ENR-LOCK-002',
                'notes' => 'Duplicate enrollment attempt.',
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('enrollment_confirmations', 1);
        $this->assertDatabaseCount('admission_handoff_records', 1);
        $this->assertDatabaseMissing('students', [
            'roll_number' => 'ENR-LOCK-002',
        ]);
    }

    public function test_enrolled_applicant_status_cannot_be_changed_after_completion(): void
    {
        Mail::fake();

        [, $applicant] = $this->makeEnrollmentReadyApplicant();
        $officer = $this->admissionOfficer();

        $this->actingAs($officer)
            ->post(route('admission.enrollment.store', $applicant), [
                'roll_number' => 'ENR-FINAL-001',
                'notes' => 'Final enrollment.',
            ])
            ->assertRedirect();

        Role::firstOrCreate(['name' => 'admission_head', 'guard_name' => 'web']);
        $head = User::factory()->create();
        $head->assignRole('admission_head');

        $this->actingAs($head)
            ->post(route('admission.applicants.status', $applicant), [
                'status' => 'withdrawn',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Completed enrollments are locked. Use the academic student lifecycle or audited cancellation workflow instead of changing applicant status.');

        $this->assertSame('selected', $applicant->fresh()->status);
        $this->assertDatabaseHas('enrollment_confirmations', [
            'applicant_id' => $applicant->id,
            'status' => 'completed',
        ]);
    }

    public function test_enrollment_rejects_duplicate_roll_number_in_same_batch(): void
    {
        Mail::fake();

        [, $firstApplicant, $program, $batch] = $this->makeEnrollmentReadyApplicant();
        [, $secondApplicant] = $this->makeEnrollmentReadyApplicant($program, $batch);
        $officer = $this->admissionOfficer();

        $this->actingAs($officer)
            ->post(route('admission.enrollment.store', $firstApplicant), [
                'roll_number' => 'BATCH-ROLL-001',
            ])
            ->assertRedirect();

        $this->actingAs($officer)
            ->post(route('admission.enrollment.store', $secondApplicant), [
                'roll_number' => 'BATCH-ROLL-001',
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('enrollment_confirmations', 1);
        $this->assertDatabaseHas('applicants', [
            'id' => $secondApplicant->id,
            'status' => 'selected',
        ]);
    }
}
