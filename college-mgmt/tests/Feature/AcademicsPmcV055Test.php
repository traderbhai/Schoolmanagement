<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCurriculumValidation;
use App\Models\AcademicPmcCurriculumPlan;
use App\Models\Batch;
use App\Models\Department;
use App\Models\Program;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcV055Test extends TestCase
{
    use RefreshDatabase;

    private function seedFixture(): User
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'PGDM-26', 'name' => 'PGDM 2026', 'status' => 'active']);
        Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'Term 1', 'is_current' => true]);
        Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT401', 'name' => 'Management Analytics', 'credits' => 3, 'is_active' => true]);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        return User::where('email', 'chair@college.com')->firstOrFail();
    }

    public function test_pmc_curriculum_validations_refresh_and_render_real_blockers(): void
    {
        $chair = $this->seedFixture();
        AcademicPmcCurriculumValidation::query()->delete();

        $this->actingAs($chair)
            ->post(route('academics.pmc.curriculum-validations.refresh'))
            ->assertRedirect();

        $this->assertTrue(AcademicPmcCurriculumValidation::whereIn('validation_type', [
            'syllabus_version',
            'credit_rule',
            'course_outcomes',
            'co_po_mapping',
            'rollout_readiness',
        ])->exists());

        $this->assertGreaterThan(0, AcademicPmcCurriculumPlan::avg('readiness_score'));

        $this->actingAs($chair)
            ->get(route('academics.pmc.curriculum-governance'))
            ->assertOk()
            ->assertSee('Curriculum Validation Control')
            ->assertSee('Refresh Validations')
            ->assertSee('CO-PO')
            ->assertSee('Recommended Action');
    }
}
