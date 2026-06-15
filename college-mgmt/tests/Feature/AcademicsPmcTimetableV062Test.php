<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\AcademicPmcTimetableConstraint;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\AcademicPmcTimetableSessionDemand;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Program;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Services\AcademicPmcTimetableV041Service;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcTimetableV062Test extends TestCase
{
    use RefreshDatabase;

    private function seedLabFixture(): array
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);

        TimetableSlot::query()->update(['is_active' => false]);

        $department = Department::factory()->create(['code' => 'V062D', 'name' => 'V062 Timetable Department']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'V062', 'name' => 'V062 Timetable Program', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'V062-26', 'name' => 'V062 2026', 'status' => 'active']);
        $term = Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'V062 Term 1', 'is_current' => true]);
        $subject = Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'V062-LAB', 'name' => 'Analytics Lab Block', 'credits' => 2, 'type' => 'practical', 'is_active' => true]);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();
        $teacherUser = User::factory()->create(['name' => 'V062 Lab Faculty', 'email' => 'v062.lab.faculty@example.com', 'password' => bcrypt('password')]);
        $teacher = Teacher::create([
            'user_id' => $teacherUser->id,
            'department_id' => $department->id,
            'employee_id' => 'V062-FAC-001',
            'designation' => 'Assistant Professor',
            'qualification' => 'PhD',
            'specialization' => 'Timetable Labs',
            'status' => 'active',
        ]);

        $periodOne = TimetableSlot::create(['name' => 'V062 Period 1', 'start_time' => '09:00', 'end_time' => '10:00', 'is_break' => false, 'sort_order' => 9001, 'is_active' => true]);
        $lunch = TimetableSlot::create(['name' => 'V062 Lunch', 'start_time' => '10:00', 'end_time' => '10:30', 'is_break' => true, 'sort_order' => 9002, 'is_active' => true]);
        $periodTwo = TimetableSlot::create(['name' => 'V062 Period 2', 'start_time' => '10:30', 'end_time' => '11:30', 'is_break' => false, 'sort_order' => 9003, 'is_active' => true]);
        $periodThree = TimetableSlot::create(['name' => 'V062 Period 3', 'start_time' => '11:30', 'end_time' => '12:30', 'is_break' => false, 'sort_order' => 9004, 'is_active' => true]);

        Classroom::query()->update(['is_active' => false]);
        $lab = Classroom::create(['room_number' => 'V062-LAB-1', 'name' => 'V062 Analytics Lab', 'capacity' => 40, 'type' => 'lab', 'has_lab' => true, 'is_active' => true]);

        $group = AcademicPmcCourseGroup::create([
            'name' => 'V062 Lab Group L1',
            'group_type' => 'lab_group',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'owner_user_id' => $chair->id,
            'min_capacity' => 1,
            'max_capacity' => 40,
            'current_strength' => 20,
            'status' => 'ready',
            'constraints' => ['session_mix' => ['lab' => ['sessions' => 1, 'duration_slots' => 2]]],
        ]);

        AcademicPmcGroupFacultyAssignment::create([
            'course_group_id' => $group->id,
            'teacher_id' => $teacher->id,
            'assignment_role' => 'primary',
            'assignment_source' => 'pmc',
            'approval_status' => 'pmc_approved',
            'weekly_hours' => 2,
            'assigned_by' => $chair->id,
        ]);

        return compact('chair', 'program', 'batch', 'term', 'group', 'periodOne', 'lunch', 'periodTwo', 'periodThree', 'lab', 'teacher');
    }

    public function test_generator_does_not_span_lab_block_across_break_slot(): void
    {
        $fixture = $this->seedLabFixture();

        $this->actingAs($fixture['chair'])->post(route('academics.pmc.timetable-generator.generate'), [
            'title' => 'V062 Break Safe Lab Timetable',
            'strategy' => 'balanced',
            'program_id' => $fixture['program']->id,
            'batch_id' => $fixture['batch']->id,
            'term_id' => $fixture['term']->id,
        ])->assertRedirect();

        $run = AcademicPmcTimetableGenerationRun::where('title', 'V062 Break Safe Lab Timetable')->firstOrFail();
        $demand = AcademicPmcTimetableSessionDemand::where('generation_run_id', $run->id)->where('course_group_id', $fixture['group']->id)->firstOrFail();
        $item = AcademicPmcTimetableGenerationItem::where('generation_run_id', $run->id)->where('course_group_id', $fixture['group']->id)->firstOrFail();

        $this->assertSame(2, $demand->duration_slots);
        $this->assertSame('scheduled', $item->status);
        $this->assertSame($fixture['periodTwo']->id, $item->timetable_slot_id);
        $this->assertNotSame($fixture['periodOne']->id, $item->timetable_slot_id, 'A two-slot lab cannot start before lunch because the next physical slot is a break.');
        $this->assertSame(0, $run->fresh()->hard_conflict_count);
        $this->assertSame(1, $run->fresh()->input_summary['break_slots']);
        $this->assertSame('PMC OS v0.062', $run->fresh()->input_summary['version']);
    }

    public function test_validation_flags_break_slots_and_incomplete_multi_slot_blocks(): void
    {
        $fixture = $this->seedLabFixture();

        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'V062 Bad Manual Timetable',
            'strategy' => 'manual',
            'program_id' => $fixture['program']->id,
            'batch_id' => $fixture['batch']->id,
            'term_id' => $fixture['term']->id,
            'created_by' => $fixture['chair']->id,
            'status' => 'generated',
            'input_summary' => ['version' => 'PMC OS v0.062'],
        ]);

        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'course_group_id' => $fixture['group']->id,
            'session_type' => 'lab',
            'duration_slots' => 2,
            'teacher_id' => $fixture['teacher']->id,
            'classroom_id' => $fixture['lab']->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $fixture['lunch']->id,
            'status' => 'scheduled',
            'explanation' => 'Bad manual placement for validation test.',
        ]);

        app(AcademicPmcTimetableV041Service::class)->refreshConstraintsAndQuality($run);

        $this->assertTrue(AcademicPmcTimetableConstraint::where('generation_run_id', $run->id)->where('constraint_type', 'break_slot_used')->exists());
        $this->assertTrue(AcademicPmcTimetableConstraint::where('generation_run_id', $run->id)->where('constraint_type', 'multi_slot_block_incomplete')->exists());
        $this->assertGreaterThanOrEqual(2, $run->fresh()->hard_conflict_count);
    }
}
