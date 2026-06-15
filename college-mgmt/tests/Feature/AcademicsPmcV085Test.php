<?php

namespace Tests\Feature;

use App\Models\AcademicPmcParentEscalation;
use App\Models\AcademicPmcStudentIntervention;
use App\Models\AcademicPmcStudentSuccessPlan;
use App\Models\Batch;
use App\Models\Department;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcV085Test extends TestCase
{
    use RefreshDatabase;

    public function test_student_success_effectiveness_diagnostics_render_on_pmc_student_success_pages(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($chair)
            ->get(route('academics.pmc.student-success-v004.index'))
            ->assertOk()
            ->assertSee('Student Success Intervention Effectiveness')
            ->assertSee('Overdue Interventions')
            ->assertSee('Repeat Risk Students');

        $this->actingAs($chair)
            ->get(route('academics.pmc.interventions.index'))
            ->assertOk()
            ->assertSee('Student Success Intervention Effectiveness')
            ->assertSee('Evidence Gaps');
    }

    public function test_student_success_effectiveness_diagnostics_count_real_blockers(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $department = Department::firstOrCreate(['code' => 'PMC085'], ['name' => 'PMC v085 Department']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PMC085', 'name' => 'PMC v085 Program', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'PMC085-26', 'name' => 'PMC v085 Batch', 'status' => 'active']);
        $studentUser = User::factory()->create(['name' => 'PMC v085 Repeat Risk Student']);
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'department_id' => $department->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'guardian_name' => 'PMC v085 Guardian',
            'guardian_phone' => '9999908585',
            'status' => 'active',
        ]);

        $plan = AcademicPmcStudentSuccessPlan::create([
            'student_id' => $student->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'mentor_user_id' => $chair->id,
            'risk_type' => 'retention_risk',
            'risk_band' => 'critical',
            'status' => 'open',
            'intervention_plan' => 'Repeat parent and mentor intervention.',
            'next_review_at' => now()->subDays(10),
            'parent_escalation_required' => true,
            'signals' => ['risk_score' => 92, 'reasons' => ['Repeated missed interventions']],
        ]);

        AcademicPmcStudentIntervention::create([
            'student_success_plan_id' => $plan->id,
            'student_id' => $student->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'owner_user_id' => $chair->id,
            'created_by' => $chair->id,
            'intervention_type' => 'mentor_meeting',
            'status' => 'open',
            'priority' => 'critical',
            'reason' => 'Overdue mentor meeting.',
            'action_plan' => 'Complete mentor meeting and submit evidence.',
            'due_at' => now()->subDays(2),
        ]);

        AcademicPmcStudentIntervention::create([
            'student_success_plan_id' => $plan->id,
            'student_id' => $student->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'owner_user_id' => $chair->id,
            'created_by' => $chair->id,
            'intervention_type' => 'remedial_class',
            'status' => 'under_review',
            'priority' => 'high',
            'reason' => 'Evidence missing after remedial plan.',
            'action_plan' => 'Attach remedial evidence.',
            'due_at' => now()->subDay(),
            'evidence' => [],
        ]);

        AcademicPmcParentEscalation::create([
            'student_success_plan_id' => $plan->id,
            'student_id' => $student->id,
            'owner_user_id' => $chair->id,
            'created_by' => $chair->id,
            'guardian_name' => 'PMC v085 Guardian',
            'guardian_phone' => '9999908585',
            'reason' => 'repeat_risk',
            'status' => 'scheduled',
            'scheduled_at' => now()->subDay(),
        ]);

        $this->actingAs($chair)
            ->get(route('academics.pmc.student-success-v004.index'))
            ->assertOk()
            ->assertSee('Student Success Intervention Effectiveness')
            ->assertSee('PMC v085 Repeat Risk Student')
            ->assertSee('Clear overdue interventions, stale reviews, parent-call delays, repeat-risk cases, and missing evidence before closing the student-success cycle.');
    }
}
