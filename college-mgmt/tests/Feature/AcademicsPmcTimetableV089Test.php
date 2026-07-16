<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcFacultyAssignmentAcknowledgement;
use App\Models\AcademicPmcFacultyPreference;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\AcademicPmcTimetablePublishCheck;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Program;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableSlot;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcTimetableV089Test extends TestCase
{
    use RefreshDatabase;

    public function test_launch_control_treats_faculty_suitability_as_a_faculty_allocation_gate(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-os.index'))
            ->assertOk()
            ->assertSee('Faculty allocation')
            ->assertSee('suitability/expertise gaps')
            ->assertSee('Faculty suitability blockers cleared');
    }

    public function test_faculty_suitability_blockers_create_publish_check_and_block_normal_pmc_publish(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'PMC v0.089 Publish Gate Draft',
            'strategy' => 'balanced',
            'created_by' => $chair->id,
            'status' => 'generated',
            'scheduled_count' => 1,
            'unscheduled_count' => 0,
            'hard_conflict_count' => 0,
            'soft_warning_count' => 0,
            'quality_score' => 92,
            'input_summary' => ['version' => 'PMC OS v0.089 test fixture'],
        ]);

        $department = Department::factory()->create(['code' => 'PMC089', 'name' => 'PMC v089 Department']);
        $program = Program::factory()->create([
            'department_id' => $department->id,
            'code' => 'PMC089',
            'name' => 'PMC v089 Program',
            'is_active' => true,
        ]);
        $subject = Subject::factory()->create([
            'department_id' => $department->id,
            'program_id' => $program->id,
            'code' => 'PMC089-SUB',
            'name' => 'PMC v089 Suitability Subject',
            'credits' => 3,
            'is_active' => true,
        ]);
        $teacher = Teacher::factory()->create([
            'user_id' => User::factory()->create(['name' => 'PMC v089 Visiting Faculty'])->id,
            'department_id' => $department->id,
            'employee_id' => 'PMC089-FAC',
            'designation' => 'Visiting Faculty',
            'employment_type' => 'visiting',
            'status' => 'active',
        ]);
        $group = AcademicPmcCourseGroup::create([
            'name' => 'PMC v089 Backup-Only Group',
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'owner_user_id' => $chair->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 25,
            'status' => 'active',
            'is_locked' => true,
        ]);
        $assignment = AcademicPmcGroupFacultyAssignment::create([
            'course_group_id' => $group->id,
            'teacher_id' => $teacher->id,
            'assignment_role' => 'backup',
            'assignment_source' => 'pmc',
            'approval_status' => 'draft',
            'weekly_hours' => 6,
            'is_backup' => true,
            'assigned_by' => $chair->id,
        ]);
        AcademicPmcFacultyPreference::create([
            'teacher_id' => $teacher->id,
            'faculty_type' => 'adjunct',
            'available_days' => [],
            'subject_expertise' => ['DIFFERENT-SUBJECT'],
            'max_classes_per_day' => 2,
            'max_consecutive_classes' => 2,
            'max_weekly_load' => 6,
        ]);
        AcademicPmcFacultyAssignmentAcknowledgement::create([
            'group_faculty_assignment_id' => $assignment->id,
            'teacher_id' => $teacher->id,
            'status' => 'concern_raised',
            'response_type' => 'decline',
            'faculty_note' => 'Cannot accept this allocation.',
            'requested_by' => $chair->id,
            'requested_at' => now()->subDay(),
        ]);
        $slot = TimetableSlot::factory()->create(['is_active' => true, 'sort_order' => 1]);
        $room = Classroom::factory()->create(['is_active' => true, 'capacity' => 60]);
        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'course_group_id' => $group->id,
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $slot->id,
            'status' => 'scheduled',
            'confidence' => 92,
        ]);

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-generator.index'))
            ->assertOk()
            ->assertSee('Faculty suitability before publish')
            ->assertSee('faculty suitability blocker');

        $this->assertDatabaseHas('academic_pmc_timetable_publish_checks', [
            'generation_run_id' => $run->id,
            'check_type' => 'faculty_suitability',
            'status' => 'block',
        ]);

        $this->actingAs($chair)
            ->post(route('academics.pmc.timetable-generator.publish', $run), [
                'decision_reason' => 'Try publish with suitability blockers.',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'Timetable publish blocked'));

        $this->assertTrue(
            AcademicPmcTimetablePublishCheck::where('generation_run_id', $run->id)
                ->where('check_type', 'faculty_suitability')
                ->where('status', 'block')
                ->exists()
        );
    }
}
