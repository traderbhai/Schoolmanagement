<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcTimetableConstraint;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\AcademicPmcTimetableImpactRecord;
use App\Models\AcademicPmcTimetablePublishCheck;
use App\Models\AcademicPmcTimetableResolutionAction;
use App\Models\Batch;
use App\Models\Department;
use App\Models\Program;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcTimetableV081Test extends TestCase
{
    use RefreshDatabase;

    public function test_generation_validation_diagnostics_render_on_dashboard_and_generator_page(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-os.index'))
            ->assertOk()
            ->assertSee('Generation Validation Diagnostics')
            ->assertSee('Solver Attempts')
            ->assertSee('Publish Blocks')
            ->assertSee('Open generator validation source');

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-generator.index'))
            ->assertOk()
            ->assertSee('Generation Validation Diagnostics')
            ->assertSee('Missing Impact')
            ->assertSee('Generation Runs');
    }

    public function test_generation_validation_blockers_are_counted_in_launch_control(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'v081 Generated Draft',
            'strategy' => 'balanced',
            'created_by' => $chair->id,
            'status' => 'completed_with_conflicts',
            'scheduled_count' => 4,
            'unscheduled_count' => 2,
            'hard_conflict_count' => 1,
            'soft_warning_count' => 1,
            'quality_score' => 55,
        ]);

        AcademicPmcTimetableImpactRecord::query()->delete();

        $run->forceFill([
            'scheduled_count' => 4,
            'unscheduled_count' => 2,
            'quality_score' => 55,
            'updated_at' => now()->subDay(),
        ])->save();

        $constraint = AcademicPmcTimetableConstraint::create([
            'generation_run_id' => $run->id,
            'constraint_type' => 'v081_fixture',
            'severity' => 'hard',
            'title' => 'v081 faculty clash',
            'description' => 'Fixture hard conflict for launch diagnostics.',
            'affected_type' => 'teacher',
            'affected_key' => 'v081',
            'recommended_fix' => 'Move one generated class.',
            'source_route' => route('academics.pmc.timetable-generator.index'),
        ]);

        AcademicPmcTimetableResolutionAction::create([
            'constraint_id' => $constraint->id,
            'generation_run_id' => $run->id,
            'action_type' => 'move_slot',
            'title' => 'Resolve v081 conflict',
            'description' => 'Fixture unresolved action.',
            'owner_user_id' => $chair->id,
            'assigned_by' => $chair->id,
            'priority' => 'high',
            'status' => 'open',
            'due_at' => now()->addDay(),
        ]);

        AcademicPmcTimetablePublishCheck::create([
            'generation_run_id' => $run->id,
            'check_type' => 'v081_publish_block',
            'status' => 'block',
            'severity' => 'high',
            'title' => 'v081 publish block',
            'description' => 'Fixture publish blocker.',
            'required_role' => 'pmc_head',
        ]);

        $department = Department::firstOrCreate(['code' => 'V081'], ['name' => 'v081 Department']);
        $program = Program::create([
            'department_id' => $department->id,
            'name' => 'v081 Program',
            'code' => 'V081-P',
            'system_type' => 'semester',
            'duration_years' => 2,
            'total_terms' => 4,
            'default_intake_capacity' => 60,
            'is_active' => true,
        ]);
        $batch = Batch::create([
            'program_id' => $program->id,
            'name' => 'v081 Batch',
            'code' => 'V081-B',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'intake_capacity' => 60,
            'status' => 'active',
        ]);
        $term = Term::create([
            'batch_id' => $batch->id,
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'v081 Term',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'is_current' => true,
            'sort_order' => 1,
        ]);
        $subject = Subject::create([
            'department_id' => $department->id,
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'v081 Subject',
            'code' => 'V081-S',
            'credits' => 3,
            'type' => 'core',
            'hours_per_week' => 3,
            'is_active' => true,
        ]);
        AcademicPmcCourseGroup::create([
            'name' => 'v081 Core Section',
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'owner_user_id' => $chair->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 30,
            'status' => 'active',
            'is_locked' => true,
        ]);

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-os.index'))
            ->assertOk()
            ->assertSee('Generate and validate')
            ->assertSee('Unscheduled')
            ->assertSee('Hard Conflicts')
            ->assertSee('Open Actions')
            ->assertSee('Publish Blocks')
            ->assertSee('Stale Inputs')
            ->assertSee('Resolve unscheduled classes, stale inputs, conflicts, publish checks, and missing impact preview before publishing.');
    }
}
