<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Batch;
use App\Models\Department;
use App\Models\DepartmentMember;
use App\Models\DepartmentRole;
use App\Models\EnrollmentConfirmation;
use App\Models\Lead;
use App\Models\Program;
use App\Models\SeatMatrix;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdmissionReportingScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admission_reporting_dashboard_respects_counsellor_scope(): void
    {
        [$counsellor, $peer] = $this->admissionCounsellors();
        [$visibleProgram, $hiddenProgram] = $this->seedScopedAdmissionRecords($counsellor, $peer);

        $response = $this->actingAs($counsellor)->get(route('admission.reports.index'));

        $response->assertOk();
        $response->assertSee($visibleProgram->name);
        $response->assertSee('Visible Source');
        $response->assertSee('Visible Scope State');
        $response->assertDontSee($hiddenProgram->name);
        $response->assertDontSee('Hidden Source');
        $response->assertDontSee('Hidden Scope State');
    }

    public function test_admission_reporting_pdf_uses_the_same_visibility_scope(): void
    {
        [$counsellor, $peer] = $this->admissionCounsellors();
        [$visibleProgram, $hiddenProgram] = $this->seedScopedAdmissionRecords($counsellor, $peer);

        $request = Request::create(route('admission.reports.export-pdf'), 'GET');
        $request->setUserResolver(fn () => $counsellor);
        $controller = app(\App\Http\Controllers\Admission\ReportingController::class);
        $method = new \ReflectionMethod($controller, 'buildReportData');
        $method->setAccessible(true);
        $capturedReportData = $method->invoke($controller, $request);

        $this->assertSame(1, $capturedReportData['totalLeads']);
        $this->assertSame(1, $capturedReportData['totalApplicants']);
        $this->assertSame(1, $capturedReportData['enrolled']);
        $this->assertTrue($capturedReportData['programStats']->pluck('name')->contains($visibleProgram->name));
        $this->assertFalse($capturedReportData['programStats']->pluck('name')->contains($hiddenProgram->name));
        $this->assertTrue($capturedReportData['sourceStats']->pluck('source')->contains('Visible Source'));
        $this->assertFalse($capturedReportData['sourceStats']->pluck('source')->contains('Hidden Source'));
    }

    public function test_admission_reporting_filters_counts_and_drilldown_links_to_current_scope(): void
    {
        [$counsellor, $peer] = $this->admissionCounsellors();
        [$visibleProgram] = $this->seedScopedAdmissionRecords($counsellor, $peer);

        $otherVisibleProgram = Program::factory()->create(['name' => 'Other Visible Admission Program', 'code' => 'OVAP']);
        $otherVisibleBatch = Batch::factory()->create(['program_id' => $otherVisibleProgram->id]);
        SeatMatrix::create(['program_id' => $otherVisibleProgram->id, 'batch_id' => $otherVisibleBatch->id, 'total_seats' => 40]);

        Lead::factory()->create([
            'program_id' => $otherVisibleProgram->id,
            'source' => 'other_visible_source',
            'status' => 'converted',
            'assigned_to' => $counsellor->id,
        ]);
        Applicant::factory()->create([
            'program_id' => $otherVisibleProgram->id,
            'batch_id' => $otherVisibleBatch->id,
            'status' => 'submitted',
            'assigned_to' => $counsellor->id,
        ]);

        $response = $this->actingAs($counsellor)->get(route('admission.reports.index', [
            'program_id' => $visibleProgram->id,
        ]));

        $response->assertOk()
            ->assertSee('Current report scope:')
            ->assertSee('Program Id: ' . $visibleProgram->id)
            ->assertSee($visibleProgram->name)
            ->assertDontSee($otherVisibleProgram->name)
            ->assertSee(route('admission.leads.index', ['program_id' => $visibleProgram->id]), false)
            ->assertSee(route('admission.applicants.index', ['program_id' => $visibleProgram->id]), false)
            ->assertSee('/admission/applicants?program_id=' . $visibleProgram->id . '&amp;status=selected', false)
            ->assertSee(route('admission.enrollment.index', ['program_id' => $visibleProgram->id]), false)
            ->assertSee(route('admission.reports.export-pdf', ['program_id' => $visibleProgram->id]), false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false);

        $request = Request::create(route('admission.reports.index', ['program_id' => $visibleProgram->id]), 'GET');
        $request->setUserResolver(fn () => $counsellor);
        $controller = app(\App\Http\Controllers\Admission\ReportingController::class);
        $method = new \ReflectionMethod($controller, 'buildReportData');
        $method->setAccessible(true);
        $capturedReportData = $method->invoke($controller, $request);

        $this->assertSame(1, $capturedReportData['totalLeads']);
        $this->assertSame(1, $capturedReportData['totalApplicants']);
        $this->assertTrue($capturedReportData['programStats']->pluck('name')->contains($visibleProgram->name));
        $this->assertFalse($capturedReportData['programStats']->pluck('name')->contains($otherVisibleProgram->name));
    }

    private function admissionCounsellors(): array
    {
        Role::firstOrCreate(['name' => 'admission_counsellor', 'guard_name' => 'web']);
        $department = Department::where('code', 'ADM')->firstOrFail();
        $role = DepartmentRole::where('department_id', $department->id)
            ->where('code', 'admission_counsellor')
            ->firstOrFail();

        $counsellor = User::factory()->create(['name' => 'Visible Scope Counsellor']);
        $peer = User::factory()->create(['name' => 'Hidden Scope Counsellor']);
        $counsellor->assignRole('admission_counsellor');
        $peer->assignRole('admission_counsellor');

        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $role->id,
            'user_id' => $counsellor->id,
            'is_active' => true,
        ]);
        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $role->id,
            'user_id' => $peer->id,
            'is_active' => true,
        ]);

        return [$counsellor, $peer];
    }

    private function seedScopedAdmissionRecords(User $counsellor, User $peer): array
    {
        $visibleProgram = Program::factory()->create(['name' => 'Visible Scope MBA', 'code' => 'VSMBA']);
        $hiddenProgram = Program::factory()->create(['name' => 'Hidden Scope BBA', 'code' => 'HSBBA']);
        $visibleBatch = Batch::factory()->create(['program_id' => $visibleProgram->id]);
        $hiddenBatch = Batch::factory()->create(['program_id' => $hiddenProgram->id]);

        SeatMatrix::create(['program_id' => $visibleProgram->id, 'batch_id' => $visibleBatch->id, 'total_seats' => 60]);
        SeatMatrix::create(['program_id' => $hiddenProgram->id, 'batch_id' => $hiddenBatch->id, 'total_seats' => 60]);

        Lead::factory()->create([
            'program_id' => $visibleProgram->id,
            'source' => 'visible_source',
            'status' => 'converted',
            'assigned_to' => $counsellor->id,
        ]);
        Lead::factory()->create([
            'program_id' => $hiddenProgram->id,
            'source' => 'hidden_source',
            'status' => 'converted',
            'assigned_to' => $peer->id,
        ]);

        $visibleApplicant = Applicant::factory()->create([
            'program_id' => $visibleProgram->id,
            'batch_id' => $visibleBatch->id,
            'status' => 'selected',
            'category' => 'General',
            'personal_data' => ['state' => 'Visible Scope State'],
            'assigned_to' => $counsellor->id,
        ]);
        $hiddenApplicant = Applicant::factory()->create([
            'program_id' => $hiddenProgram->id,
            'batch_id' => $hiddenBatch->id,
            'status' => 'selected',
            'category' => 'General',
            'personal_data' => ['state' => 'Hidden Scope State'],
            'assigned_to' => $peer->id,
        ]);

        EnrollmentConfirmation::create([
            'applicant_id' => $visibleApplicant->id,
            'confirmed_by' => $counsellor->id,
            'confirmed_at' => now(),
            'enrollment_number' => 'ENR-VISIBLE',
            'roll_number' => 'ROLL-VISIBLE',
            'batch_id' => $visibleBatch->id,
            'status' => 'completed',
        ]);
        EnrollmentConfirmation::create([
            'applicant_id' => $hiddenApplicant->id,
            'confirmed_by' => $peer->id,
            'confirmed_at' => now(),
            'enrollment_number' => 'ENR-HIDDEN',
            'roll_number' => 'ROLL-HIDDEN',
            'batch_id' => $hiddenBatch->id,
            'status' => 'completed',
        ]);

        return [$visibleProgram, $hiddenProgram];
    }
}
