<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseAllocationException;
use App\Models\AcademicPmcElectiveChoice;
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

class AcademicsPmcTimetableV087Test extends TestCase
{
    use RefreshDatabase;

    public function test_allocation_pressure_diagnostics_render_on_timetable_and_allocation_pages(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-os.index'))
            ->assertOk()
            ->assertSee('Allocation Pressure Diagnostics')
            ->assertSee('Choice Students Pending')
            ->assertSee('Duplicate Baskets')
            ->assertSee('Single-Course Baskets');

        $this->actingAs($chair)
            ->get(route('academics.pmc.elective-allocation.index'))
            ->assertOk()
            ->assertSee('Allocation Pressure Diagnostics')
            ->assertSee('Add/Drop Pending')
            ->assertSee('Dean Pending');
    }

    public function test_allocation_pressure_diagnostics_count_real_choice_exception_and_basket_blockers(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $department = Department::factory()->create(['code' => 'PMC087', 'name' => 'PMC v087 Department']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PMC087', 'name' => 'PMC v087 Program', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'PMC087-26', 'name' => 'PMC v087 Batch', 'status' => 'active']);
        $term = Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'PMC v087 Term 1', 'is_current' => true]);
        $subject = Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'PMC087-ELEC', 'name' => 'PMC v087 Elective', 'credits' => 3, 'is_active' => true]);
        $student = Student::factory()->create([
            'user_id' => User::factory()->create(['name' => 'PMC v087 Allocation Student'])->id,
            'department_id' => $department->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'active',
        ]);

        AcademicPmcElectiveChoice::create([
            'student_id' => $student->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'preference_rank' => 1,
            'priority_score' => 91,
            'status' => 'submitted',
            'choice_source' => 'student_portal',
        ]);

        AcademicPmcStudentCourseAllocation::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'allocation_type' => 'elective',
            'allocation_source' => 'choice_window',
            'approval_status' => 'waitlisted',
            'basket_status' => 'waitlisted',
            'waitlisted' => true,
            'validation_flags' => ['capacity_full'],
        ]);

        AcademicPmcStudentCourseAllocation::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'allocation_type' => 'elective',
            'allocation_source' => 'manual_override',
            'approval_status' => 'allocated',
            'basket_status' => 'allocated',
            'waitlisted' => false,
            'override_reason' => 'Manual duplicate fixture for allocation pressure.',
            'validation_flags' => [],
        ]);

        AcademicPmcCourseAllocationException::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'exception_type' => 'improvement',
            'status' => 'requested',
            'credit_delta' => 3,
            'requires_dean_approval' => true,
            'reason' => 'Needs Dean review before section locking.',
            'validation_flags' => ['dean_approval_required'],
            'requested_by' => $chair->id,
            'requested_at' => now(),
        ]);

        $this->actingAs($chair)
            ->get(route('academics.pmc.student-course-baskets.index'))
            ->assertOk()
            ->assertSee('Allocation Pressure Diagnostics')
            ->assertSee('Resolve pending elective choices, waitlists, add/drop exceptions, repeat/backlog cases, duplicate baskets, and incomplete student baskets before locking sections.')
            ->assertSee('PMC v087 Allocation Student');
    }
}
