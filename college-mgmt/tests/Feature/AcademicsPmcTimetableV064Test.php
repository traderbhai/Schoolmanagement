<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Program;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcTimetableV064Test extends TestCase
{
    use RefreshDatabase;

    public function test_student_compact_strategy_ranks_adjacent_same_day_slot_above_next_day_slot(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);

        TimetableSlot::query()->update(['is_active' => false]);
        Classroom::query()->update(['is_active' => false]);

        $department = Department::factory()->create(['code' => 'V064D', 'name' => 'V064 Timetable Department']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'V064', 'name' => 'V064 Program', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'V064-26', 'name' => 'V064 2026', 'status' => 'active']);
        $term = Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'V064 Term 1', 'is_current' => true]);
        $subject = Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'V064101', 'name' => 'Strategy-Aware Scheduling', 'credits' => 2, 'is_active' => true]);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();
        $teacherUser = User::factory()->create(['name' => 'V064 Faculty', 'email' => 'v064.faculty@example.com', 'password' => bcrypt('password')]);
        $teacher = Teacher::create(['user_id' => $teacherUser->id, 'department_id' => $department->id, 'employee_id' => 'V064-FAC-001', 'designation' => 'Assistant Professor', 'qualification' => 'PhD', 'specialization' => 'Scheduling Strategy', 'status' => 'active']);

        $slotOne = TimetableSlot::create(['name' => 'V064 Period 1', 'start_time' => '09:00', 'end_time' => '10:00', 'is_break' => false, 'sort_order' => 9701, 'is_active' => true]);
        $slotTwo = TimetableSlot::create(['name' => 'V064 Period 2', 'start_time' => '10:00', 'end_time' => '11:00', 'is_break' => false, 'sort_order' => 9702, 'is_active' => true]);
        Classroom::create(['room_number' => 'V064-101', 'name' => 'V064 Lecture Room', 'capacity' => 60, 'type' => 'lecture', 'is_active' => true]);

        $group = AcademicPmcCourseGroup::create([
            'name' => 'V064 Compact Core Section',
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'owner_user_id' => $chair->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 50,
            'status' => 'ready',
            'constraints' => ['weekly_sessions' => 2],
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

        $this->actingAs($chair)->post(route('academics.pmc.timetable-generator.generate'), [
            'title' => 'V064 Student Compact Timetable',
            'strategy' => 'student_compact',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
        ])->assertRedirect();

        $run = AcademicPmcTimetableGenerationRun::where('title', 'V064 Student Compact Timetable')->firstOrFail();
        $items = AcademicPmcTimetableGenerationItem::where('generation_run_id', $run->id)
            ->where('course_group_id', $group->id)
            ->where('status', 'scheduled')
            ->orderBy('session_index')
            ->get();

        $this->assertCount(2, $items);
        $this->assertSame(1, (int) $items[0]->day_of_week);
        $this->assertSame($slotOne->id, $items[0]->timetable_slot_id);
        $this->assertSame(1, (int) $items[1]->day_of_week);
        $this->assertSame($slotTwo->id, $items[1]->timetable_slot_id);
        $this->assertContains('strategy_student_compact', $items[1]->metadata['placement_reasons']);
        $this->assertContains('keeps_student_day_compact', $items[1]->metadata['placement_reasons']);
        $this->assertNotEmpty($items[1]->metadata['placement_alternatives']);
        $this->assertArrayHasKey('score', $items[1]->metadata['placement_alternatives'][0]);
        $this->assertArrayHasKey('slot_name', $items[1]->metadata['placement_alternatives'][0]);
        $this->assertTrue($items[1]->metadata['placement_score'] >= $items[0]->metadata['placement_score']);
        $this->assertTrue(AcademicPmcTimetableGenerationRun::whereKey($run->id)->where('strategy', 'student_compact')->exists());

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-generator.index'))
            ->assertOk()
            ->assertSee('Solver Reason')
            ->assertSee('Score')
            ->assertSee('Alt:');
    }
}
