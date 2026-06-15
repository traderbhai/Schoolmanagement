<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicScopeAssignment;
use App\Models\Batch;
use App\Models\Department;
use App\Models\Program;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcTimetableV049Test extends TestCase
{
    use RefreshDatabase;

    private function seedFixture(): array
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'PGDM-26', 'name' => 'PGDM 2026', 'status' => 'active']);
        $term = Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'Term 1', 'is_current' => true]);
        $subject = Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT401', 'name' => 'Management Analytics', 'credits' => 3, 'is_active' => true]);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        return compact('department', 'program', 'batch', 'term', 'subject');
    }

    public function test_pmc_manager_can_create_timetable_group_inside_assigned_scope_only(): void
    {
        $this->seedFixture();
        $manager = User::where('email', 'pmc.manager@college.com')->firstOrFail();
        $assignedBatchId = AcademicScopeAssignment::where('user_id', $manager->id)
            ->where('scope_type', 'batch')
            ->where('can_manage', true)
            ->value('scope_id');
        $assignedBatch = Batch::findOrFail($assignedBatchId);
        $assignedProgram = Program::findOrFail($assignedBatch->program_id);
        $assignedTerm = Term::where('batch_id', $assignedBatch->id)->first() ?: Term::where('program_id', $assignedProgram->id)->first();
        $assignedSubject = Subject::where('program_id', $assignedProgram->id)->firstOrFail();

        $this->actingAs($manager)->post(route('academics.pmc.course-groups.store'), [
            'name' => 'Manager Scoped Section',
            'group_type' => 'core_section',
            'program_id' => $assignedProgram->id,
            'batch_id' => $assignedBatch->id,
            'term_id' => $assignedTerm?->id,
            'subject_id' => $assignedSubject->id,
            'min_capacity' => 10,
            'max_capacity' => 60,
            'current_strength' => 30,
        ])->assertRedirect();

        $this->assertTrue(AcademicPmcCourseGroup::where('name', 'Manager Scoped Section')->exists());

        $otherDepartment = Department::factory()->create(['code' => 'SCI', 'name' => 'Science']);
        $otherProgram = Program::factory()->create(['department_id' => $otherDepartment->id, 'code' => 'BSC', 'name' => 'BSc', 'is_active' => true]);
        $otherBatch = Batch::factory()->create(['program_id' => $otherProgram->id, 'code' => 'BSC-26', 'name' => 'BSc 2026', 'status' => 'active']);
        $otherTerm = Term::factory()->create(['program_id' => $otherProgram->id, 'batch_id' => $otherBatch->id, 'term_number' => 1, 'name' => 'Term 1']);
        $otherSubject = Subject::factory()->create(['department_id' => $otherDepartment->id, 'program_id' => $otherProgram->id, 'code' => 'SCI101', 'name' => 'Physics', 'is_active' => true]);

        $this->actingAs($manager)->post(route('academics.pmc.course-groups.store'), [
            'name' => 'Out Of Scope Section',
            'group_type' => 'core_section',
            'program_id' => $otherProgram->id,
            'batch_id' => $otherBatch->id,
            'term_id' => $otherTerm->id,
            'subject_id' => $otherSubject->id,
            'min_capacity' => 10,
            'max_capacity' => 60,
            'current_strength' => 30,
        ])->assertForbidden();

        $this->assertFalse(AcademicPmcCourseGroup::where('name', 'Out Of Scope Section')->exists());
    }

    public function test_dean_can_override_pmc_scope_for_timetable_setup(): void
    {
        $fixture = $this->seedFixture();
        $dean = User::where('email', 'dean@college.com')->firstOrFail();

        $this->actingAs($dean)->post(route('academics.pmc.course-groups.store'), [
            'name' => 'Dean Override Section',
            'group_type' => 'core_section',
            'program_id' => $fixture['program']->id,
            'batch_id' => $fixture['batch']->id,
            'term_id' => $fixture['term']->id,
            'subject_id' => $fixture['subject']->id,
            'min_capacity' => 10,
            'max_capacity' => 60,
            'current_strength' => 30,
        ])->assertRedirect();

        $this->assertTrue(AcademicPmcCourseGroup::where('name', 'Dean Override Section')->exists());
    }
}
