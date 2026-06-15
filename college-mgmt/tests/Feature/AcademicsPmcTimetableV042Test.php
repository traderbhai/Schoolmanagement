<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcElectiveChoice;
use App\Models\AcademicPmcGroupBuildRun;
use App\Models\AcademicPmcStudentCourseAllocation;
use App\Models\AcademicPmcTimetableConstraint;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\AcademicPmcTimetablePublishCheck;
use App\Models\AcademicPmcTimetableSolverAttempt;
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

class AcademicsPmcTimetableV042Test extends TestCase
{
    use RefreshDatabase;

    private function seedFixture(): User
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'PGDM-26', 'name' => 'PGDM 2026', 'status' => 'active']);
        Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'Term 1', 'is_current' => true]);
        Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT401', 'name' => 'Management Analytics', 'credits' => 3, 'is_active' => true]);
        $studentUser = User::factory()->create(['name' => 'PMC v042 Student']);
        Student::factory()->create(['user_id' => $studentUser->id, 'department_id' => $department->id, 'program_id' => $program->id, 'batch_id' => $batch->id, 'status' => 'active']);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        return User::where('email', 'chair@college.com')->firstOrFail();
    }

    public function test_elective_allocation_applies_capacity_and_waitlist(): void
    {
        $chair = $this->seedFixture();
        $program = Program::where('code', 'PGDM')->firstOrFail();
        $batch = Batch::where('program_id', $program->id)->firstOrFail();
        $term = Term::where('program_id', $program->id)->orWhere('batch_id', $batch->id)->firstOrFail();
        $subject = Subject::where('code', 'PMC-ELEC-401')->firstOrFail();
        $secondUser = User::factory()->create(['name' => 'Second Elective Student']);
        $secondStudent = Student::factory()->create(['user_id' => $secondUser->id, 'department_id' => $program->department_id, 'program_id' => $program->id, 'batch_id' => $batch->id, 'status' => 'active']);

        AcademicPmcElectiveChoice::where('subject_id', $subject->id)->update(['status' => 'submitted']);
        AcademicPmcStudentCourseAllocation::where('subject_id', $subject->id)->where('allocation_type', 'elective')->delete();
        AcademicPmcElectiveChoice::updateOrCreate(
            ['student_id' => $secondStudent->id, 'term_id' => $term->id, 'subject_id' => $subject->id],
            ['program_id' => $program->id, 'batch_id' => $batch->id, 'preference_rank' => 1, 'priority_score' => 10, 'status' => 'submitted']
        );

        $this->actingAs($chair)->post(route('academics.pmc.elective-allocation.process'), [
            'title' => 'Capacity Test Elective Allocation',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_ids' => [$subject->id],
            'capacity_per_subject' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('academic_pmc_student_course_allocations', ['subject_id' => $subject->id, 'allocation_type' => 'elective', 'waitlisted' => false]);
        $this->assertDatabaseHas('academic_pmc_student_course_allocations', ['student_id' => $secondStudent->id, 'subject_id' => $subject->id, 'waitlisted' => true]);
    }

    public function test_elective_allocation_supports_multiple_subjects_per_student_with_caps(): void
    {
        $chair = $this->seedFixture();
        $program = Program::where('code', 'PGDM')->firstOrFail();
        $batch = Batch::where('program_id', $program->id)->firstOrFail();
        $term = Term::where('program_id', $program->id)->orWhere('batch_id', $batch->id)->firstOrFail();
        $firstSubject = Subject::where('code', 'PMC-ELEC-401')->firstOrFail();
        $secondSubject = Subject::factory()->create([
            'department_id' => $program->department_id,
            'program_id' => $program->id,
            'code' => 'PMC-ELEC-402',
            'name' => 'Second Elective Subject',
            'credits' => 3,
            'is_active' => true,
        ]);

        $studentUser = User::factory()->create(['name' => 'Multi Elective Student']);
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'department_id' => $program->department_id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'active',
        ]);

        AcademicPmcElectiveChoice::whereIn('subject_id', [$firstSubject->id, $secondSubject->id])->delete();
        AcademicPmcStudentCourseAllocation::whereIn('subject_id', [$firstSubject->id, $secondSubject->id])->where('allocation_type', 'elective')->delete();
        AcademicPmcElectiveChoice::updateOrCreate(
            ['student_id' => $student->id, 'term_id' => $term->id, 'subject_id' => $firstSubject->id],
            ['program_id' => $program->id, 'batch_id' => $batch->id, 'preference_rank' => 1, 'priority_score' => 95, 'status' => 'submitted']
        );
        AcademicPmcElectiveChoice::updateOrCreate(
            ['student_id' => $student->id, 'term_id' => $term->id, 'subject_id' => $secondSubject->id],
            ['program_id' => $program->id, 'batch_id' => $batch->id, 'preference_rank' => 2, 'priority_score' => 90, 'status' => 'submitted']
        );

        $this->actingAs($chair)->post(route('academics.pmc.elective-allocation.process'), [
            'title' => 'Multi Elective Allocation',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_ids' => [$firstSubject->id, $secondSubject->id],
            'capacity_per_subject' => 3,
            'max_electives_per_student' => 2,
        ])->assertRedirect();

        $this->assertDatabaseHas('academic_pmc_student_course_allocations', [
            'student_id' => $student->id,
            'subject_id' => $firstSubject->id,
            'allocation_type' => 'elective',
            'waitlisted' => false,
        ]);
        $this->assertDatabaseHas('academic_pmc_student_course_allocations', [
            'student_id' => $student->id,
            'subject_id' => $secondSubject->id,
            'allocation_type' => 'elective',
            'waitlisted' => false,
        ]);

        AcademicPmcStudentCourseAllocation::whereIn('subject_id', [$firstSubject->id, $secondSubject->id])
            ->where('allocation_type', 'elective')
            ->delete();
        AcademicPmcElectiveChoice::whereIn('subject_id', [$firstSubject->id, $secondSubject->id])
            ->update(['status' => 'submitted']);

        $this->actingAs($chair)->post(route('academics.pmc.elective-allocation.process'), [
            'title' => 'Max Elective Restriction Check',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_ids' => [$firstSubject->id, $secondSubject->id],
            'capacity_per_subject' => 3,
            'max_electives_per_student' => 1,
        ])->assertRedirect();

        $allocations = AcademicPmcStudentCourseAllocation::where('student_id', $student->id)
            ->whereIn('subject_id', [$firstSubject->id, $secondSubject->id])
            ->pluck('waitlisted')
            ->values();
        $this->assertCount(2, $allocations);
        $this->assertEquals(1, $allocations->filter()->count());
        $this->assertEquals(1, $allocations->filter(fn (bool $v) => ! $v)->count());
    }

    public function test_auto_group_builder_and_validation_create_real_world_timetable_controls(): void
    {
        $chair = $this->seedFixture();
        $program = Program::where('code', 'PGDM')->firstOrFail();
        $batch = Batch::where('program_id', $program->id)->firstOrFail();
        $term = Term::where('program_id', $program->id)->orWhere('batch_id', $batch->id)->firstOrFail();
        $subject = Subject::where('code', 'PMC-ELEC-401')->firstOrFail();
        $run = AcademicPmcTimetableGenerationRun::where('title', 'PMC v0.041 Balanced Draft')->firstOrFail();
        AcademicPmcStudentCourseAllocation::where('subject_id', $subject->id)->update(['waitlisted' => false, 'basket_status' => 'allocated']);

        $this->actingAs($chair)->post(route('academics.pmc.course-groups.auto-build'), [
            'title' => 'v042 Auto Build Test',
            'group_prefix' => 'Auto Elective',
            'group_type' => 'elective_group',
            'strategy' => 'balanced_capacity',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'min_capacity' => 1,
            'max_capacity' => 20,
        ])->assertRedirect();

        $this->assertTrue(AcademicPmcGroupBuildRun::where('title', 'v042 Auto Build Test')->exists());
        $this->assertTrue(AcademicPmcCourseGroup::where('name', 'like', 'Auto Elective%')->exists());

        $this->actingAs($chair)->post(route('academics.pmc.timetable-generator.validate', $run))->assertRedirect();

        $this->assertTrue(AcademicPmcTimetableConstraint::where('generation_run_id', $run->id)->where('constraint_type', 'student_clash')->exists());
        $this->assertTrue(AcademicPmcTimetablePublishCheck::where('generation_run_id', $run->id)->where('check_type', 'hard_conflicts')->where('status', 'block')->exists());
        $this->assertTrue(AcademicPmcTimetableSolverAttempt::where('generation_run_id', $run->id)->exists());
    }
}
