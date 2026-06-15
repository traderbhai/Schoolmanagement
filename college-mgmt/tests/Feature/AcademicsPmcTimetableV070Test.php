<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\AcademicPmcTimetableImpactRecord;
use App\Models\AcademicPmcTimetableVersionWorkflow;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\DepartmentActivityLog;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcTimetableV070Test extends TestCase
{
    use RefreshDatabase;

    public function test_publish_automatically_refreshes_generation_impact_preview_and_stores_it_in_workflow_summary(): void
    {
        $department = Department::factory()->create(['code' => 'V070D', 'name' => 'V070 Timetable Department']);
        Program::factory()->create(['department_id' => $department->id, 'code' => 'BASE', 'name' => 'Base Program', 'is_active' => true]);
        Semester::factory()->create(['number' => 1, 'is_current' => true]);
        $this->seed(AcademicsOperatingDemoSeeder::class);

        TimetableSlot::query()->update(['is_active' => false]);
        Classroom::query()->update(['is_active' => false]);

        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'V070', 'name' => 'V070 Program', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'V070-26', 'name' => 'V070 2026', 'status' => 'active']);
        $term = Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'V070 Term 1', 'is_current' => true]);
        $subject = Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'V070101', 'name' => 'Publish Impact Governance', 'credits' => 2, 'is_active' => true]);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();
        $teacherUser = User::factory()->create(['name' => 'V070 Faculty', 'email' => 'v070.faculty@example.com', 'password' => bcrypt('password')]);
        $teacher = Teacher::create(['user_id' => $teacherUser->id, 'department_id' => $department->id, 'employee_id' => 'V070-FAC-001', 'designation' => 'Assistant Professor', 'qualification' => 'PhD', 'specialization' => 'Publish Impact', 'status' => 'active']);
        Classroom::create(['room_number' => 'V070-101', 'name' => 'V070 Room', 'capacity' => 80, 'type' => 'lecture', 'is_active' => true]);
        foreach ([1, 2] as $index) {
            TimetableSlot::create(['name' => 'V070 Period ' . $index, 'start_time' => sprintf('%02d:00', 8 + $index), 'end_time' => sprintf('%02d:00', 9 + $index), 'is_break' => false, 'sort_order' => 9980 + $index, 'is_active' => true]);
        }

        $group = AcademicPmcCourseGroup::create([
            'name' => 'V070 Core Section A',
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
            'constraints' => ['weekly_hours' => 2],
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
            'title' => 'V070 Publish Impact Timetable',
            'strategy' => 'balanced',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
        ])->assertRedirect();

        $run = AcademicPmcTimetableGenerationRun::where('title', 'V070 Publish Impact Timetable')->firstOrFail();
        $this->assertSame(0, (int) $run->hard_conflict_count);

        $this->actingAs($chair)->post(route('academics.pmc.timetable-generator.publish', $run), [
            'decision_reason' => 'Publish after automatic impact refresh.',
            'effective_from' => now()->addDay()->toDateString(),
        ])->assertRedirect();

        $run->refresh();
        $workflow = AcademicPmcTimetableVersionWorkflow::where('generation_run_id', $run->id)->where('lifecycle_status', 'published')->firstOrFail();
        $this->assertSame(7, AcademicPmcTimetableImpactRecord::where('metadata->generation_run_id', $run->id)->where('metadata->version', 'PMC OS v0.069')->count());
        $this->assertSame('PMC OS v0.070', $workflow->publish_summary['impact_preview']['version']);
        $this->assertSame(7, $workflow->publish_summary['impact_preview']['impact_records']);
        $this->assertSame(1, $workflow->publish_summary['impact_preview']['affected_faculty']);
        $this->assertSame(1, $workflow->publish_summary['impact_preview']['affected_rooms']);
        $this->assertTrue(DepartmentActivityLog::where('action', 'academic_pmc_v069_timetable_impact_preview_refreshed')->where('subject_id', $run->id)->exists());
    }
}
