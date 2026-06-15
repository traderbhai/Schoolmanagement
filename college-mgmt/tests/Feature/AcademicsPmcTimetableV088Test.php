<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcFacultyAssignmentAcknowledgement;
use App\Models\AcademicPmcFacultyLoadReview;
use App\Models\AcademicPmcFacultyPreference;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\Department;
use App\Models\Program;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcTimetableV088Test extends TestCase
{
    use RefreshDatabase;

    public function test_faculty_suitability_diagnostics_render_on_timetable_and_faculty_pages(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-os.index'))
            ->assertOk()
            ->assertSee('Faculty Suitability Diagnostics')
            ->assertSee('Expertise Gaps')
            ->assertSee('Adjunct Day Risk')
            ->assertSee('Backup-Only Gap');

        $this->actingAs($chair)
            ->get(route('academics.pmc.section-faculty-allocation.index'))
            ->assertOk()
            ->assertSee('Faculty Suitability Diagnostics')
            ->assertSee('Ack Concerns')
            ->assertSee('Overload Risk');
    }

    public function test_faculty_suitability_diagnostics_count_real_assignment_readiness_blockers(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $department = Department::factory()->create(['code' => 'PMC088', 'name' => 'PMC v088 Department']);
        $program = Program::factory()->create([
            'department_id' => $department->id,
            'code' => 'PMC088',
            'name' => 'PMC v088 Program',
            'is_active' => true,
        ]);
        $subject = Subject::factory()->create([
            'department_id' => $department->id,
            'program_id' => $program->id,
            'code' => 'PMC088-SUB',
            'name' => 'PMC v088 Suitability Subject',
            'credits' => 3,
            'is_active' => true,
        ]);
        $teacher = Teacher::factory()->create([
            'user_id' => User::factory()->create(['name' => 'PMC v088 Visiting Faculty'])->id,
            'department_id' => $department->id,
            'employee_id' => 'PMC088-FAC',
            'designation' => 'Visiting Faculty',
            'employment_type' => 'visiting',
            'status' => 'active',
        ]);
        $group = AcademicPmcCourseGroup::create([
            'name' => 'PMC v088 Suitability Risk Group',
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'owner_user_id' => $chair->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 30,
            'status' => 'active',
            'is_locked' => true,
        ]);
        $assignment = AcademicPmcGroupFacultyAssignment::create([
            'course_group_id' => $group->id,
            'teacher_id' => $teacher->id,
            'assignment_role' => 'backup',
            'assignment_source' => 'pmc',
            'approval_status' => 'draft',
            'weekly_hours' => 8,
            'is_backup' => true,
            'assigned_by' => $chair->id,
            'notes' => 'Backup-only assignment to force suitability diagnostics.',
        ]);

        AcademicPmcFacultyPreference::create([
            'teacher_id' => $teacher->id,
            'faculty_type' => 'adjunct',
            'available_days' => [],
            'subject_expertise' => ['UNMATCHED-SUBJECT'],
            'unavailable_slots' => [['day' => 1, 'slot' => 1]],
            'max_classes_per_day' => 2,
            'max_consecutive_classes' => 2,
            'max_weekly_load' => 6,
            'restriction_notes' => 'Only available after industry commitment review.',
        ]);
        AcademicPmcFacultyAssignmentAcknowledgement::create([
            'group_faculty_assignment_id' => $assignment->id,
            'teacher_id' => $teacher->id,
            'status' => 'concern_raised',
            'response_type' => 'decline',
            'faculty_note' => 'Cannot take this subject in current slot pattern.',
            'constraints_raised' => ['adjunct_day_mismatch'],
            'requested_by' => $chair->id,
            'requested_at' => now()->subDay(),
        ]);
        AcademicPmcFacultyLoadReview::create([
            'teacher_id' => $teacher->id,
            'assigned_weekly_hours' => 16,
            'scheduled_classes' => 6,
            'max_classes_in_day' => 4,
            'max_consecutive_classes' => 3,
            'configured_weekly_limit' => 6,
            'configured_daily_limit' => 2,
            'load_band' => 'overload',
            'status' => 'approval_required',
            'risk_reasons' => ['adjunct_weekly_limit_exceeded'],
        ]);

        $this->actingAs($chair)
            ->get(route('academics.pmc.section-faculty-allocation.index'))
            ->assertOk()
            ->assertSee('Faculty Suitability Diagnostics')
            ->assertSee('PMC v088 Visiting Faculty')
            ->assertSee('Expertise Gaps')
            ->assertSee('Adjunct Day Risk')
            ->assertSee('Backup-Only Gap')
            ->assertSee('Resolve subject expertise gaps, adjunct-day constraints, acknowledgement concerns, overload approvals, and backup-only primary gaps before final timetable generation.');
    }
}
