<?php

namespace Tests\Feature;

use App\Models\AcademicPmcFacultyAvailabilityRequest;
use App\Models\AcademicPmcFacultyPreference;
use App\Models\Batch;
use App\Models\Department;
use App\Models\Program;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcTimetableV046Test extends TestCase
{
    use RefreshDatabase;

    private function seedFixture(): array
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'PGDM-26', 'name' => 'PGDM 2026', 'status' => 'active']);
        Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'Term 1', 'is_current' => true]);
        Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT401', 'name' => 'Management Analytics', 'credits' => 3, 'is_active' => true]);
        $studentUser = User::factory()->create(['name' => 'PMC v046 Student']);
        Student::factory()->create(['user_id' => $studentUser->id, 'department_id' => $department->id, 'program_id' => $program->id, 'batch_id' => $batch->id, 'status' => 'active']);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        return [
            'chair' => User::where('email', 'chair@college.com')->firstOrFail(),
            'faculty' => User::where('email', 'pmc.faculty@college.com')->firstOrFail(),
            'term' => Term::firstOrFail(),
        ];
    }

    public function test_faculty_submits_availability_and_pmc_approves_into_preferences(): void
    {
        $fixture = $this->seedFixture();
        $faculty = $fixture['faculty'];
        $faculty->assignRole('teacher');
        $teacher = Teacher::where('user_id', $faculty->id)->firstOrFail();

        $this->actingAs($faculty)->post(route('teacher.pmc-availability.store'), [
            'term_id' => $fixture['term']->id,
            'available_days' => '1,3,5',
            'preferred_slots' => '1,2',
            'unavailable_slots' => '2:1',
            'max_classes_per_day' => 3,
            'max_consecutive_classes' => 2,
            'max_weekly_load' => 12,
            'reason' => 'Research block on Tuesday mornings.',
        ])->assertRedirect();

        $request = AcademicPmcFacultyAvailabilityRequest::where('teacher_id', $teacher->id)->where('reason', 'Research block on Tuesday mornings.')->firstOrFail();
        $this->assertSame('submitted', $request->status);

        $this->actingAs($fixture['chair'])->patch(route('academics.pmc.faculty-availability-requests.decide', $request), [
            'status' => 'approved',
            'decision_note' => 'Accepted by PMC for timetable generation.',
        ])->assertRedirect();

        $this->assertDatabaseHas('academic_pmc_faculty_availability_requests', ['id' => $request->id, 'status' => 'approved']);
        $preference = AcademicPmcFacultyPreference::where('teacher_id', $teacher->id)->where('term_id', $fixture['term']->id)->firstOrFail();
        $this->assertSame([1, 3, 5], $preference->available_days);
        $this->assertSame(3, $preference->max_classes_per_day);
    }

    public function test_pmc_and_faculty_availability_pages_render_seeded_data(): void
    {
        $fixture = $this->seedFixture();
        $fixture['faculty']->assignRole('teacher');

        $this->actingAs($fixture['chair'])
            ->get(route('academics.pmc.faculty-availability-requests.index'))
            ->assertOk()
            ->assertSee('PMC Faculty Availability Requests')
            ->assertSee('Visiting faculty available only Tuesday and Thursday');

        $this->actingAs($fixture['faculty'])
            ->get(route('teacher.pmc-availability.index'))
            ->assertOk()
            ->assertSee('My PMC Availability')
            ->assertSee('Prefers morning analytics lab slots');
    }
}
