<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseAllocationException;
use App\Models\AcademicPmcStudentCourseAllocation;
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

class AcademicsPmcTimetableV050Test extends TestCase
{
    use RefreshDatabase;

    private function seedFixture(): array
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'PGDM-26', 'name' => 'PGDM 2026', 'status' => 'active']);
        $term = Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'Term 1', 'is_current' => true]);
        $subject = Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT401', 'name' => 'Management Analytics', 'credits' => 3, 'is_active' => true]);
        $studentUser = User::factory()->create(['name' => 'PMC v050 Student']);
        $student = Student::factory()->create(['user_id' => $studentUser->id, 'department_id' => $department->id, 'program_id' => $program->id, 'batch_id' => $batch->id, 'status' => 'active']);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        return compact('department', 'program', 'batch', 'term', 'subject', 'student');
    }

    public function test_pmc_requests_and_approves_add_course_exception_into_student_basket(): void
    {
        $fixture = $this->seedFixture();
        $chair = User::where('email', 'chair@college.com')->firstOrFail();
        $newSubject = Subject::factory()->create(['department_id' => $fixture['department']->id, 'program_id' => $fixture['program']->id, 'code' => 'MGT450', 'name' => 'Business Simulation', 'credits' => 3, 'is_active' => true]);

        $this->actingAs($chair)->post(route('academics.pmc.course-allocation-exceptions.store'), [
            'student_id' => $fixture['student']->id,
            'subject_id' => $newSubject->id,
            'term_id' => $fixture['term']->id,
            'exception_type' => 'add',
            'credit_delta' => 3,
            'reason' => 'Late approved add during add/drop window.',
        ])->assertRedirect();

        $exception = AcademicPmcCourseAllocationException::where('subject_id', $newSubject->id)->firstOrFail();
        $this->actingAs($chair)->patch(route('academics.pmc.course-allocation-exceptions.decide', $exception), [
            'status' => 'approved',
            'decision_note' => 'Approved within student credit load.',
        ])->assertRedirect();

        $this->assertDatabaseHas('academic_pmc_student_course_allocations', [
            'student_id' => $fixture['student']->id,
            'subject_id' => $newSubject->id,
            'allocation_source' => 'exception_add',
            'basket_status' => 'approved',
        ]);
    }

    public function test_drop_exception_marks_existing_allocation_dropped(): void
    {
        $fixture = $this->seedFixture();
        $chair = User::where('email', 'chair@college.com')->firstOrFail();
        AcademicPmcStudentCourseAllocation::updateOrCreate(
            ['student_id' => $fixture['student']->id, 'subject_id' => $fixture['subject']->id, 'term_id' => $fixture['term']->id],
            ['allocation_type' => 'core', 'allocation_source' => 'bulk_core', 'approval_status' => 'allocated', 'basket_status' => 'approved']
        );

        $this->actingAs($chair)->post(route('academics.pmc.course-allocation-exceptions.store'), [
            'student_id' => $fixture['student']->id,
            'subject_id' => $fixture['subject']->id,
            'term_id' => $fixture['term']->id,
            'exception_type' => 'drop',
            'credit_delta' => 3,
            'reason' => 'Approved medical withdrawal from subject.',
        ])->assertRedirect();

        $exception = AcademicPmcCourseAllocationException::where('exception_type', 'drop')->where('student_id', $fixture['student']->id)->firstOrFail();
        $this->actingAs($chair)->patch(route('academics.pmc.course-allocation-exceptions.decide', $exception), [
            'status' => 'approved',
            'decision_note' => 'Drop approved with replacement plan.',
        ])->assertRedirect();

        $this->assertDatabaseHas('academic_pmc_student_course_allocations', [
            'student_id' => $fixture['student']->id,
            'subject_id' => $fixture['subject']->id,
            'basket_status' => 'dropped',
        ]);
    }

    public function test_dean_required_exception_cannot_be_approved_by_pmc_manager(): void
    {
        $fixture = $this->seedFixture();
        $chair = User::where('email', 'chair@college.com')->firstOrFail();
        $manager = User::where('email', 'pmc.manager@college.com')->firstOrFail();
        $dean = User::where('email', 'dean@college.com')->firstOrFail();

        $this->actingAs($chair)->post(route('academics.pmc.course-allocation-exceptions.store'), [
            'student_id' => $fixture['student']->id,
            'subject_id' => $fixture['subject']->id,
            'term_id' => $fixture['term']->id,
            'exception_type' => 'improvement',
            'credit_delta' => 3,
            'reason' => 'Improvement attempt needs Dean approval.',
        ])->assertRedirect();

        $exception = AcademicPmcCourseAllocationException::where('exception_type', 'improvement')->where('student_id', $fixture['student']->id)->firstOrFail();
        $this->actingAs($manager)->patch(route('academics.pmc.course-allocation-exceptions.decide', $exception), [
            'status' => 'approved',
            'decision_note' => 'Manager tries to approve.',
        ])->assertForbidden();

        $this->actingAs($dean)->patch(route('academics.pmc.course-allocation-exceptions.decide', $exception), [
            'status' => 'approved',
            'decision_note' => 'Dean approved improvement attempt.',
        ])->assertRedirect();

        $this->assertDatabaseHas('academic_pmc_course_allocation_exceptions', [
            'id' => $exception->id,
            'status' => 'approved',
            'decision_note' => 'Dean approved improvement attempt.',
        ]);
    }

    public function test_course_allocation_page_shows_exception_workflow(): void
    {
        $this->seedFixture();
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($chair)
            ->get(route('academics.pmc.course-allocation.index'))
            ->assertOk()
            ->assertSee('Course Basket Exception')
            ->assertSee('Course Basket Exceptions')
            ->assertSee('mandatory_core_drop');
    }
}
