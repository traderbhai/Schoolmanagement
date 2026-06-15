<?php

namespace Tests\Feature;

use App\Models\AcademicPmcFacultyAssignmentAcknowledgement;
use App\Models\AcademicPmcGroupFacultyAssignment;
use App\Models\AcademicPmcTimetablePublishCheck;
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

class AcademicsPmcTimetableV052Test extends TestCase
{
    use RefreshDatabase;

    private function seedFixture(): User
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'PGDM-26', 'name' => 'PGDM 2026', 'status' => 'active']);
        Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'Term 1', 'is_current' => true]);
        Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT401', 'name' => 'Management Analytics', 'credits' => 3, 'is_active' => true]);
        $studentUser = User::factory()->create(['name' => 'PMC v052 Student']);
        Student::factory()->create(['user_id' => $studentUser->id, 'department_id' => $department->id, 'program_id' => $program->id, 'batch_id' => $batch->id, 'status' => 'active']);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        return User::where('email', 'chair@college.com')->firstOrFail();
    }

    public function test_pmc_requests_faculty_assignment_acknowledgement(): void
    {
        $chair = $this->seedFixture();
        $assignment = AcademicPmcGroupFacultyAssignment::firstOrFail();
        AcademicPmcFacultyAssignmentAcknowledgement::where('group_faculty_assignment_id', $assignment->id)->delete();

        $this->actingAs($chair)->post(route('academics.pmc.faculty-assignment-acknowledgements.request', $assignment))->assertRedirect();

        $this->assertDatabaseHas('academic_pmc_faculty_assignment_acknowledgements', [
            'group_faculty_assignment_id' => $assignment->id,
            'teacher_id' => $assignment->teacher_id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('academic_pmc_timetable_publish_checks', [
            'check_type' => 'faculty_acknowledgements',
            'status' => 'warn',
        ]);
    }

    public function test_faculty_responds_with_constraints_and_pmc_reviews_acknowledgement(): void
    {
        $chair = $this->seedFixture();
        $ack = AcademicPmcFacultyAssignmentAcknowledgement::where('status', 'accepted')->firstOrFail();
        $faculty = $ack->teacher->user;

        $this->actingAs($faculty)->patch(route('academics.pmc.faculty-assignment-acknowledgements.respond', $ack), [
            'response_type' => 'accept_with_constraints',
            'faculty_note' => 'Can take only morning slots.',
            'constraints_raised' => 'morning_only,max_two_classes',
        ])->assertRedirect();

        $this->assertDatabaseHas('academic_pmc_faculty_assignment_acknowledgements', [
            'id' => $ack->id,
            'status' => 'concern_raised',
            'response_type' => 'accept_with_constraints',
        ]);

        $this->actingAs($chair)->patch(route('academics.pmc.faculty-assignment-acknowledgements.review', $ack), [
            'status' => 'accepted',
            'review_note' => 'PMC adjusted timetable preferences.',
        ])->assertRedirect();

        $this->assertDatabaseHas('academic_pmc_faculty_assignment_acknowledgements', [
            'id' => $ack->id,
            'status' => 'accepted',
            'review_note' => 'PMC adjusted timetable preferences.',
        ]);
    }

    public function test_non_assigned_faculty_cannot_respond_to_acknowledgement(): void
    {
        $this->seedFixture();
        $ack = AcademicPmcFacultyAssignmentAcknowledgement::firstOrFail();
        $otherFaculty = User::where('email', 'pmc.adjunct@college.com')->firstOrFail();
        if ($otherFaculty->id === $ack->teacher->user_id) {
            $otherFaculty = User::where('email', 'pmc.faculty@college.com')->firstOrFail();
        }

        $this->actingAs($otherFaculty)->patch(route('academics.pmc.faculty-assignment-acknowledgements.respond', $ack), [
            'response_type' => 'accept',
            'faculty_note' => 'Wrong faculty tries to accept.',
        ])->assertForbidden();
    }

    public function test_faculty_allocation_page_shows_acknowledgement_workflow(): void
    {
        $chair = $this->seedFixture();

        $this->actingAs($chair)
            ->get(route('academics.pmc.section-faculty-allocation.index'))
            ->assertOk()
            ->assertSee('Faculty Assignment Acknowledgements')
            ->assertSee('Request')
            ->assertSee('adjunct_day_limit');
    }
}
