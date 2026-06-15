<?php

namespace Tests\Feature;

use App\Models\AcademicPmcPolicyAudit;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\Department;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcV086Test extends TestCase
{
    use RefreshDatabase;

    public function test_pmc_policy_audit_shows_high_risk_route_enforcement_diagnostics(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($chair)
            ->get(route('academics.pmc.policy-audit.index'))
            ->assertOk()
            ->assertSee('PMC High-Risk Route Enforcement Diagnostics')
            ->assertSee('Scope-Aware')
            ->assertSee('Broad Write')
            ->assertSee('academics.pmc.timetable-notifications.update-status');

        $this->assertDatabaseHas('academic_pmc_policy_audits', [
            'route_name' => 'academics.pmc.timetable-generator.publish',
            'required_scope' => 'generation_run_scope',
            'risk_level' => 'critical',
            'missing_enforcement' => false,
        ]);
        $this->assertGreaterThanOrEqual(16, AcademicPmcPolicyAudit::count());
    }

    public function test_pmc_manager_cannot_validate_out_of_scope_generation_run_directly(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $manager = User::where('email', 'pmc.manager@college.com')->firstOrFail();
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $department = Department::factory()->create(['code' => 'PMC086', 'name' => 'PMC v086 Department']);
        $outOfScopeProgram = Program::factory()->create([
            'department_id' => $department->id,
            'code' => 'PMC086',
            'name' => 'PMC v086 Out Of Scope Program',
            'is_active' => true,
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'PMC v086 Out Of Scope Run',
            'strategy' => 'balanced',
            'program_id' => $outOfScopeProgram->id,
            'created_by' => $chair->id,
            'status' => 'generated',
            'input_summary' => ['fixture' => true],
        ]);

        $this->actingAs($manager)
            ->post(route('academics.pmc.timetable-generator.validate', $run))
            ->assertForbidden();

        $this->actingAs($chair)
            ->post(route('academics.pmc.timetable-generator.validate', $run))
            ->assertRedirect();
    }
}
