<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\AcademicPmcTimetableGenerationItem;
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

class AcademicsPmcTimetableV065Test extends TestCase
{
    use RefreshDatabase;

    public function test_generator_stores_top_alternative_candidates_for_manual_pmc_review(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);

        TimetableSlot::query()->update(['is_active' => false]);
        Classroom::query()->update(['is_active' => false]);

        $department = Department::factory()->create(['code' => 'V065D', 'name' => 'V065 Timetable Department']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'V065', 'name' => 'V065 Program', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'V065-26', 'name' => 'V065 2026', 'status' => 'active']);
        $term = Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'V065 Term 1', 'is_current' => true]);
        $subject = Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'V065101', 'name' => 'Solver Explainability', 'credits' => 2, 'is_active' => true]);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();
        $teacherUser = User::factory()->create(['name' => 'V065 Faculty', 'email' => 'v065.faculty@example.com', 'password' => bcrypt('password')]);
        $teacher = Teacher::create(['user_id' => $teacherUser->id, 'department_id' => $department->id, 'employee_id' => 'V065-FAC-001', 'designation' => 'Assistant Professor', 'qualification' => 'PhD', 'specialization' => 'Scheduling', 'status' => 'active']);

        foreach (range(1, 4) as $index) {
            TimetableSlot::create([
                'name' => 'V065 Period ' . $index,
                'start_time' => sprintf('%02d:00', 8 + $index),
                'end_time' => sprintf('%02d:00', 9 + $index),
                'is_break' => false,
                'sort_order' => 9800 + $index,
                'is_active' => true,
            ]);
        }
        Classroom::create(['room_number' => 'V065-101', 'name' => 'V065 Small Room', 'capacity' => 45, 'type' => 'lecture', 'is_active' => true]);
        Classroom::create(['room_number' => 'V065-201', 'name' => 'V065 Large Room', 'capacity' => 100, 'type' => 'lecture', 'is_active' => true]);

        $group = AcademicPmcCourseGroup::create([
            'name' => 'V065 Explainable Section',
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'owner_user_id' => $chair->id,
            'min_capacity' => 1,
            'max_capacity' => 80,
            'current_strength' => 40,
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
            'title' => 'V065 Explainable Timetable',
            'strategy' => 'room_optimized',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
        ])->assertRedirect();

        $item = AcademicPmcTimetableGenerationItem::whereHas('generationRun', fn ($query) => $query->where('title', 'V065 Explainable Timetable'))
            ->where('course_group_id', $group->id)
            ->firstOrFail();

        $this->assertSame('PMC OS v0.065', $item->metadata['version']);
        $this->assertNotEmpty($item->metadata['placement_alternatives']);
        $this->assertLessThanOrEqual(3, count($item->metadata['placement_alternatives']));
        $this->assertArrayHasKey('day', $item->metadata['placement_alternatives'][0]);
        $this->assertArrayHasKey('room_name', $item->metadata['placement_alternatives'][0]);
        $this->assertArrayHasKey('reasons', $item->metadata['placement_alternatives'][0]);

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-generator.index'))
            ->assertOk()
            ->assertSee('Solver Reason')
            ->assertSee('Alt:');
    }
}
