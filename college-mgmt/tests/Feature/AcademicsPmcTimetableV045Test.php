<?php

namespace Tests\Feature;

use App\Models\AcademicPmcTimetableConstraint;
use App\Models\AcademicPmcTimetablePublishCheck;
use App\Models\AcademicPmcTimetableResolutionAction;
use App\Models\Batch;
use App\Models\Department;
use App\Models\Program;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcTimetableV045Test extends TestCase
{
    use RefreshDatabase;

    private function seedFixture(): User
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'PGDM-26', 'name' => 'PGDM 2026', 'status' => 'active']);
        Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'Term 1', 'is_current' => true]);
        Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT401', 'name' => 'Management Analytics', 'credits' => 3, 'is_active' => true]);
        $studentUser = User::factory()->create(['name' => 'PMC v045 Student']);
        Student::factory()->create(['user_id' => $studentUser->id, 'department_id' => $department->id, 'program_id' => $program->id, 'batch_id' => $batch->id, 'status' => 'active']);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        return User::where('email', 'chair@college.com')->firstOrFail();
    }

    public function test_pmc_can_create_and_close_resolution_action_from_constraint(): void
    {
        $chair = $this->seedFixture();
        $constraint = AcademicPmcTimetableConstraint::where('constraint_type', 'student_clash')->firstOrFail();
        AcademicPmcTimetableResolutionAction::where('constraint_id', $constraint->id)->delete();

        $this->actingAs($chair)->post(route('academics.pmc.timetable-constraints.resolution-actions.store', $constraint), [
            'action_type' => 'move_group_slot',
            'title' => 'Move elective slot',
            'description' => 'Shift elective away from core section.',
            'priority' => 'high',
        ])->assertRedirect();

        $action = AcademicPmcTimetableResolutionAction::where('constraint_id', $constraint->id)->firstOrFail();
        $this->assertSame('open', $action->status);

        $this->actingAs($chair)->patch(route('academics.pmc.timetable-resolution-actions.close', $action), [
            'status' => 'resolved',
            'resolution_note' => 'Elective moved to Thursday slot and reviewed by PMC.',
        ])->assertRedirect();

        $this->assertDatabaseHas('academic_pmc_timetable_resolution_actions', [
            'id' => $action->id,
            'status' => 'resolved',
            'resolution_note' => 'Elective moved to Thursday slot and reviewed by PMC.',
        ]);
        $this->assertTrue(AcademicPmcTimetablePublishCheck::where('generation_run_id', $constraint->generation_run_id)->where('check_type', 'resolution_actions')->exists());
    }

    public function test_planner_and_reports_show_resolution_actions(): void
    {
        $chair = $this->seedFixture();

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-planner.index'))
            ->assertOk()
            ->assertSee('Resolution Actions')
            ->assertSee('Move elective group away from core section');

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-reports.index'))
            ->assertOk()
            ->assertSee('Conflict Resolution Actions')
            ->assertSee('Adjunct preferred day reviewed by PMC');
    }
}
