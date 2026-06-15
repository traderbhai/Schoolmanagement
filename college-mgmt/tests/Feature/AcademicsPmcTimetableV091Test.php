<?php

namespace Tests\Feature;

use App\Models\AcademicPmcElectiveChoice;
use App\Models\AcademicPmcStudentCourseAllocation;
use App\Models\Batch;
use App\Models\Department;
use App\Models\ElectiveRegistrationWindow;
use App\Models\Program;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcademicsPmcTimetableV091Test extends TestCase
{
    use RefreshDatabase;

    private function seedFixture(): array
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'PGDM-26', 'name' => 'PGDM 2026', 'status' => 'active']);
        $term = Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'Term 1', 'is_current' => true]);
        Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT401', 'name' => 'Management Analytics', 'credits' => 3, 'type' => 'theory', 'is_active' => true]);
        Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'ELEC501', 'name' => 'Consumer Analytics Elective', 'credits' => 3, 'type' => 'elective', 'is_active' => true]);
        Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'ELEC502', 'name' => 'Fintech Strategy Elective', 'credits' => 3, 'type' => 'elective', 'is_active' => true]);
        $studentUser = User::factory()->create(['name' => 'PMC v091 Student', 'email' => 'pmc.v091.student@example.test']);
        Student::factory()->create(['user_id' => $studentUser->id, 'department_id' => $department->id, 'program_id' => $program->id, 'batch_id' => $batch->id, 'current_term_id' => $term->id, 'status' => 'active']);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $student = Student::whereHas('user', fn ($q) => $q->where('email', 'pmc.v091.student@example.test'))->firstOrFail();
        $student->user->assignRole('student');

        ElectiveRegistrationWindow::updateOrCreate(
            ['program_id' => $student->program_id, 'term_id' => $student->current_term_id, 'elective_group' => null],
            ['opens_at' => now()->subDay(), 'closes_at' => now()->addDays(5), 'max_selections' => 2, 'status' => 'open', 'instructions' => 'Test choice window.', 'created_by' => User::where('email', 'chair@college.com')->value('id') ?? $student->user_id]
        );

        return [
            'student' => $student,
            'studentUser' => $student->user,
            'chair' => User::where('email', 'chair@college.com')->firstOrFail(),
            'subjects' => Subject::where('program_id', $student->program_id)->where('type', 'elective')->orderBy('code')->get(),
        ];
    }

    public function test_student_can_view_elective_choice_portal(): void
    {
        $fixture = $this->seedFixture();

        $this->actingAs($fixture['studentUser'])
            ->get(route('student.pmc-elective-choices'))
            ->assertOk()
            ->assertSee('My Elective Choices')
            ->assertSee('Choice Window')
            ->assertSee('Submit Ranked Choices')
            ->assertSee('My Choice Outcomes');
    }

    public function test_student_submits_ranked_elective_choices(): void
    {
        $fixture = $this->seedFixture();
        $subjects = $fixture['subjects']->take(2)->values();

        $this->actingAs($fixture['studentUser'])
            ->post(route('student.pmc-elective-choices.store'), [
                'term_id' => $fixture['student']->current_term_id,
                'subject_ids' => [$subjects[0]->id, $subjects[1]->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('academic_pmc_elective_choices', [
            'student_id' => $fixture['student']->id,
            'subject_id' => $subjects[0]->id,
            'preference_rank' => 1,
            'status' => 'submitted',
            'choice_source' => 'student_self_service',
        ]);
        $this->assertDatabaseHas('academic_pmc_elective_choices', [
            'student_id' => $fixture['student']->id,
            'subject_id' => $subjects[1]->id,
            'preference_rank' => 2,
            'status' => 'submitted',
        ]);
    }

    public function test_duplicate_elective_preferences_are_rejected(): void
    {
        $fixture = $this->seedFixture();
        $subject = $fixture['subjects']->firstOrFail();

        $this->actingAs($fixture['studentUser'])
            ->post(route('student.pmc-elective-choices.store'), [
                'term_id' => $fixture['student']->current_term_id,
                'subject_ids' => [$subject->id, $subject->id],
            ])
            ->assertStatus(422);
    }

    public function test_pmc_allocation_consumes_student_submitted_choices(): void
    {
        $fixture = $this->seedFixture();
        $subject = $fixture['subjects']->firstOrFail();

        AcademicPmcElectiveChoice::updateOrCreate(
            ['student_id' => $fixture['student']->id, 'term_id' => $fixture['student']->current_term_id, 'subject_id' => $subject->id],
            ['program_id' => $fixture['student']->program_id, 'batch_id' => $fixture['student']->batch_id, 'preference_rank' => 1, 'priority_score' => 100, 'status' => 'submitted', 'choice_source' => 'student_self_service']
        );

        $this->actingAs($fixture['chair'])
            ->post(route('academics.pmc.elective-allocation.process'), [
                'title' => 'v091 Choice Allocation',
                'program_id' => $fixture['student']->program_id,
                'batch_id' => $fixture['student']->batch_id,
                'term_id' => $fixture['student']->current_term_id,
                'subject_ids' => [$subject->id],
                'capacity_per_subject' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('academic_pmc_student_course_allocations', [
            'student_id' => $fixture['student']->id,
            'subject_id' => $subject->id,
            'allocation_source' => 'choice_window',
            'waitlisted' => false,
        ]);
    }
}
