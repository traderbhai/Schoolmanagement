<?php

namespace Tests\Unit;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcRoomCapability;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Program;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Services\TimetableOptimizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimetableOptimizationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_optimizer_builds_demand_and_solves_canonical_sessions(): void
    {
        $fixture = $this->fixture();

        $service = app(TimetableOptimizationService::class);
        $demand = $service->buildDemand([
            'program_id' => $fixture['program']->id,
            'batch_id' => $fixture['batch']->id,
            'term_id' => $fixture['term']->id,
        ]);

        $this->assertCount(1, $demand);
        $this->assertSame('lecture', $demand->first()['session_type']);

        $run = $service->solve($fixture['user'], [
            'program_id' => $fixture['program']->id,
            'batch_id' => $fixture['batch']->id,
            'term_id' => $fixture['term']->id,
        ], [
            'title' => 'Optimizer Unit Run',
            'strategy' => 'room_optimized',
        ]);

        $this->assertSame(1, (int) $run->scheduled_count);
        $this->assertSame(0, (int) $run->unscheduled_count);
        $this->assertDatabaseHas('academic_pmc_timetable_generation_items', [
            'generation_run_id' => $run->id,
            'course_group_id' => $fixture['group']->id,
            'status' => 'scheduled',
            'source_type' => 'optimizer',
        ]);
    }

    public function test_optimizer_exposes_candidate_scoring_and_rejection_reasons(): void
    {
        $fixture = $this->fixture();
        $service = app(TimetableOptimizationService::class);

        $score = $service->scoreCandidate([
            'group' => $fixture['group'],
            'room' => $fixture['room'],
            'teacher_day_load' => 0,
            'group_day_load' => 0,
            'preferred_slot' => true,
            'strategy' => 'faculty_balanced',
        ]);

        $this->assertGreaterThan(0, $score['score']);
        $this->assertContains('faculty_preferred_slot', $score['reasons']);

        $reasons = $service->explainRejectedCandidate([
            'group' => $fixture['group'],
            'teacher_id' => null,
        ]);

        $this->assertContains('missing_primary_faculty', $reasons);
    }

    public function test_optimizer_accepts_lab_room_capability_records(): void
    {
        $fixture = $this->fixture(['group_type' => 'lab_group']);
        $fixture['room']->update(['has_lab' => false, 'type' => 'lecture']);
        AcademicPmcRoomCapability::create([
            'classroom_id' => $fixture['room']->id,
            'capability_type' => 'lab',
            'capability_key' => 'computer_lab',
            'capability_value' => 'available',
            'is_active' => true,
        ]);
        TimetableSlot::factory()->create(['sort_order' => 2, 'is_active' => true, 'is_break' => false]);

        $run = app(TimetableOptimizationService::class)->solve($fixture['user'], [
            'program_id' => $fixture['program']->id,
            'batch_id' => $fixture['batch']->id,
            'term_id' => $fixture['term']->id,
        ], [
            'title' => 'Capability Lab Run',
        ]);

        $this->assertSame(1, (int) $run->scheduled_count);
    }

    private function fixture(array $overrides = []): array
    {
        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'Term 1',
        ]);
        $subject = Subject::factory()->create(['program_id' => $program->id, 'credits' => 1]);
        $teacher = Teacher::factory()->create();
        $room = Classroom::factory()->create(['capacity' => 40, 'is_active' => true]);
        TimetableSlot::factory()->create(['sort_order' => 1, 'is_active' => true, 'is_break' => false]);
        $group = AcademicPmcCourseGroup::create([
            'name' => 'Optimizer Section A',
            'group_type' => $overrides['group_type'] ?? 'core_section',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'min_capacity' => 1,
            'max_capacity' => 40,
            'current_strength' => 30,
            'status' => 'active',
            'is_locked' => true,
        ]);
        AcademicPmcGroupFacultyAssignment::create([
            'course_group_id' => $group->id,
            'teacher_id' => $teacher->id,
            'assignment_role' => 'primary',
            'weekly_hours' => 1,
            'approval_status' => 'approved',
        ]);

        return [
            'program' => $program,
            'batch' => $batch,
            'term' => $term,
            'group' => $group,
            'room' => $room,
            'user' => User::factory()->create(),
        ];
    }
}
