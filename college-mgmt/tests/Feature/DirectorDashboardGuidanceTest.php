<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\ApprovalWorkflow;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\OrgReportingLine;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DirectorDashboardGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private function directorUser(): User
    {
        Role::firstOrCreate(['name' => 'director', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('director');

        return $user;
    }

    public function test_director_dashboard_prioritizes_overdue_academic_approvals(): void
    {
        $user = $this->directorUser();
        $applicant = Applicant::factory()->create();

        ApprovalWorkflow::create([
            'approvable_type' => Applicant::class,
            'approvable_id' => $applicant->id,
            'approver_role' => 'dean_academics',
            'status' => 'pending',
            'due_at' => now()->subDay(),
        ]);

        $this->actingAs($user)
            ->get(route('director.dashboard'))
            ->assertStatus(200)
            ->assertSee('Director Priority')
            ->assertSee('Escalate 1 overdue approval')
            ->assertSee('Review Approvals')
            ->assertSee(route('dean.approvals'), false);
    }

    public function test_director_dashboard_shows_clean_accounts_fee_summary(): void
    {
        $user = $this->directorUser();
        $student = Student::factory()->create();

        OrgReportingLine::updateOrCreate([
            'parent_role' => 'director',
            'child_role' => 'accounts_officer',
        ], [
            'can_view_summary' => true,
            'can_view_full' => true,
            'sort_order' => 1,
        ]);

        $feeStructure = FeeStructure::create([
            'course_id' => $student->course_id,
            'program_id' => $student->program_id,
            'academic_year_id' => AcademicYear::factory()->create()->id,
            'fee_type' => 'Tuition',
            'amount' => 12345,
        ]);

        FeePayment::create([
            'student_id' => $student->id,
            'fee_structure_id' => $feeStructure->id,
            'amount_paid' => 12345,
            'payment_date' => now()->toDateString(),
            'receipt_number' => 'DIR-FEE-001',
            'payment_method' => 'cash',
            'status' => 'paid',
        ]);

        $this->actingAs($user)
            ->get(route('director.dashboard'))
            ->assertStatus(200)
            ->assertSee('Department Portal Overviews')
            ->assertSee('Accounts')
            ->assertSee('Fee Collections This Year')
            ->assertSee('Rs. 12,345')
            ->assertDontSee('â', false);
    }
}
