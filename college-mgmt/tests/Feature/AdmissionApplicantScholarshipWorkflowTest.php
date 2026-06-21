<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\ApplicantScholarship;
use App\Models\Batch;
use App\Models\Department;
use App\Models\DepartmentMember;
use App\Models\DepartmentRole;
use App\Models\Program;
use App\Models\RequiredDocument;
use App\Models\ScholarshipScheme;
use App\Models\Student;
use App\Models\StudentScholarshipApplication;
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

    private function admissionUserWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

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
        $officer = $this->admissionUserWithRole('admission_head');
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

    public function test_applicant_scholarship_award_must_have_positive_amount(): void
    {
        $officer = $this->admissionOfficer();
        $applicant = $this->applicant();
        $scheme = $this->scheme();

        $this->actingAs($officer)
            ->post(route('admission.applicants.scholarships.store', $applicant), [
                'scheme_id' => $scheme->id,
                'awarded_amount' => 0,
            ])
            ->assertSessionHasErrors('awarded_amount');

        $this->assertSame(0, ApplicantScholarship::count());
    }

    public function test_applicant_scholarship_cannot_be_awarded_after_final_negative_applicant_state(): void
    {
        $officer = $this->admissionOfficer();
        $scheme = $this->scheme();

        foreach (['rejected', 'withdrawn', 'enrolled'] as $status) {
            $applicant = $this->applicant(['status' => $status]);

            $this->actingAs($officer)
                ->post(route('admission.applicants.scholarships.store', $applicant), [
                    'scheme_id' => $scheme->id,
                    'awarded_amount' => 1000,
                ])
                ->assertSessionHasErrors('scheme_id');
        }

        $this->assertSame(0, ApplicantScholarship::count());
    }

    public function test_applicant_scholarship_routes_respect_admission_hierarchy_scope(): void
    {
        $department = Department::where('code', 'ADM')->firstOrFail();
        $manager = $this->admissionUserWithRole('admission_manager');
        $directReport = $this->admissionUserWithRole('admission_counsellor');
        $outsideCounsellor = $this->admissionUserWithRole('admission_counsellor');
        $managerRole = DepartmentRole::where('department_id', $department->id)->where('code', 'admission_manager')->firstOrFail();
        $counsellorRole = DepartmentRole::where('department_id', $department->id)->where('code', 'admission_counsellor')->firstOrFail();
        $managerMember = DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $managerRole->id,
            'user_id' => $manager->id,
        ]);
        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $directReport->id,
            'reports_to_member_id' => $managerMember->id,
        ]);
        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $outsideCounsellor->id,
        ]);

        $scheme = $this->scheme(['name' => 'Scoped Applicant Scholarship']);
        $visibleApplicant = $this->applicant(['assigned_to' => $directReport->id]);
        $visibleApplicant->user->update(['name' => 'Visible Scholarship Applicant']);
        $hiddenApplicant = $this->applicant(['assigned_to' => $outsideCounsellor->id]);
        $hiddenApplicant->user->update(['name' => 'Hidden Scholarship Applicant']);

        $visibleAward = ApplicantScholarship::create([
            'applicant_id' => $visibleApplicant->id,
            'scheme_id' => $scheme->id,
            'awarded_amount' => 2000,
            'status' => 'awarded',
            'awarded_by' => $directReport->id,
            'awarded_at' => now(),
        ]);
        $hiddenAward = ApplicantScholarship::create([
            'applicant_id' => $hiddenApplicant->id,
            'scheme_id' => $scheme->id,
            'awarded_amount' => 3000,
            'status' => 'awarded',
            'awarded_by' => $outsideCounsellor->id,
            'awarded_at' => now(),
        ]);

        $this->actingAs($manager)
            ->get(route('admission.scholarship-disbursements.index'))
            ->assertOk()
            ->assertSee('Visible Scholarship Applicant')
            ->assertDontSee('Hidden Scholarship Applicant')
            ->assertSee('2,000')
            ->assertDontSee('5,000');

        $this->actingAs($manager)
            ->post(route('admission.applicants.scholarships.store', $hiddenApplicant), [
                'scheme_id' => $scheme->id,
                'awarded_amount' => 1000,
            ])
            ->assertForbidden();

        $this->actingAs($manager)
            ->delete(route('admission.scholarships.destroy', $hiddenAward))
            ->assertForbidden();

        $this->actingAs($manager)
            ->post(route('admission.scholarships.disburse', $hiddenAward), [
                'disbursement_ref' => 'UTR-HIDDEN-SCHOLARSHIP',
            ])
            ->assertForbidden();

        $this->assertSame('awarded', $hiddenAward->fresh()->status);
        $this->assertNull($hiddenAward->fresh()->disbursement_ref);

        $this->actingAs($manager)
            ->post(route('admission.scholarships.disburse', $visibleAward), [
                'disbursement_ref' => 'UTR-VISIBLE-SCHOLARSHIP',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('disbursed', $visibleAward->fresh()->status);
        $this->assertSame('UTR-VISIBLE-SCHOLARSHIP', $visibleAward->fresh()->disbursement_ref);
    }

    public function test_applicant_scholarship_disbursement_rechecks_award_integrity_and_preserves_notes(): void
    {
        $officer = $this->admissionOfficer();
        $applicant = $this->applicant();
        $scheme = $this->scheme(['max_amount' => 5000]);
        $award = ApplicantScholarship::create([
            'applicant_id' => $applicant->id,
            'scheme_id' => $scheme->id,
            'awarded_amount' => 8000,
            'status' => 'awarded',
            'awarded_by' => $officer->id,
            'awarded_at' => now(),
            'notes' => 'Award approved by committee.',
        ]);

        $this->actingAs($officer)
            ->post(route('admission.scholarships.disburse', $award), [
                'disbursement_ref' => 'UTR-BLOCKED',
                'notes' => 'Trying to pay over-cap award.',
            ])
            ->assertSessionHasErrors('scholarship');

        $this->assertSame('awarded', $award->fresh()->status);
        $this->assertNull($award->fresh()->disbursement_ref);

        $award->update(['awarded_amount' => 4000]);

        $this->actingAs($officer)
            ->post(route('admission.scholarships.disburse', $award), [
                'disbursement_ref' => 'UTR-APPLICANT-SCH-1',
                'notes' => 'Paid through bank transfer.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $award->refresh();
        $this->assertSame('disbursed', $award->status);
        $this->assertSame('UTR-APPLICANT-SCH-1', $award->disbursement_ref);
        $this->assertStringContainsString('Award approved by committee.', $award->notes);
        $this->assertStringContainsString('Paid through bank transfer.', $award->notes);
    }

    public function test_applicant_scholarship_disbursement_rejects_blank_reference_after_trimming(): void
    {
        $officer = $this->admissionOfficer();
        $award = ApplicantScholarship::create([
            'applicant_id' => $this->applicant()->id,
            'scheme_id' => $this->scheme()->id,
            'awarded_amount' => 4000,
            'status' => 'awarded',
            'awarded_by' => $officer->id,
            'awarded_at' => now(),
        ]);

        $this->actingAs($officer)
            ->post(route('admission.scholarships.disburse', $award), [
                'disbursement_ref' => '   ',
            ])
            ->assertSessionHasErrors('disbursement_ref');

        $award->refresh();
        $this->assertSame('awarded', $award->status);
        $this->assertNull($award->disbursement_ref);
        $this->assertNull($award->disbursed_at);
    }

    public function test_applicant_scholarship_disbursement_rechecks_scheme_proof_and_final_applicant_state(): void
    {
        $officer = $this->admissionOfficer();

        $inactiveScheme = $this->scheme(['is_active' => false]);
        $inactiveSchemeAward = ApplicantScholarship::create([
            'applicant_id' => $this->applicant()->id,
            'scheme_id' => $inactiveScheme->id,
            'awarded_amount' => 1000,
            'status' => 'awarded',
            'awarded_by' => $officer->id,
            'awarded_at' => now(),
        ]);

        $this->actingAs($officer)
            ->post(route('admission.scholarships.disburse', $inactiveSchemeAward), [
                'disbursement_ref' => 'UTR-INACTIVE-SCHEME',
            ])
            ->assertSessionHasErrors('scholarship');

        $this->assertSame('awarded', $inactiveSchemeAward->fresh()->status);
        $this->assertNull($inactiveSchemeAward->fresh()->disbursement_ref);

        $proofScheme = $this->scheme(['requires_document' => true]);
        $missingProofAward = ApplicantScholarship::create([
            'applicant_id' => $this->applicant()->id,
            'scheme_id' => $proofScheme->id,
            'awarded_amount' => 1000,
            'status' => 'awarded',
            'awarded_by' => $officer->id,
            'awarded_at' => now(),
        ]);

        $this->actingAs($officer)
            ->post(route('admission.scholarships.disburse', $missingProofAward), [
                'disbursement_ref' => 'UTR-MISSING-PROOF',
            ])
            ->assertSessionHasErrors('scholarship');

        $this->assertSame('awarded', $missingProofAward->fresh()->status);
        $this->assertNull($missingProofAward->fresh()->disbursement_ref);

        $withdrawnApplicant = $this->applicant(['status' => 'withdrawn']);
        $withdrawnAward = ApplicantScholarship::create([
            'applicant_id' => $withdrawnApplicant->id,
            'scheme_id' => $this->scheme()->id,
            'awarded_amount' => 1000,
            'status' => 'awarded',
            'awarded_by' => $officer->id,
            'awarded_at' => now(),
        ]);

        $this->actingAs($officer)
            ->post(route('admission.scholarships.disburse', $withdrawnAward), [
                'disbursement_ref' => 'UTR-WITHDRAWN-APPLICANT',
            ])
            ->assertSessionHasErrors('scholarship');

        $this->assertSame('awarded', $withdrawnAward->fresh()->status);
        $this->assertNull($withdrawnAward->fresh()->disbursement_ref);

        $enrolledApplicant = $this->applicant(['status' => 'enrolled']);
        $enrolledAward = ApplicantScholarship::create([
            'applicant_id' => $enrolledApplicant->id,
            'scheme_id' => $this->scheme()->id,
            'awarded_amount' => 1000,
            'status' => 'awarded',
            'awarded_by' => $officer->id,
            'awarded_at' => now(),
        ]);

        $this->actingAs($officer)
            ->post(route('admission.scholarships.disburse', $enrolledAward), [
                'disbursement_ref' => 'UTR-ENROLLED-APPLICANT',
            ])
            ->assertSessionHasErrors('scholarship');

        $this->assertSame('awarded', $enrolledAward->fresh()->status);
        $this->assertNull($enrolledAward->fresh()->disbursement_ref);
    }

    public function test_applicant_scholarship_disbursement_reference_must_be_unique(): void
    {
        $officer = $this->admissionOfficer();
        $scheme = $this->scheme();
        $disbursed = ApplicantScholarship::create([
            'applicant_id' => $this->applicant()->id,
            'scheme_id' => $scheme->id,
            'awarded_amount' => 1000,
            'status' => 'disbursed',
            'awarded_by' => $officer->id,
            'awarded_at' => now(),
            'disbursed_at' => now(),
            'disbursement_ref' => 'UTR-DUPLICATE',
        ]);
        $award = ApplicantScholarship::create([
            'applicant_id' => $this->applicant()->id,
            'scheme_id' => $this->scheme()->id,
            'awarded_amount' => 1000,
            'status' => 'awarded',
            'awarded_by' => $officer->id,
            'awarded_at' => now(),
        ]);

        $this->actingAs($officer)
            ->post(route('admission.scholarships.disburse', $award), [
                'disbursement_ref' => ' utr-duplicate ',
            ])
            ->assertSessionHasErrors('disbursement_ref');

        $this->assertSame('awarded', $award->fresh()->status);
        $this->assertNull($award->fresh()->disbursement_ref);
    }

    public function test_scholarship_scheme_cannot_reduce_capacity_or_maximum_below_existing_awards(): void
    {
        $officer = $this->admissionOfficer();
        $scheme = $this->scheme([
            'max_amount' => 25000,
            'available_seats' => 2,
        ]);
        ApplicantScholarship::create([
            'applicant_id' => $this->applicant()->id,
            'scheme_id' => $scheme->id,
            'awarded_amount' => 12000,
            'status' => 'awarded',
            'awarded_by' => $officer->id,
            'awarded_at' => now(),
        ]);
        StudentScholarshipApplication::create([
            'student_id' => Student::factory()->create()->id,
            'scholarship_scheme_id' => $scheme->id,
            'reason' => str_repeat('Approved student scholarship reason. ', 2),
            'status' => 'approved',
            'disbursed_amount' => 10000,
        ]);

        $this->actingAs($officer)
            ->put(route('admission.scholarship-schemes.update', $scheme), [
                'name' => $scheme->name,
                'scheme_code' => $scheme->scheme_code,
                'type' => $scheme->type,
                'criteria' => $scheme->criteria,
                'max_amount' => 25000,
                'available_seats' => 1,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('available_seats');

        $this->actingAs($officer)
            ->put(route('admission.scholarship-schemes.update', $scheme), [
                'name' => $scheme->name,
                'scheme_code' => $scheme->scheme_code,
                'type' => $scheme->type,
                'criteria' => $scheme->criteria,
                'max_amount' => 11000,
                'available_seats' => 2,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('max_amount');

        $scheme->refresh();
        $this->assertSame(2, $scheme->available_seats);
        $this->assertSame('25000.00', $scheme->max_amount);
    }

    public function test_scholarship_scheme_with_active_records_cannot_change_eligibility_contract(): void
    {
        $officer = $this->admissionOfficer();
        $program = Program::factory()->create(['is_active' => true]);
        $scheme = $this->scheme([
            'program_id' => $program->id,
            'min_cgpa' => 7.5,
            'max_family_income' => 500000,
            'requires_document' => false,
            'is_active' => true,
        ]);
        StudentScholarshipApplication::create([
            'student_id' => Student::factory()->create(['program_id' => $program->id, 'status' => 'active'])->id,
            'scholarship_scheme_id' => $scheme->id,
            'reason' => str_repeat('Pending student scholarship reason. ', 2),
            'status' => 'pending',
        ]);

        $this->actingAs($officer)
            ->put(route('admission.scholarship-schemes.update', $scheme), [
                'name' => $scheme->name,
                'scheme_code' => $scheme->scheme_code,
                'type' => $scheme->type,
                'criteria' => $scheme->criteria,
                'program_id' => $program->id,
                'min_cgpa' => 8.0,
                'max_family_income' => 500000,
                'requires_document' => true,
                'max_amount' => 25000,
                'available_seats' => 2,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('scholarship_scheme');

        $scheme->refresh();
        $this->assertSame('7.50', $scheme->min_cgpa);
        $this->assertFalse($scheme->requires_document);

        $this->actingAs($officer)
            ->put(route('admission.scholarship-schemes.update', $scheme), [
                'name' => 'Renamed Merit Scholarship',
                'scheme_code' => $scheme->scheme_code,
                'type' => $scheme->type,
                'criteria' => 'Updated description without changing eligibility.',
                'program_id' => $program->id,
                'min_cgpa' => 7.5,
                'max_family_income' => 500000,
                'requires_document' => false,
                'max_amount' => 26000,
                'available_seats' => 3,
                'is_active' => true,
            ])
            ->assertRedirect(route('admission.scholarship-schemes.index'))
            ->assertSessionHas('success', 'Scholarship scheme updated.');

        $scheme->refresh();
        $this->assertSame('Renamed Merit Scholarship', $scheme->name);
        $this->assertSame('26000.00', $scheme->max_amount);
        $this->assertSame(3, $scheme->available_seats);
    }

    public function test_scholarship_scheme_with_active_records_cannot_be_toggled_directly(): void
    {
        $officer = $this->admissionOfficer();
        $scheme = $this->scheme(['is_active' => true]);
        ApplicantScholarship::create([
            'applicant_id' => $this->applicant()->id,
            'scheme_id' => $scheme->id,
            'awarded_amount' => 12000,
            'status' => 'awarded',
            'awarded_by' => $officer->id,
            'awarded_at' => now(),
        ]);

        $this->actingAs($officer)
            ->post(route('admission.scholarship-schemes.toggle', $scheme))
            ->assertRedirect()
            ->assertSessionHas('error', 'Scholarship schemes with active applications or awards cannot be activated or deactivated directly. Create a new scheme version or close existing applications first.');

        $this->assertTrue($scheme->fresh()->is_active);
    }

    public function test_scholarship_scheme_pages_use_readable_amount_labels_and_guidance(): void
    {
        $officer = $this->admissionOfficer();
        $scheme = $this->scheme([
            'name' => 'Readable Merit Scholarship',
            'max_amount' => 18000,
            'available_seats' => null,
        ]);

        $this->actingAs($officer)
            ->get(route('admission.scholarship-schemes.index'))
            ->assertOk()
            ->assertSee('Scholarship Schemes')
            ->assertSee('Readable Merit Scholarship')
            ->assertSee('Max Amount (Rs.)')
            ->assertSee('Rs. 18,000')
            ->assertSee('Unlimited')
            ->assertDontSee('N/A', false)
            ->assertDontSee('â', false)
            ->assertDontSee('₹', false);

        $this->actingAs($officer)
            ->get(route('admission.scholarship-schemes.create'))
            ->assertOk()
            ->assertSee('Define the eligibility, award limit, proof requirement, and capacity')
            ->assertSee('Maximum Amount (Rs.)')
            ->assertSee('Select type')
            ->assertDontSee('â', false)
            ->assertDontSee('₹', false);

        $this->actingAs($officer)
            ->get(route('admission.scholarship-schemes.edit', $scheme))
            ->assertOk()
            ->assertSee('Edit Scheme')
            ->assertSee('Maximum Amount (Rs.)')
            ->assertSee('Active and available for awarding')
            ->assertDontSee('â', false)
            ->assertDontSee('₹', false);
    }

    public function test_scholarship_disbursement_queue_empty_and_row_states_are_operational(): void
    {
        $officer = $this->admissionUserWithRole('admission_head');
        $program = Program::factory()->create(['is_active' => true, 'name' => 'Scholarship Program']);

        $this->actingAs($officer)
            ->get(route('admission.scholarship-disbursements.index', ['program_id' => $program->id]))
            ->assertOk()
            ->assertSee('No scholarship disbursements are pending')
            ->assertSee('Admission visibility scope')
            ->assertSee('Clear Filters')
            ->assertSee('Open Applicants')
            ->assertSee('Review Schemes')
            ->assertDontSee('All scholarships have been disbursed.')
            ->assertDontSee('N/A', false)
            ->assertDontSee('â', false)
            ->assertDontSee('₹', false);

        $scheme = $this->scheme(['name' => 'Visible Disbursement Scheme']);
        $applicant = $this->applicant(['program' => $program]);
        $applicant->user->update(['name' => 'Visible Disbursement Applicant']);
        ApplicantScholarship::create([
            'applicant_id' => $applicant->id,
            'scheme_id' => $scheme->id,
            'awarded_amount' => 9000,
            'status' => 'awarded',
            'awarded_by' => $officer->id,
            'awarded_at' => now(),
        ]);

        $this->actingAs($officer)
            ->get(route('admission.scholarship-disbursements.index'))
            ->assertOk()
            ->assertSee('Visible Disbursement Applicant')
            ->assertSee('Visible Disbursement Scheme')
            ->assertSee('Amount (Rs.)')
            ->assertSee('Rs. 9,000.00')
            ->assertSee('Disbursing <strong>Rs. 9,000.00</strong>', false)
            ->assertDontSee('N/A', false)
            ->assertDontSee('â', false)
            ->assertDontSee('₹', false);
    }
}
