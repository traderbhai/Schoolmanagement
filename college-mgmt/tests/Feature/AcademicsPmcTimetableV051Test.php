<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcCourseGroupAdjustment;
use App\Models\AcademicPmcCourseGroupMember;
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

class AcademicsPmcTimetableV051Test extends TestCase
{
    use RefreshDatabase;

    private function seedFixture(): User
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'PGDM-26', 'name' => 'PGDM 2026', 'status' => 'active']);
        Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'Term 1', 'is_current' => true]);
        Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT401', 'name' => 'Management Analytics', 'credits' => 3, 'is_active' => true]);
        $studentUser = User::factory()->create(['name' => 'PMC v051 Student']);
        Student::factory()->create(['user_id' => $studentUser->id, 'department_id' => $department->id, 'program_id' => $program->id, 'batch_id' => $batch->id, 'status' => 'active']);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        return User::where('email', 'chair@college.com')->firstOrFail();
    }

    public function test_pmc_can_request_and_approve_rebalance_between_groups(): void
    {
        $chair = $this->seedFixture();
        $source = AcademicPmcCourseGroup::where('name', 'PGDM Core Section A')->firstOrFail();
        $target = AcademicPmcCourseGroup::where('name', 'Growth Analytics Elective Group 1')->firstOrFail();

        $this->actingAs($chair)->post(route('academics.pmc.course-group-adjustments.store'), [
            'course_group_id' => $source->id,
            'target_course_group_id' => $target->id,
            'adjustment_type' => 'rebalance',
            'strength_delta' => 1,
            'reason' => 'Balance students after add/drop window.',
        ])->assertRedirect();

        $adjustment = AcademicPmcCourseGroupAdjustment::where('reason', 'Balance students after add/drop window.')->firstOrFail();
        $this->actingAs($chair)->patch(route('academics.pmc.course-group-adjustments.decide', $adjustment), [
            'status' => 'approved',
            'decision_note' => 'Approved by PMC after capacity review.',
        ])->assertRedirect();

        $this->assertDatabaseHas('academic_pmc_course_groups', ['id' => $source->id, 'current_strength' => max(0, $source->current_strength - 1)]);
        $this->assertDatabaseHas('academic_pmc_course_groups', ['id' => $target->id, 'current_strength' => $target->current_strength + 1]);
    }

    public function test_move_student_adjustment_moves_membership_to_target_group(): void
    {
        $chair = $this->seedFixture();
        $source = AcademicPmcCourseGroup::where('name', 'PGDM Core Section A')->firstOrFail();
        $target = AcademicPmcCourseGroup::where('name', 'Growth Analytics Elective Group 1')->firstOrFail();
        $member = AcademicPmcCourseGroupMember::where('course_group_id', $source->id)->firstOrFail();

        $this->actingAs($chair)->post(route('academics.pmc.course-group-adjustments.store'), [
            'course_group_id' => $source->id,
            'target_course_group_id' => $target->id,
            'student_id' => $member->student_id,
            'adjustment_type' => 'move_student',
            'strength_delta' => 1,
            'reason' => 'Manual student move due elective conflict.',
        ])->assertRedirect();

        $adjustment = AcademicPmcCourseGroupAdjustment::where('reason', 'Manual student move due elective conflict.')->firstOrFail();
        $this->actingAs($chair)->patch(route('academics.pmc.course-group-adjustments.decide', $adjustment), [
            'status' => 'approved',
            'decision_note' => 'Moved with student consent.',
        ])->assertRedirect();

        $this->assertDatabaseHas('academic_pmc_course_group_members', [
            'student_id' => $member->student_id,
            'course_group_id' => $target->id,
            'move_reason' => 'Moved with student consent.',
        ]);
    }

    public function test_dean_required_group_adjustment_blocks_pmc_manager_approval(): void
    {
        $chair = $this->seedFixture();
        $manager = User::where('email', 'pmc.manager@college.com')->firstOrFail();
        $dean = User::where('email', 'dean@college.com')->firstOrFail();
        $source = AcademicPmcCourseGroup::where('name', 'PGDM Core Section A')->firstOrFail();
        $target = AcademicPmcCourseGroup::where('name', 'Growth Analytics Elective Group 1')->firstOrFail();

        $this->actingAs($chair)->post(route('academics.pmc.course-group-adjustments.store'), [
            'course_group_id' => $source->id,
            'target_course_group_id' => $target->id,
            'adjustment_type' => 'merge',
            'strength_delta' => 1,
            'reason' => 'Merge under-strength sections.',
        ])->assertRedirect();

        $adjustment = AcademicPmcCourseGroupAdjustment::where('adjustment_type', 'merge')->where('reason', 'Merge under-strength sections.')->firstOrFail();
        $this->actingAs($manager)->patch(route('academics.pmc.course-group-adjustments.decide', $adjustment), [
            'status' => 'approved',
            'decision_note' => 'Manager approval attempt.',
        ])->assertForbidden();

        $this->actingAs($dean)->patch(route('academics.pmc.course-group-adjustments.decide', $adjustment), [
            'status' => 'approved',
            'decision_note' => 'Dean approved section merge.',
        ])->assertRedirect();

        $this->assertDatabaseHas('academic_pmc_course_group_adjustments', [
            'id' => $adjustment->id,
            'status' => 'approved',
            'decision_note' => 'Dean approved section merge.',
        ]);
    }

    public function test_group_builder_page_shows_adjustment_workflow(): void
    {
        $chair = $this->seedFixture();

        $this->actingAs($chair)
            ->get(route('academics.pmc.course-groups.index'))
            ->assertOk()
            ->assertSee('Group Adjustment')
            ->assertSee('Section And Group Adjustments')
            ->assertSee('rebalance');
    }
}
