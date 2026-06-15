<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\DepartmentActivityLog;
use App\Models\Program;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcTimetableV067Test extends TestCase
{
    use RefreshDatabase;

    private function seedConflictFixture(): array
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);

        TimetableSlot::query()->update(['is_active' => false]);
        Classroom::query()->update(['is_active' => false]);

        $department = Department::factory()->create(['code' => 'V067D', 'name' => 'V067 Timetable Department']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'V067', 'name' => 'V067 Program', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'V067-26', 'name' => 'V067 2026', 'status' => 'active']);
        $term = Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'V067 Term 1', 'is_current' => true]);
        $subject = Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'V067101', 'name' => 'Safe Alternative Apply', 'credits' => 1, 'is_active' => true]);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();
        $dean = User::where('email', 'dean@college.com')->firstOrFail();
        $teacherUser = User::factory()->create(['name' => 'V067 Faculty', 'email' => 'v067.faculty@example.com', 'password' => bcrypt('password')]);
        $teacher = Teacher::create(['user_id' => $teacherUser->id, 'department_id' => $department->id, 'employee_id' => 'V067-FAC-001', 'designation' => 'Assistant Professor', 'qualification' => 'PhD', 'specialization' => 'Timetable Safety', 'status' => 'active']);

        $slotOne = TimetableSlot::create(['name' => 'V067 Period 1', 'start_time' => '09:00', 'end_time' => '10:00', 'is_break' => false, 'sort_order' => 9951, 'is_active' => true]);
        $slotTwo = TimetableSlot::create(['name' => 'V067 Period 2', 'start_time' => '10:00', 'end_time' => '11:00', 'is_break' => false, 'sort_order' => 9952, 'is_active' => true]);
        $room = Classroom::create(['room_number' => 'V067-101', 'name' => 'V067 Room', 'capacity' => 60, 'type' => 'lecture', 'is_active' => true]);

        $groupA = AcademicPmcCourseGroup::create(['name' => 'V067 Section A', 'group_type' => 'core_section', 'program_id' => $program->id, 'batch_id' => $batch->id, 'term_id' => $term->id, 'subject_id' => $subject->id, 'owner_user_id' => $chair->id, 'min_capacity' => 1, 'max_capacity' => 60, 'current_strength' => 35, 'status' => 'ready']);
        $groupB = AcademicPmcCourseGroup::create(['name' => 'V067 Section B', 'group_type' => 'core_section', 'program_id' => $program->id, 'batch_id' => $batch->id, 'term_id' => $term->id, 'subject_id' => $subject->id, 'owner_user_id' => $chair->id, 'min_capacity' => 1, 'max_capacity' => 60, 'current_strength' => 30, 'status' => 'ready']);

        $run = AcademicPmcTimetableGenerationRun::create(['title' => 'V067 Safe Apply Timetable', 'strategy' => 'manual', 'program_id' => $program->id, 'batch_id' => $batch->id, 'term_id' => $term->id, 'created_by' => $chair->id, 'status' => 'generated', 'input_summary' => ['version' => 'PMC OS v0.067']]);
        $itemA = AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'course_group_id' => $groupA->id,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $slotOne->id,
            'status' => 'scheduled',
            'confidence' => 90,
            'metadata' => [
                'version' => 'PMC OS v0.065',
                'placement_alternatives' => [[
                    'day' => 1,
                    'slot_id' => $slotTwo->id,
                    'slot_name' => $slotTwo->name,
                    'room_id' => $room->id,
                    'room_name' => $room->name,
                    'score' => 82,
                    'reasons' => ['test_conflicting_alternative'],
                ]],
            ],
        ]);
        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'course_group_id' => $groupB->id,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $slotTwo->id,
            'status' => 'scheduled',
            'confidence' => 90,
        ]);

        return compact('chair', 'dean', 'itemA', 'slotOne', 'slotTwo', 'room', 'run');
    }

    public function test_pmc_apply_alternative_rolls_back_when_it_introduces_hard_conflict(): void
    {
        $fixture = $this->seedConflictFixture();

        $this->actingAs($fixture['chair'])->post(route('academics.pmc.timetable-generator-items.apply-alternative', $fixture['itemA']), [
            'alternative_index' => 0,
            'decision_note' => 'Try conflicting move.',
        ])->assertStatus(422);

        $fixture['itemA']->refresh();
        $this->assertSame($fixture['slotOne']->id, $fixture['itemA']->timetable_slot_id);
        $this->assertSame('hard_conflict_introduced', $fixture['itemA']->metadata['last_blocked_solver_alternative']['reason']);
        $this->assertFalse(DepartmentActivityLog::where('action', 'academic_pmc_v066_solver_alternative_applied')->exists());
    }

    public function test_dean_can_apply_conflicting_alternative_with_explicit_override_reason(): void
    {
        $fixture = $this->seedConflictFixture();

        $this->actingAs($fixture['dean'])->post(route('academics.pmc.timetable-generator-items.apply-alternative', $fixture['itemA']), [
            'alternative_index' => 0,
            'decision_note' => 'Dean accepts conflict for emergency room repair.',
            'allow_hard_conflict_override' => true,
            'override_reason' => 'Room repair forces temporary clash for one day.',
        ])->assertRedirect();

        $fixture['itemA']->refresh();
        $fixture['run']->refresh();
        $this->assertSame($fixture['slotTwo']->id, $fixture['itemA']->timetable_slot_id);
        $this->assertSame('PMC OS v0.067', $fixture['itemA']->metadata['version']);
        $this->assertTrue($fixture['itemA']->metadata['applied_solver_alternative']['hard_conflict_override']);
        $this->assertSame('Room repair forces temporary clash for one day.', $fixture['itemA']->metadata['applied_solver_alternative']['override_reason']);
        $this->assertGreaterThan(0, $fixture['run']->hard_conflict_count);
        $this->assertTrue(DepartmentActivityLog::where('action', 'academic_pmc_v067_solver_alternative_hard_conflict_override')->exists());
    }
}
