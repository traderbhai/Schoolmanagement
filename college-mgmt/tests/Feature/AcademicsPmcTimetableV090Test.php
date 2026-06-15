<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseAllocationException;
use App\Models\AcademicPmcStudentBasketAcknowledgement;
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
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcademicsPmcTimetableV090Test extends TestCase
{
    use RefreshDatabase;

    private function seedFixture(): array
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'PGDM-26', 'name' => 'PGDM 2026', 'status' => 'active']);
        Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'Term 1', 'is_current' => true]);
        Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT401', 'name' => 'Management Analytics', 'credits' => 3, 'is_active' => true]);
        $studentUser = User::factory()->create(['name' => 'PMC v090 Student', 'email' => 'pmc.v090.student@example.test']);
        Student::factory()->create(['user_id' => $studentUser->id, 'department_id' => $department->id, 'program_id' => $program->id, 'batch_id' => $batch->id, 'status' => 'active']);
        $otherUser = User::factory()->create(['name' => 'PMC v090 Other Student', 'email' => 'pmc.v090.other@example.test']);
        Student::factory()->create(['user_id' => $otherUser->id, 'department_id' => $department->id, 'program_id' => $program->id, 'batch_id' => $batch->id, 'status' => 'active']);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $student = Student::whereHas('subjectEnrollments')->firstOrFail();
        $student->user->assignRole('student');

        return [
            'student' => $student,
            'studentUser' => $student->user,
            'chair' => User::where('email', 'chair@college.com')->firstOrFail(),
        ];
    }

    public function test_student_can_view_course_basket_self_service(): void
    {
        $fixture = $this->seedFixture();

        $this->actingAs($fixture['studentUser'])
            ->get(route('student.pmc-course-basket'))
            ->assertOk()
            ->assertSee('My Course Basket')
            ->assertSee('Allocated Courses')
            ->assertSee('Timetable Preview')
            ->assertSee('Acknowledgements And Requests')
            ->assertSee('Growth Analytics Elective Group 1');
    }

    public function test_student_can_submit_objection_for_own_allocation(): void
    {
        $fixture = $this->seedFixture();
        $allocation = AcademicPmcStudentCourseAllocation::where('student_id', $fixture['student']->id)->firstOrFail();

        $this->actingAs($fixture['studentUser'])
            ->post(route('student.pmc-course-basket.acknowledge'), [
                'student_course_allocation_id' => $allocation->id,
                'acknowledgement_type' => 'objection',
                'reason' => 'Faculty slot conflict',
                'student_note' => 'I need PMC to review this allocation before timetable freeze.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('academic_pmc_student_basket_acknowledgements', [
            'student_id' => $fixture['student']->id,
            'student_course_allocation_id' => $allocation->id,
            'acknowledgement_type' => 'objection',
            'status' => 'objection_submitted',
        ]);

        $this->assertDatabaseHas('academic_pmc_course_allocation_exceptions', [
            'student_id' => $fixture['student']->id,
            'student_course_allocation_id' => $allocation->id,
            'exception_type' => 'student_objection',
            'status' => 'pending',
        ]);
    }

    public function test_student_cannot_submit_against_another_student_allocation(): void
    {
        $fixture = $this->seedFixture();
        $otherStudent = Student::where('id', '!=', $fixture['student']->id)->firstOrFail();
        $sourceAllocation = AcademicPmcStudentCourseAllocation::where('student_id', $fixture['student']->id)->firstOrFail();
        $otherAllocation = AcademicPmcStudentCourseAllocation::firstOrCreate(
            ['student_id' => $otherStudent->id, 'subject_id' => $sourceAllocation->subject_id, 'term_id' => $sourceAllocation->term_id],
            ['allocation_type' => 'core', 'allocation_source' => 'test_fixture', 'approval_status' => 'allocated', 'basket_status' => 'approved']
        );

        $this->actingAs($fixture['studentUser'])
            ->post(route('student.pmc-course-basket.acknowledge'), [
                'student_course_allocation_id' => $otherAllocation->id,
                'acknowledgement_type' => 'add_drop_request',
                'reason' => 'Trying unrelated record',
                'student_note' => 'This should not be accepted.',
            ])
            ->assertNotFound();
    }

    public function test_pmc_can_review_student_course_basket_request(): void
    {
        $fixture = $this->seedFixture();
        $ack = AcademicPmcStudentBasketAcknowledgement::where('student_id', $fixture['student']->id)
            ->whereIn('status', ['objection_submitted', 'under_review'])
            ->firstOrFail();

        $this->actingAs($fixture['chair'])
            ->patch(route('academics.pmc.student-course-basket-acknowledgements.review', $ack), [
                'status' => 'resolved',
                'pmc_note' => 'Reviewed with course basket and timetable impact.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('academic_pmc_student_basket_acknowledgements', [
            'id' => $ack->id,
            'status' => 'resolved',
            'pmc_note' => 'Reviewed with course basket and timetable impact.',
        ]);
    }
}
