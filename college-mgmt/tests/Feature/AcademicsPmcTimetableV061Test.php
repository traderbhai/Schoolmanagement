<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\AcademicPmcTimetableSessionDemand;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\TimetableSlot;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcTimetableV061Test extends TestCase
{
    use RefreshDatabase;

    private function seedFixture(): array
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'PGDM-26', 'name' => 'PGDM 2026', 'status' => 'active']);
        Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'Term 1', 'is_current' => true]);
        Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT401', 'name' => 'Management Analytics', 'credits' => 3, 'is_active' => true]);
        Semester::factory()->create(['number' => 1, 'is_current' => true]);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        $isolatedProgram = Program::factory()->create(['department_id' => $department->id, 'code' => 'V061', 'name' => 'V061 Timetable Program', 'is_active' => true]);
        $isolatedBatch = Batch::factory()->create(['program_id' => $isolatedProgram->id, 'code' => 'V061-26', 'name' => 'V061 2026', 'status' => 'active']);
        $term = Term::factory()->create(['program_id' => $isolatedProgram->id, 'batch_id' => $isolatedBatch->id, 'term_number' => 1, 'name' => 'V061 Term 1', 'is_current' => true]);
        $subject = Subject::factory()->create(['department_id' => $department->id, 'program_id' => $isolatedProgram->id, 'code' => 'V061101', 'name' => 'Applied Timetable Design', 'credits' => 3, 'is_active' => true]);
        $teacher = Teacher::firstOrFail();
        $room = Classroom::firstOrCreate(['room_number' => 'V061-101'], ['name' => 'V061 Lecture Room', 'capacity' => 80, 'type' => 'lecture', 'is_active' => true]);
        foreach ([1, 2, 3] as $index) {
            TimetableSlot::firstOrCreate(
                ['name' => 'V061 Period ' . $index],
                ['start_time' => sprintf('%02d:00', 8 + $index), 'end_time' => sprintf('%02d:00', 9 + $index), 'is_break' => false, 'sort_order' => 100 + $index, 'is_active' => true]
            );
        }

        $group = AcademicPmcCourseGroup::create([
            'name' => 'V061 Core Section A',
            'group_type' => 'core_section',
            'program_id' => $isolatedProgram->id,
            'batch_id' => $isolatedBatch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'owner_user_id' => User::where('email', 'chair@college.com')->value('id'),
            'min_capacity' => 1,
            'max_capacity' => 80,
            'current_strength' => 45,
            'status' => 'ready',
            'constraints' => ['weekly_hours' => 3],
        ]);
        AcademicPmcGroupFacultyAssignment::create([
            'course_group_id' => $group->id,
            'teacher_id' => $teacher->id,
            'assignment_role' => 'primary',
            'assignment_source' => 'pmc',
            'approval_status' => 'pmc_approved',
            'weekly_hours' => 3,
            'assigned_by' => User::where('email', 'chair@college.com')->value('id'),
        ]);

        return [
            'chair' => User::where('email', 'chair@college.com')->firstOrFail(),
            'program' => $isolatedProgram,
            'batch' => $isolatedBatch,
            'term' => $term,
            'group' => $group,
        ];
    }

    public function test_generator_creates_weekly_session_demand_and_multiple_sessions_per_group(): void
    {
        $fixture = $this->seedFixture();

        $this->actingAs($fixture['chair'])->post(route('academics.pmc.timetable-generator.generate'), [
            'title' => 'V061 Demand Driven Timetable',
            'strategy' => 'balanced',
            'program_id' => $fixture['program']->id,
            'batch_id' => $fixture['batch']->id,
            'term_id' => $fixture['term']->id,
        ])->assertRedirect();

        $run = AcademicPmcTimetableGenerationRun::where('title', 'V061 Demand Driven Timetable')->firstOrFail();
        $demand = AcademicPmcTimetableSessionDemand::where('generation_run_id', $run->id)
            ->where('course_group_id', $fixture['group']->id)
            ->firstOrFail();

        $this->assertSame(3, $demand->required_sessions_per_week);
        $this->assertSame(3, $demand->scheduled_sessions);
        $this->assertSame(0, $demand->unscheduled_sessions);
        $this->assertSame(3, AcademicPmcTimetableGenerationItem::where('generation_run_id', $run->id)->where('course_group_id', $fixture['group']->id)->where('status', 'scheduled')->count());
        $this->assertSame(3, $run->fresh()->scheduled_count);

        $this->actingAs($fixture['chair'])
            ->get(route('academics.pmc.timetable-generator.index'))
            ->assertOk()
            ->assertSee('Weekly Session Demand')
            ->assertSee('V061 Core Section A');
    }

    public function test_publish_syncs_generated_sessions_to_operational_timetable_entries(): void
    {
        $fixture = $this->seedFixture();

        $this->actingAs($fixture['chair'])->post(route('academics.pmc.timetable-generator.generate'), [
            'title' => 'V061 Publish Sync Timetable',
            'strategy' => 'balanced',
            'program_id' => $fixture['program']->id,
            'batch_id' => $fixture['batch']->id,
            'term_id' => $fixture['term']->id,
        ])->assertRedirect();

        $run = AcademicPmcTimetableGenerationRun::where('title', 'V061 Publish Sync Timetable')->firstOrFail();
        $this->assertSame(0, $run->hard_conflict_count);

        $this->actingAs($fixture['chair'])->post(route('academics.pmc.timetable-generator.publish', $run), [
            'decision_reason' => 'Publish demand-driven v0.061 timetable.',
            'effective_from' => now()->addDay()->toDateString(),
        ])->assertRedirect();

        $this->assertSame(3, TimetableEntry::where('program_id', $fixture['program']->id)->where('term_id', $fixture['term']->id)->where('status', 'published')->count());
        $this->assertSame(3, AcademicPmcTimetableGenerationItem::where('generation_run_id', $run->id)->whereNotNull('operational_timetable_entry_id')->count());
    }
}
