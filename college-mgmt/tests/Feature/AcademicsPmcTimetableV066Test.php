<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\AcademicPmcTimetableGenerationItem;
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

class AcademicsPmcTimetableV066Test extends TestCase
{
    use RefreshDatabase;

    public function test_pmc_can_apply_solver_alternative_and_quality_is_refreshed(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);

        TimetableSlot::query()->update(['is_active' => false]);
        Classroom::query()->update(['is_active' => false]);

        $department = Department::factory()->create(['code' => 'V066D', 'name' => 'V066 Timetable Department']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'V066', 'name' => 'V066 Program', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'V066-26', 'name' => 'V066 2026', 'status' => 'active']);
        $term = Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'V066 Term 1', 'is_current' => true]);
        $subject = Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'V066101', 'name' => 'Alternative Application', 'credits' => 1, 'is_active' => true]);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();
        $teacherUser = User::factory()->create(['name' => 'V066 Faculty', 'email' => 'v066.faculty@example.com', 'password' => bcrypt('password')]);
        $teacher = Teacher::create(['user_id' => $teacherUser->id, 'department_id' => $department->id, 'employee_id' => 'V066-FAC-001', 'designation' => 'Assistant Professor', 'qualification' => 'PhD', 'specialization' => 'Scheduling', 'status' => 'active']);

        foreach (range(1, 3) as $index) {
            TimetableSlot::create([
                'name' => 'V066 Period ' . $index,
                'start_time' => sprintf('%02d:00', 8 + $index),
                'end_time' => sprintf('%02d:00', 9 + $index),
                'is_break' => false,
                'sort_order' => 9900 + $index,
                'is_active' => true,
            ]);
        }
        Classroom::create(['room_number' => 'V066-101', 'name' => 'V066 Room A', 'capacity' => 40, 'type' => 'lecture', 'is_active' => true]);
        Classroom::create(['room_number' => 'V066-102', 'name' => 'V066 Room B', 'capacity' => 60, 'type' => 'lecture', 'is_active' => true]);

        $group = AcademicPmcCourseGroup::create([
            'name' => 'V066 Alternative Section',
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'owner_user_id' => $chair->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 35,
            'status' => 'ready',
            'constraints' => ['weekly_sessions' => 1],
        ]);

        AcademicPmcGroupFacultyAssignment::create([
            'course_group_id' => $group->id,
            'teacher_id' => $teacher->id,
            'assignment_role' => 'primary',
            'assignment_source' => 'pmc',
            'approval_status' => 'pmc_approved',
            'weekly_hours' => 1,
            'assigned_by' => $chair->id,
        ]);

        $this->actingAs($chair)->post(route('academics.pmc.timetable-generator.generate'), [
            'title' => 'V066 Alternative Timetable',
            'strategy' => 'room_optimized',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
        ])->assertRedirect();

        $item = AcademicPmcTimetableGenerationItem::whereHas('generationRun', fn ($query) => $query->where('title', 'V066 Alternative Timetable'))
            ->where('course_group_id', $group->id)
            ->firstOrFail();
        $alternative = $item->metadata['placement_alternatives'][0];
        $previousDay = $item->day_of_week;
        $previousSlot = $item->timetable_slot_id;

        $this->actingAs($chair)->post(route('academics.pmc.timetable-generator-items.apply-alternative', $item), [
            'alternative_index' => 0,
            'decision_note' => 'Use alternate slot to reduce room pressure.',
        ])->assertRedirect();

        $item->refresh();

        $this->assertSame((int) $alternative['day'], (int) $item->day_of_week);
        $this->assertSame((int) $alternative['slot_id'], (int) $item->timetable_slot_id);
        $this->assertSame((int) $alternative['room_id'], (int) $item->classroom_id);
        $this->assertSame('PMC OS v0.067', $item->metadata['version']);
        $this->assertSame($previousDay, $item->metadata['previous_placement']['day']);
        $this->assertSame($previousSlot, $item->metadata['previous_placement']['slot_id']);
        $this->assertSame('Use alternate slot to reduce room pressure.', $item->metadata['applied_solver_alternative']['decision_note']);
        $this->assertTrue($item->generationRun->quality_score !== null);
        $this->assertTrue(DepartmentActivityLog::where('action', 'academic_pmc_v066_solver_alternative_applied')->exists());

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-generator.index'))
            ->assertOk()
            ->assertSee('Apply D');
    }
}
