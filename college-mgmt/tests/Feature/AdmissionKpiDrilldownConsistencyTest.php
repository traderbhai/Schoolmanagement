<?php

namespace Tests\Feature;

use App\Models\AdmissionFeeInstallment;
use App\Models\AdmissionPayment;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\Batch;
use App\Models\Department;
use App\Models\DepartmentMember;
use App\Models\DepartmentRole;
use App\Models\EnrollmentConfirmation;
use App\Models\Lead;
use App\Models\Program;
use App\Models\RequiredDocument;
use App\Models\Student;
use App\Models\User;
use App\Services\AdmissionKpiDrilldownService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdmissionKpiDrilldownConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admission_dashboard_counts_match_linked_drilldown_lists(): void
    {
        $head = $this->userWithRole('admission_head');
        $program = Program::factory()->create(['is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $document = RequiredDocument::create([
            'program_id' => $program->id,
            'name' => 'Migration Certificate',
            'is_mandatory' => true,
            'accepted_formats' => 'pdf',
            'is_active' => true,
        ]);

        Lead::factory()->count(4)->create(['program_id' => $program->id]);
        $this->applicant($program, $batch, 'submitted');
        $this->applicant($program, $batch, 'submitted');
        $this->applicant($program, $batch, 'under_review');
        $shortlisted = $this->applicant($program, $batch, 'shortlisted');
        $selected = $this->applicant($program, $batch, 'selected');
        $this->applicant($program, $batch, 'rejected');

        ApplicantDocument::create([
            'applicant_id' => $shortlisted->id,
            'required_document_id' => $document->id,
            'file_path' => 'documents/migration-1.pdf',
            'original_name' => 'migration-1.pdf',
            'file_size_kb' => 80,
            'status' => 'pending',
            'uploaded_at' => now(),
            'version' => 1,
        ]);
        ApplicantDocument::create([
            'applicant_id' => $selected->id,
            'required_document_id' => $document->id,
            'file_path' => 'documents/migration-2.pdf',
            'original_name' => 'migration-2.pdf',
            'file_size_kb' => 80,
            'status' => 'pending',
            'uploaded_at' => now(),
            'version' => 1,
        ]);

        $installment = AdmissionFeeInstallment::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'name' => 'Admission Fee',
            'amount' => 10000,
            'installment_number' => 1,
            'is_active' => true,
        ]);
        AdmissionPayment::create([
            'applicant_id' => $selected->id,
            'admission_fee_installment_id' => $installment->id,
            'amount_paid' => 10000,
            'payment_date' => now()->toDateString(),
            'payment_mode' => 'upi',
            'status' => 'pending',
            'submitted_by' => $selected->user_id,
        ]);

        EnrollmentConfirmation::create([
            'applicant_id' => $selected->id,
            'confirmed_by' => $head->id,
            'confirmed_at' => now(),
            'enrollment_number' => 'ENR-DRILLDOWN-1',
            'roll_number' => 'ROLL-DRILLDOWN-1',
            'batch_id' => $batch->id,
            'status' => 'completed',
        ]);
        Student::factory()->count(3)->create();

        $dashboard = app(AdmissionKpiDrilldownService::class)->dashboard($head);
        $this->assertSame(4, $dashboard['funnelData']['leads']);
        $this->assertSame(6, $dashboard['funnelData']['applied']);
        $this->assertSame(1, $dashboard['funnelData']['shortlisted']);
        $this->assertSame(1, $dashboard['funnelData']['selected']);
        $this->assertSame(1, $dashboard['funnelData']['enrolled']);
        $this->assertSame(2, $dashboard['kpis']['docs_pending']);
        $this->assertSame(1, $dashboard['kpis']['payments_pending']);

        $this->actingAs($head)->get(route('admission.dashboard'))
            ->assertOk()
            ->assertSee('Admission Funnel')
            ->assertSee('Enrolled');

        $this->actingAs($head)->get(route('admission.leads.index'))
            ->assertOk()
            ->assertSee('4 records after filters')
            ->assertSee('Filter: All visible leads');

        $this->actingAs($head)->get(route('admission.applicants.index'))
            ->assertOk()
            ->assertSee('6 records after filters')
            ->assertSee('Filter: All visible applicants');

        $this->actingAs($head)->get(route('admission.applicants.index', ['status' => 'shortlisted']))
            ->assertOk()
            ->assertSee('1 records after filters')
            ->assertSee('Filter: Status: Shortlisted');

        $this->actingAs($head)->get(route('admission.applicants.index', ['status' => 'selected']))
            ->assertOk()
            ->assertSee('1 records after filters')
            ->assertSee('Filter: Status: Selected');

        $this->actingAs($head)->get(route('admission.documents.queue'))
            ->assertOk()
            ->assertSee('Pending Documents (2)')
            ->assertSee('Filter: All visible pending documents');

        $this->actingAs($head)->get(route('admission.payments.queue'))
            ->assertOk()
            ->assertSee('Pending Verification')
            ->assertSee('Filter: All visible pending payments');

        $this->actingAs($head)->get(route('admission.enrollment.index'))
            ->assertOk()
            ->assertSee('Total Enrolled')
            ->assertSee('Showing 1-1 of 1')
            ->assertDontSee('Showing 1-3 of 3');
    }

    public function test_admission_kpi_drilldowns_respect_counsellor_scope(): void
    {
        [$counsellor, $peer] = $this->admissionCounsellors();
        $program = Program::factory()->create(['is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $document = RequiredDocument::create([
            'program_id' => $program->id,
            'name' => 'Transfer Certificate',
            'is_mandatory' => true,
            'accepted_formats' => 'pdf',
            'is_active' => true,
        ]);

        Lead::factory()->create(['program_id' => $program->id, 'assigned_to' => $counsellor->id, 'status' => 'new']);
        Lead::factory()->create(['program_id' => $program->id, 'assigned_to' => $peer->id, 'status' => 'new']);

        $visible = $this->applicant($program, $batch, 'selected', $counsellor->id);
        $hidden = $this->applicant($program, $batch, 'selected', $peer->id);

        foreach ([$visible, $hidden] as $index => $applicant) {
            ApplicantDocument::create([
                'applicant_id' => $applicant->id,
                'required_document_id' => $document->id,
                'file_path' => "documents/tc-{$index}.pdf",
                'original_name' => "tc-{$index}.pdf",
                'file_size_kb' => 80,
                'status' => 'pending',
                'uploaded_at' => now(),
                'version' => 1,
            ]);
            EnrollmentConfirmation::create([
                'applicant_id' => $applicant->id,
                'confirmed_by' => $counsellor->id,
                'confirmed_at' => now(),
                'enrollment_number' => 'ENR-SCOPE-' . $index,
                'roll_number' => 'ROLL-SCOPE-' . $index,
                'batch_id' => $batch->id,
                'status' => 'completed',
            ]);
        }

        $dashboard = app(AdmissionKpiDrilldownService::class)->dashboard($counsellor);
        $this->assertSame(1, $dashboard['funnelData']['leads']);
        $this->assertSame(1, $dashboard['funnelData']['applied']);
        $this->assertSame(1, $dashboard['funnelData']['selected']);
        $this->assertSame(1, $dashboard['funnelData']['enrolled']);
        $this->assertSame(1, $dashboard['kpis']['docs_pending']);

        $this->actingAs($counsellor)->get(route('admission.applicants.index'))
            ->assertOk()
            ->assertSee('1 records after filters')
            ->assertSee($visible->application_number)
            ->assertDontSee($hidden->application_number);

        $this->actingAs($counsellor)->get(route('admission.documents.queue'))
            ->assertOk()
            ->assertSee('Pending Documents (1)')
            ->assertDontSee($hidden->user->name);

        $this->actingAs($counsellor)->get(route('admission.enrollment.index'))
            ->assertOk()
            ->assertSee('Showing 1-1 of 1')
            ->assertDontSee('ENR-SCOPE-1');
    }

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function admissionCounsellors(): array
    {
        Role::firstOrCreate(['name' => 'admission_counsellor', 'guard_name' => 'web']);
        $department = Department::where('code', 'ADM')->firstOrFail();
        $role = DepartmentRole::where('department_id', $department->id)
            ->where('code', 'admission_counsellor')
            ->firstOrFail();

        $counsellor = User::factory()->create(['name' => 'Visible Counsellor']);
        $peer = User::factory()->create(['name' => 'Hidden Counsellor']);
        $counsellor->assignRole('admission_counsellor');
        $peer->assignRole('admission_counsellor');

        foreach ([$counsellor, $peer] as $user) {
            DepartmentMember::create([
                'department_id' => $department->id,
                'department_role_id' => $role->id,
                'user_id' => $user->id,
                'is_active' => true,
            ]);
        }

        return [$counsellor, $peer];
    }

    private function applicant(Program $program, Batch $batch, string $status, ?int $assignedTo = null): Applicant
    {
        return Applicant::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => $status,
            'assigned_to' => $assignedTo,
        ]);
    }
}
