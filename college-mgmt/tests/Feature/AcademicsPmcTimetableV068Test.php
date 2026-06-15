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

class AcademicsPmcTimetableV068Test extends TestCase
{
    use RefreshDatabase;

    private function seedManualMoveFixture(): array
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);

        TimetableSlot::query()->update(['is_active' => false]);
        Classroom::query()->update(['is_active' => false]);

        $department = Department::factory()->create(['code' => 'V068D', 'name' => 'V068 Timetable Department']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'V068', 'name' => 'V068 Program', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'V068-26', 'name' => 'V068 2026', 'status' => 'active']);
        $term = Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'V068 Term 1', 'is_current' => true]);
        $subject = Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'V068101', 'name' => 'Manual Timetable Move', 'credits' => 1, 'is_active' => true]);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();
        $dean = User::where('email', 'dean@college.com')->firstOrFail();
        $teacherUser = User::factory()->create(['name' => 'V068 Faculty', 'email' => 'v068.faculty@example.com', 'password' => bcrypt('password')]);
        $teacher = Teacher::create(['user_id' => $teacherUser->id, 'department_id' => $department->id, 'employee_id' => 'V068-FAC-001', 'designation' => 'Assistant Professor', 'qualification' => 'PhD', 'specialization' => 'Manual Timetable Review', 'status' => 'active']);

        $slotOne = TimetableSlot::create(['name' => 'V068 Period 1', 'start_time' => '09:00', 'end_time' => '10:00', 'is_break' => false, 'sort_order' => 9961, 'is_active' => true]);
        $slotTwo = TimetableSlot::create(['name' => 'V068 Period 2', 'start_time' => '10:00', 'end_time' => '11:00', 'is_break' => false, 'sort_order' => 9962, 'is_active' => true]);
        $slotThree = TimetableSlot::create(['name' => 'V068 Period 3', 'start_time' => '11:00', 'end_time' => '12:00', 'is_break' => false, 'sort_order' => 9963, 'is_active' => true]);
        $roomA = Classroom::create(['room_number' => 'V068-101', 'name' => 'V068 Room A', 'capacity' => 60, 'type' => 'lecture', 'is_active' => true]);
        $roomB = Classroom::create(['room_number' => 'V068-102', 'name' => 'V068 Room B', 'capacity' => 60, 'type' => 'lecture', 'is_active' => true]);

        $groupA = AcademicPmcCourseGroup::create(['name' => 'V068 Section A', 'group_type' => 'core_section', 'program_id' => $program->id, 'batch_id' => $batch->id, 'term_id' => $term->id, 'subject_id' => $subject->id, 'owner_user_id' => $chair->id, 'min_capacity' => 1, 'max_capacity' => 60, 'current_strength' => 35, 'status' => 'ready']);
        $groupB = AcademicPmcCourseGroup::create(['name' => 'V068 Section B', 'group_type' => 'core_section', 'program_id' => $program->id, 'batch_id' => $batch->id, 'term_id' => $term->id, 'subject_id' => $subject->id, 'owner_user_id' => $chair->id, 'min_capacity' => 1, 'max_capacity' => 60, 'current_strength' => 30, 'status' => 'ready']);

        $run = AcademicPmcTimetableGenerationRun::create(['title' => 'V068 Manual Move Timetable', 'strategy' => 'manual', 'program_id' => $program->id, 'batch_id' => $batch->id, 'term_id' => $term->id, 'created_by' => $chair->id, 'status' => 'generated', 'input_summary' => ['version' => 'PMC OS v0.068']]);
        $itemA = AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'course_group_id' => $groupA->id,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $teacher->id,
            'classroom_id' => $roomA->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $slotOne->id,
            'status' => 'scheduled',
            'confidence' => 90,
            'metadata' => ['version' => 'PMC OS v0.065', 'placement_score' => 87, 'placement_reasons' => ['initial placement']],
        ]);
        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'course_group_id' => $groupB->id,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $teacher->id,
            'classroom_id' => $roomA->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $slotTwo->id,
            'status' => 'scheduled',
            'confidence' => 90,
        ]);

        return compact('chair', 'dean', 'itemA', 'slotOne', 'slotTwo', 'slotThree', 'roomA', 'roomB', 'run');
    }

    public function test_pmc_can_manually_move_generated_item_without_new_hard_conflict(): void
    {
        $fixture = $this->seedManualMoveFixture();

        $this->actingAs($fixture['chair'])->post(route('academics.pmc.timetable-generator-items.move', $fixture['itemA']), [
            'day_of_week' => 1,
            'timetable_slot_id' => $fixture['slotThree']->id,
            'classroom_id' => $fixture['roomB']->id,
            'decision_note' => 'Move to keep student timetable compact.',
        ])->assertRedirect();

        $fixture['itemA']->refresh();
        $fixture['run']->refresh();

        $this->assertSame($fixture['slotThree']->id, $fixture['itemA']->timetable_slot_id);
        $this->assertSame($fixture['roomB']->id, $fixture['itemA']->classroom_id);
        $this->assertSame('PMC OS v0.068', $fixture['itemA']->metadata['version']);
        $this->assertSame($fixture['slotOne']->id, $fixture['itemA']->metadata['previous_placement']['slot_id']);
        $this->assertSame('Move to keep student timetable compact.', $fixture['itemA']->metadata['manual_move']['decision_note']);
        $this->assertSame(0, (int) $fixture['run']->hard_conflict_count);
        $this->assertTrue(DepartmentActivityLog::where('action', 'academic_pmc_v068_manual_timetable_item_moved')->exists());

        $this->actingAs($fixture['chair'])
            ->get(route('academics.pmc.timetable-generator.index'))
            ->assertOk()
            ->assertSee('Manual Move')
            ->assertSee('Move');
    }

    public function test_manual_move_rolls_back_for_pmc_but_dean_can_override_with_reason(): void
    {
        $fixture = $this->seedManualMoveFixture();

        $this->actingAs($fixture['chair'])->post(route('academics.pmc.timetable-generator-items.move', $fixture['itemA']), [
            'day_of_week' => 1,
            'timetable_slot_id' => $fixture['slotTwo']->id,
            'classroom_id' => $fixture['roomA']->id,
            'decision_note' => 'Try conflicting manual move.',
        ])->assertStatus(422);

        $fixture['itemA']->refresh();
        $this->assertSame($fixture['slotOne']->id, $fixture['itemA']->timetable_slot_id);
        $this->assertSame($fixture['roomA']->id, $fixture['itemA']->classroom_id);
        $this->assertSame('hard_conflict_introduced', $fixture['itemA']->metadata['last_blocked_manual_move']['reason']);

        $this->actingAs($fixture['dean'])->post(route('academics.pmc.timetable-generator-items.move', $fixture['itemA']), [
            'day_of_week' => 1,
            'timetable_slot_id' => $fixture['slotTwo']->id,
            'classroom_id' => $fixture['roomA']->id,
            'decision_note' => 'Dean accepts clash for temporary offline adjustment.',
            'allow_hard_conflict_override' => true,
            'override_reason' => 'Temporary common session forces a manual exception.',
        ])->assertRedirect();

        $fixture['itemA']->refresh();
        $fixture['run']->refresh();
        $this->assertSame($fixture['slotTwo']->id, $fixture['itemA']->timetable_slot_id);
        $this->assertSame('PMC OS v0.068', $fixture['itemA']->metadata['version']);
        $this->assertTrue($fixture['itemA']->metadata['manual_move']['hard_conflict_override']);
        $this->assertSame('Temporary common session forces a manual exception.', $fixture['itemA']->metadata['manual_move']['override_reason']);
        $this->assertGreaterThan(0, (int) $fixture['run']->hard_conflict_count);
        $this->assertTrue(DepartmentActivityLog::where('action', 'academic_pmc_v068_manual_move_hard_conflict_override')->exists());
    }
}
