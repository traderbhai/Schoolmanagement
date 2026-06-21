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
    Department,
    DepartmentMember,
    DepartmentRole,
    EnrollmentConfirmation,
    Program,
    RequiredDocument,
    SelectionProcessStep,
    SelectionSession,
    ScoringParameter,
    Specialization,
    SessionApplicant,
    Student,
    Term,
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
        [, $applicant, $program] = $this->makeApplicant();
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
            ->assertSee('<h1 class="h6 mb-0">Enroll Applicant</h1>', false)
            ->assertDontSee('<h1 class="h6 mb-0">Dashboard</h1>', false)
            ->assertSee('Enrollment confirmation sequence')
            ->assertSee('Trigger Academics handoff')
            ->assertSee('Enrollment is locked until every required check is complete')
            ->assertSee('Missing verified mandatory documents')
            ->assertSee('12th Marksheet')
            ->assertSee('Confirm Enrollment')
            ->assertSee('disabled', false)
            ->assertDontSee('N/A', false)
            ->assertDontSee('Ã', false)
            ->assertDontSee('â', false);

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
            ->assertSee('Mandatory documents verified')
            ->assertSee('No specialization')
            ->assertSee('Term not selected')
            ->assertDontSee('N/A', false)
            ->assertDontSee('Ã', false)
            ->assertDontSee('â', false);

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

        $this->actingAs($officer)
            ->get(route('admission.enrollment.index'))
            ->assertOk()
            ->assertSee('Enrollment-to-student control sequence')
            ->assertSee('Academics handoff reviewed')
            ->assertSee('Current view:')
            ->assertSee('No completed enrollments match this view')
            ->assertSee('Open Selected Applicants')
            ->assertDontSee('N/A', false)
            ->assertDontSee('Ã', false)
            ->assertDontSee('â', false);

        $this->actingAs($officer)
            ->get(route('admission.enrollment.index', ['program_id' => 999999]))
            ->assertOk()
            ->assertSee('No completed enrollments match this view')
            ->assertSee('Open Selected Applicants')
            ->assertDontSee('N/A', false)
            ->assertDontSee('Ã', false)
            ->assertDontSee('â', false);

        $this->actingAs($officer)
            ->get(route('admission.enrollment.show', $confirmation))
            ->assertOk()
            ->assertSee('Enrollment handoff context')
            ->assertSee('View Student Profile')
            ->assertSee('Print Enrollment Letter')
            ->assertDontSee('N/A', false)
            ->assertDontSee('Ã', false)
            ->assertDontSee('â', false);

        Mail::assertQueued(EnrollmentConfirmed::class);
    }

    public function test_application_pdf_requires_submitted_application(): void
    {
        [, $draft] = $this->makeApplicant();
        $officer = $this->admissionOfficer();

        $this->actingAs($officer)
            ->get(route('admission.applicants.application-pdf', $draft))
            ->assertNotFound();

        $draft->update([
            'status' => 'submitted',
            'personal_data' => ['name' => 'Submitted Applicant', 'email' => 'submitted@example.test'],
            'academic_data' => ['qualification' => 'Graduation'],
        ]);

        $response = $this->actingAs($officer)
            ->get(route('admission.applicants.application-pdf', $draft));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_application_pdf_template_uses_readable_missing_data_labels(): void
    {
        [, $applicant, $program] = $this->makeApplicant();
        $applicant->update([
            'status' => 'submitted',
            'category' => 'general',
            'sub_category' => null,
            'entrance_exam_name' => 'CAT',
            'entrance_exam_roll_number' => null,
            'entrance_exam_score' => null,
            'entrance_exam_rank' => null,
            'entrance_exam_date' => null,
            'personal_data' => ['name' => '', 'email' => 'readable-pdf@example.test'],
            'academic_data' => ['qualification' => null],
        ]);

        $requiredDocument = RequiredDocument::create([
            'program_id' => $program->id,
            'name' => 'Placeholder Document',
            'description' => 'Temporary required document for PDF rendering',
            'is_mandatory' => true,
            'accepted_formats' => 'pdf',
            'max_size_kb' => 2048,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $document = ApplicantDocument::create([
            'applicant_id' => $applicant->id,
            'required_document_id' => $requiredDocument->id,
            'file_path' => 'admission-documents/missing-name.pdf',
            'original_name' => 'temporary-name.pdf',
            'file_size_kb' => 25,
            'status' => 'pending',
            'uploaded_at' => null,
        ]);

        $applicant->load(['user', 'program', 'batch', 'documents.requiredDocument']);
        $applicant->setRelation('program', null);
        $applicant->setRelation('batch', null);
        $document->original_name = null;
        $document->setRelation('requiredDocument', null);
        $applicant->setRelation('documents', collect([$document]));

        $html = view('admission.application-pdf.template', [
            'applicant' => $applicant,
            'sections' => [
                'Personal Details' => $applicant->personal_data ?? [],
                'Academic Details' => $applicant->academic_data ?? [],
                'Family Details' => [],
                'Additional Info' => [],
            ],
            'collegeName' => 'Demo Institute',
        ])->render();

        $this->assertStringContainsString('Program not selected', $html);
        $this->assertStringContainsString('Batch not selected', $html);
        $this->assertStringContainsString('Sub-category not selected', $html);
        $this->assertStringContainsString('Roll number not recorded', $html);
        $this->assertStringContainsString('Score not recorded', $html);
        $this->assertStringContainsString('Rank not recorded', $html);
        $this->assertStringContainsString('Exam date not recorded', $html);
        $this->assertStringContainsString('Document name not recorded', $html);
        $this->assertStringContainsString('Upload time not recorded', $html);
        $this->assertStringContainsString('Not provided', $html);
        $this->assertStringNotContainsString('N/A', $html);
        $this->assertStringNotContainsString('â', $html);
        $this->assertStringNotContainsString('Ã', $html);
        $this->assertStringNotContainsString('Â', $html);
        $this->assertStringNotContainsString('&bull;', $html);
        $this->assertStringNotContainsString('&mdash;', $html);
    }

    public function test_application_pdf_respects_assignment_scope(): void
    {
        Role::firstOrCreate(['name' => 'admission_counsellor', 'guard_name' => 'web']);

        $department = Department::where('code', 'ADM')->firstOrFail();
        $counsellorRole = DepartmentRole::where('department_id', $department->id)
            ->where('code', 'admission_counsellor')
            ->firstOrFail();

        $assignedCounsellor = User::factory()->create();
        $assignedCounsellor->assignRole('admission_counsellor');
        $peerCounsellor = User::factory()->create();
        $peerCounsellor->assignRole('admission_counsellor');

        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $assignedCounsellor->id,
        ]);
        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $peerCounsellor->id,
        ]);

        [, $applicant] = $this->makeApplicant();
        $applicant->update([
            'status' => 'submitted',
            'assigned_to' => $assignedCounsellor->id,
            'personal_data' => ['name' => 'Scoped PDF Applicant', 'email' => 'scoped-pdf@example.test'],
            'academic_data' => ['qualification' => 'Graduation'],
        ]);

        $this->actingAs($peerCounsellor)
            ->get(route('admission.applicants.application-pdf', $applicant))
            ->assertForbidden();

        $response = $this->actingAs($assignedCounsellor)
            ->get(route('admission.applicants.application-pdf', $applicant));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_admission_fee_receipt_respects_assignment_scope(): void
    {
        Role::firstOrCreate(['name' => 'admission_counsellor', 'guard_name' => 'web']);

        $department = Department::where('code', 'ADM')->firstOrFail();
        $counsellorRole = DepartmentRole::where('department_id', $department->id)
            ->where('code', 'admission_counsellor')
            ->firstOrFail();

        $assignedCounsellor = User::factory()->create();
        $assignedCounsellor->assignRole('admission_counsellor');
        $peerCounsellor = User::factory()->create();
        $peerCounsellor->assignRole('admission_counsellor');

        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $assignedCounsellor->id,
        ]);
        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $peerCounsellor->id,
        ]);

        [, $applicant, $program, $batch] = $this->makeApplicant();
        $applicant->update(['status' => 'selected', 'assigned_to' => $assignedCounsellor->id]);

        $installment = AdmissionFeeInstallment::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'name' => 'Scoped Receipt Fee',
            'amount' => 10000,
            'installment_number' => 1,
            'due_date' => now()->addDays(7),
            'is_active' => true,
        ]);

        $payment = AdmissionPayment::create([
            'applicant_id' => $applicant->id,
            'admission_fee_installment_id' => $installment->id,
            'amount_paid' => 10000,
            'payment_date' => now()->toDateString(),
            'payment_mode' => 'upi',
            'transaction_reference' => 'SCOPED-RCP-' . $applicant->id,
            'status' => 'verified',
            'submitted_by' => $applicant->user_id,
            'verified_by' => $assignedCounsellor->id,
            'verified_at' => now(),
        ]);

        $this->actingAs($peerCounsellor)
            ->get(route('admission.payments.receipt', $payment))
            ->assertForbidden();

        $response = $this->actingAs($assignedCounsellor)
            ->get(route('admission.payments.receipt', $payment));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_call_letter_pdf_respects_assignment_scope(): void
    {
        Role::firstOrCreate(['name' => 'admission_counsellor', 'guard_name' => 'web']);

        $department = Department::where('code', 'ADM')->firstOrFail();
        $counsellorRole = DepartmentRole::where('department_id', $department->id)
            ->where('code', 'admission_counsellor')
            ->firstOrFail();

        $assignedCounsellor = User::factory()->create();
        $assignedCounsellor->assignRole('admission_counsellor');
        $peerCounsellor = User::factory()->create();
        $peerCounsellor->assignRole('admission_counsellor');

        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $assignedCounsellor->id,
        ]);
        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $peerCounsellor->id,
        ]);

        [, $applicant, $program, $batch] = $this->makeApplicant();
        $applicant->update([
            'status' => 'shortlisted',
            'assigned_to' => $assignedCounsellor->id,
            'personal_data' => ['name' => 'Scoped Call Letter Applicant', 'email' => 'call-letter@example.test'],
        ]);
        $step = SelectionProcessStep::create([
            'program_id' => $program->id,
            'name' => 'Scoped Personal Interview',
            'type' => 'pi',
            'step_order' => 1,
            'max_score' => 100,
            'weightage' => 100,
            'is_active' => true,
        ]);
        $session = SelectionSession::create([
            'selection_process_step_id' => $step->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'session_name' => 'Scoped PI Session',
            'scheduled_date' => now()->addDays(3)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'venue' => 'Interview Room 1',
            'max_candidates' => 20,
            'status' => 'scheduled',
            'created_by' => $assignedCounsellor->id,
        ]);
        SessionApplicant::create([
            'selection_session_id' => $session->id,
            'applicant_id' => $applicant->id,
            'assigned_at' => now(),
            'attendance_status' => 'pending',
        ]);

        $this->actingAs($peerCounsellor)
            ->get(route('admission.applicants.call-letter', $applicant))
            ->assertForbidden();

        $response = $this->actingAs($assignedCounsellor)
            ->get(route('admission.applicants.call-letter', $applicant));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_call_letter_template_uses_readable_missing_schedule_labels(): void
    {
        [, $applicant] = $this->makeApplicant();
        $applicant->application_number = 'APP-CALL-READABLE';
        $applicant->setRelation('program', null);
        $applicant->setRelation('batch', null);
        $applicant->setRelation('user', User::factory()->make(['name' => 'Call Letter Applicant']));

        $session = new SelectionSession([
            'scheduled_date' => null,
            'start_time' => null,
            'end_time' => null,
            'venue' => null,
        ]);

        $html = view('admission.call-letters.template', [
            'applicant' => $applicant,
            'session' => $session,
            'collegeName' => 'Demo Institute',
        ])->render();

        $this->assertStringContainsString('Program not assigned', $html);
        $this->assertStringContainsString('Batch not assigned', $html);
        $this->assertStringContainsString('To be announced', $html);
        $this->assertStringContainsString('Time not announced', $html);
        $this->assertStringContainsString('Venue not announced', $html);
        $this->assertStringContainsString('Reporting time not announced', $html);
        $this->assertStringNotContainsString('&mdash;', $html);
        $this->assertStringNotContainsString('&ndash;', $html);
        $this->assertStringNotContainsString('N/A', $html);
        $this->assertStringNotContainsString('â', $html);
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

    public function test_enrollment_rejects_specialization_and_term_outside_applicant_scope(): void
    {
        Mail::fake();

        [, $applicant, $program, $batch] = $this->makeEnrollmentReadyApplicant();
        $otherProgram = Program::factory()->create(['is_active' => true]);
        $otherBatch = Batch::factory()->create(['program_id' => $program->id]);
        $validSpecialization = Specialization::create([
            'program_id' => $program->id,
            'name' => 'Business Analytics',
            'code' => 'BA-' . $applicant->id,
            'is_active' => true,
        ]);
        $wrongProgramSpecialization = Specialization::create([
            'program_id' => $otherProgram->id,
            'name' => 'Other Program Specialization',
            'code' => 'OPS-' . $applicant->id,
            'is_active' => true,
        ]);
        $wrongBatchTerm = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $otherBatch->id,
            'term_number' => 1,
            'name' => 'Other Batch Term',
        ]);
        $validTerm = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'Applicant Batch Term',
        ]);
        $officer = $this->admissionOfficer();

        $this->actingAs($officer)
            ->get(route('admission.enrollment.create', $applicant))
            ->assertOk()
            ->assertSee('Business Analytics')
            ->assertDontSee('Other Program Specialization');

        $this->actingAs($officer)
            ->post(route('admission.enrollment.store', $applicant), [
                'roll_number' => 'SCOPE-ENR-001',
                'specialization_id' => $wrongProgramSpecialization->id,
                'term_id' => $validTerm->id,
            ])
            ->assertSessionHasErrors('specialization_id');

        $this->actingAs($officer)
            ->post(route('admission.enrollment.store', $applicant), [
                'roll_number' => 'SCOPE-ENR-002',
                'specialization_id' => $validSpecialization->id,
                'term_id' => $wrongBatchTerm->id,
            ])
            ->assertSessionHasErrors('term_id');

        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('enrollment_confirmations', 0);

        $this->actingAs($officer)
            ->post(route('admission.enrollment.store', $applicant), [
                'roll_number' => 'SCOPE-ENR-003',
                'specialization_id' => $validSpecialization->id,
                'term_id' => $validTerm->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('students', [
            'roll_number' => 'SCOPE-ENR-003',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'specialization_id' => $validSpecialization->id,
        ]);
        $this->assertDatabaseHas('enrollment_confirmations', [
            'applicant_id' => $applicant->id,
            'term_id' => $validTerm->id,
            'status' => 'completed',
        ]);
    }

    public function test_enrollment_letter_requires_completed_confirmation(): void
    {
        $officer = $this->admissionOfficer();

        foreach (['processing', 'failed'] as $status) {
            [, $applicant, , $batch] = $this->makeApplicant();

            $confirmation = EnrollmentConfirmation::create([
                'applicant_id' => $applicant->id,
                'confirmed_by' => $officer->id,
                'confirmed_at' => now(),
                'enrollment_number' => 'ENR-STALE-' . strtoupper($status),
                'roll_number' => 'ROLL-STALE',
                'batch_id' => $batch->id,
                'status' => $status,
            ]);

            $this->actingAs($officer)
                ->from(route('admission.enrollment.show', $confirmation))
                ->get(route('admission.enrollment.letter', $confirmation))
                ->assertRedirect(route('admission.enrollment.show', $confirmation))
                ->assertSessionHas('error', 'Enrollment letter is available only after enrollment is completed.');
        }
    }

    public function test_completed_enrollment_letter_can_be_downloaded(): void
    {
        Mail::fake();

        [, $applicant] = $this->makeEnrollmentReadyApplicant();
        $officer = $this->admissionOfficer();

        $this->actingAs($officer)
            ->post(route('admission.enrollment.store', $applicant), [
                'roll_number' => 'MBA-LETTER-001',
                'notes' => 'Enrollment letter verification.',
            ])
            ->assertRedirect();

        $confirmation = EnrollmentConfirmation::firstOrFail();

        $letterHtml = view('admission.enrollment.letter', compact('confirmation'))->render();
        $this->assertStringContainsString('Enrollment Confirmation Letter', $letterHtml);
        $this->assertStringContainsString('Admissions Office | Accredited Institute of Management | enrollment@college.edu', $letterHtml);
        $this->assertStringContainsString('Rs.', $letterHtml);
        $this->assertStringContainsString('Enrollment letter verification.', $letterHtml);
        $this->assertStringNotContainsString('N/A', $letterHtml);
        $this->assertStringNotContainsString('Ã', $letterHtml);
        $this->assertStringNotContainsString('â', $letterHtml);

        $response = $this->actingAs($officer)
            ->get(route('admission.enrollment.letter', $confirmation));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_enrollment_confirmation_and_letter_respect_assignment_scope(): void
    {
        Mail::fake();
        Role::firstOrCreate(['name' => 'admission_counsellor', 'guard_name' => 'web']);

        $department = Department::where('code', 'ADM')->firstOrFail();
        $counsellorRole = DepartmentRole::where('department_id', $department->id)
            ->where('code', 'admission_counsellor')
            ->firstOrFail();

        $assignedCounsellor = User::factory()->create();
        $assignedCounsellor->assignRole('admission_counsellor');
        $peerCounsellor = User::factory()->create();
        $peerCounsellor->assignRole('admission_counsellor');

        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $assignedCounsellor->id,
        ]);
        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $peerCounsellor->id,
        ]);

        [, $applicant] = $this->makeEnrollmentReadyApplicant();
        $applicant->update(['assigned_to' => $assignedCounsellor->id]);

        $this->actingAs($peerCounsellor)
            ->get(route('admission.enrollment.create', $applicant))
            ->assertForbidden();

        $this->actingAs($assignedCounsellor)
            ->post(route('admission.enrollment.store', $applicant), [
                'roll_number' => 'SCOPE-LETTER-001',
                'notes' => 'Scoped enrollment letter.',
            ])
            ->assertRedirect();

        $confirmation = EnrollmentConfirmation::firstOrFail();

        $this->actingAs($peerCounsellor)
            ->get(route('admission.enrollment.show', $confirmation))
            ->assertForbidden();

        $this->actingAs($peerCounsellor)
            ->get(route('admission.enrollment.letter', $confirmation))
            ->assertForbidden();

        $response = $this->actingAs($assignedCounsellor)
            ->get(route('admission.enrollment.letter', $confirmation));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_enrollment_index_respects_assignment_scope(): void
    {
        Mail::fake();
        Role::firstOrCreate(['name' => 'admission_counsellor', 'guard_name' => 'web']);

        $department = Department::where('code', 'ADM')->firstOrFail();
        $counsellorRole = DepartmentRole::where('department_id', $department->id)
            ->where('code', 'admission_counsellor')
            ->firstOrFail();

        $assignedCounsellor = User::factory()->create();
        $assignedCounsellor->assignRole('admission_counsellor');
        $peerCounsellor = User::factory()->create();
        $peerCounsellor->assignRole('admission_counsellor');

        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $assignedCounsellor->id,
        ]);
        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $peerCounsellor->id,
        ]);

        [$firstUser, $firstApplicant, $program, $batch] = $this->makeEnrollmentReadyApplicant();
        [$secondUser, $secondApplicant] = $this->makeEnrollmentReadyApplicant($program, $batch);

        $firstUser->update(['name' => 'Scoped Hidden Student']);
        $secondUser->update(['name' => 'Scoped Visible Student']);
        $firstApplicant->update(['assigned_to' => $assignedCounsellor->id]);
        $secondApplicant->update(['assigned_to' => $peerCounsellor->id]);

        $this->actingAs($assignedCounsellor)
            ->post(route('admission.enrollment.store', $firstApplicant), [
                'roll_number' => 'SCOPE-INDEX-001',
                'notes' => 'Hidden from peer list.',
            ])
            ->assertRedirect();

        $this->actingAs($peerCounsellor)
            ->post(route('admission.enrollment.store', $secondApplicant), [
                'roll_number' => 'SCOPE-INDEX-002',
                'notes' => 'Visible to assigned counsellor.',
            ])
            ->assertRedirect();

        $response = $this->actingAs($peerCounsellor)
            ->get(route('admission.enrollment.index'));

        $response->assertOk()
            ->assertSee('Scoped Visible Student')
            ->assertSee('SCOPE-INDEX-002')
            ->assertDontSee('Scoped Hidden Student')
            ->assertDontSee('SCOPE-INDEX-001')
            ->assertViewHas('totalEnrolled', 1)
            ->assertViewHas('thisMonth', 1);

        $this->assertCount(1, $response->viewData('confirmations'));
    }

    public function test_assessment_scoring_respects_assignment_scope(): void
    {
        Role::firstOrCreate(['name' => 'admission_counsellor', 'guard_name' => 'web']);

        $department = Department::where('code', 'ADM')->firstOrFail();
        $counsellorRole = DepartmentRole::where('department_id', $department->id)
            ->where('code', 'admission_counsellor')
            ->firstOrFail();

        $assignedCounsellor = User::factory()->create();
        $assignedCounsellor->assignRole('admission_counsellor');
        $peerCounsellor = User::factory()->create();
        $peerCounsellor->assignRole('admission_counsellor');

        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $assignedCounsellor->id,
        ]);
        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $peerCounsellor->id,
        ]);

        [$firstUser, $firstApplicant, $program, $batch] = $this->makeApplicant();
        [$secondUser, $secondApplicant] = $this->makeApplicant($program);

        $firstUser->update(['name' => 'Scoped Score Hidden']);
        $secondUser->update(['name' => 'Scoped Score Visible']);
        $firstApplicant->update([
            'batch_id' => $batch->id,
            'status' => 'shortlisted',
            'assigned_to' => $assignedCounsellor->id,
        ]);
        $secondApplicant->update([
            'batch_id' => $batch->id,
            'status' => 'shortlisted',
            'assigned_to' => $peerCounsellor->id,
        ]);

        $step = SelectionProcessStep::create([
            'program_id' => $program->id,
            'name' => 'Scoped PI Scoring',
            'type' => 'pi',
            'step_order' => 1,
            'max_score' => 100,
            'weightage' => 100,
            'is_active' => true,
        ]);
        $parameter = ScoringParameter::create([
            'selection_process_step_id' => $step->id,
            'name' => 'Communication',
            'max_score' => 10,
            'sort_order' => 1,
        ]);
        $session = SelectionSession::create([
            'selection_process_step_id' => $step->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'session_name' => 'Scoped Assessment Session',
            'scheduled_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'venue' => 'Assessment Room',
            'max_candidates' => 20,
            'status' => 'ongoing',
            'created_by' => $assignedCounsellor->id,
        ]);
        foreach ([$firstApplicant, $secondApplicant] as $applicant) {
            SessionApplicant::create([
                'selection_session_id' => $session->id,
                'applicant_id' => $applicant->id,
                'assigned_at' => now(),
                'attendance_status' => 'present',
            ]);
        }

        $this->actingAs($peerCounsellor)
            ->get(route('admission.sessions.scores', $session))
            ->assertOk()
            ->assertSee('Scoped Score Visible')
            ->assertDontSee('Scoped Score Hidden');

        $this->actingAs($peerCounsellor)
            ->get(route('admission.applicants.scorecard', $firstApplicant))
            ->assertForbidden();

        $this->actingAs($peerCounsellor)
            ->post(route('admission.sessions.scores.save', $session), [
                'scores' => [
                    $firstApplicant->id => [
                        'param_' . $parameter->id => 8,
                        'remarks' => 'Out-of-scope score attempt.',
                    ],
                ],
            ])
            ->assertForbidden();

        $this->actingAs($peerCounsellor)
            ->post(route('admission.sessions.scores.save', $session), [
                'scores' => [
                    $secondApplicant->id => [
                        'param_' . $parameter->id => 9,
                        'remarks' => 'Scoped score allowed.',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('applicant_scores', [
            'applicant_id' => $firstApplicant->id,
            'selection_session_id' => $session->id,
        ]);
        $this->assertDatabaseHas('applicant_scores', [
            'applicant_id' => $secondApplicant->id,
            'selection_session_id' => $session->id,
            'total_score' => 9,
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
