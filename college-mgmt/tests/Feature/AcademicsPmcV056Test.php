<?php

namespace Tests\Feature;

use App\Models\AcademicPmcPlanningCycle;
use App\Models\AcademicPmcReadinessItem;
use App\Models\AcademicPmcWorkItem;
use App\Models\Batch;
use App\Models\Department;
use App\Models\Program;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcV056Test extends TestCase
{
    use RefreshDatabase;

    private function seedFixture(): array
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'PGDM-26', 'name' => 'PGDM 2026', 'status' => 'active']);
        $term = Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'Term 1', 'is_current' => true]);
        Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT401', 'name' => 'Management Analytics', 'credits' => 3, 'is_active' => true]);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        return [User::where('email', 'chair@college.com')->firstOrFail(), $program, $term];
    }

    public function test_pmc_planning_cycle_generates_readiness_items_and_blocker_actions(): void
    {
        [$chair, $program, $term] = $this->seedFixture();

        $this->actingAs($chair)->post(route('academics.pmc.planning.store'), [
            'title' => 'Test Real PMC Semester Readiness',
            'cycle_type' => 'semester_readiness',
            'academic_year' => '2026-27',
            'program_id' => $program->id,
            'term_id' => $term->id,
        ])->assertRedirect();

        $cycle = AcademicPmcPlanningCycle::where('title', 'Test Real PMC Semester Readiness')->firstOrFail();
        $this->assertGreaterThanOrEqual(7, $cycle->readinessItems()->count());
        $this->assertGreaterThan(0, $cycle->readiness_score);

        $item = $cycle->readinessItems()->where('is_blocker', true)->firstOrFail();
        $this->actingAs($chair)->patch(route('academics.pmc.semester-readiness.items.update', $item), [
            'status' => 'done',
            'completion_percent' => 100,
            'is_blocker' => 0,
            'evidence_note' => 'Evidence verified by PMC.',
        ])->assertRedirect();

        $item->refresh();
        $this->assertSame('done', $item->status);
        $this->assertSame(100, $item->completion_percent);

        $anotherBlocker = AcademicPmcReadinessItem::where('is_blocker', true)->where('status', '!=', 'done')->firstOrFail();
        $this->actingAs($chair)->post(route('academics.pmc.semester-readiness.items.work-item', $anotherBlocker))->assertRedirect();
        $this->assertTrue(AcademicPmcWorkItem::where('source_type', 'academic_pmc_readiness_item')->where('source_key', (string) $anotherBlocker->id)->exists());

        $this->actingAs($chair)
            ->get(route('academics.pmc.planning.index'))
            ->assertOk()
            ->assertSee('PMC Planning Cycle Control')
            ->assertSee('Semester Readiness Checklist')
            ->assertSee('Create Planning Cycle')
            ->assertSee('Create Action');
    }
}
