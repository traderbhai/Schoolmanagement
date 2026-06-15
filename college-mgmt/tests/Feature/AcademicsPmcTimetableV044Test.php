<?php

namespace Tests\Feature;

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

class AcademicsPmcTimetableV044Test extends TestCase
{
    use RefreshDatabase;

    private function seedFixture(): array
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'PGDM-26', 'name' => 'PGDM 2026', 'status' => 'active']);
        Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'Term 1', 'is_current' => true]);
        Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT401', 'name' => 'Management Analytics', 'credits' => 3, 'is_active' => true]);
        $studentUser = User::factory()->create(['name' => 'PMC v044 Student', 'email' => 'pmc.v044.student@example.test']);
        Student::factory()->create(['user_id' => $studentUser->id, 'department_id' => $department->id, 'program_id' => $program->id, 'batch_id' => $batch->id, 'status' => 'active']);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $seededStudent = Student::firstOrFail();
        $seededStudent->user->assignRole('student');

        return [
            'student' => $seededStudent->user,
            'faculty' => User::where('email', 'pmc.faculty@college.com')->firstOrFail(),
            'chair' => User::where('email', 'chair@college.com')->firstOrFail(),
        ];
    }

    public function test_student_sees_only_group_membership_timetable(): void
    {
        $fixture = $this->seedFixture();

        $this->actingAs($fixture['student'])
            ->get(route('student.pmc-timetable'))
            ->assertOk()
            ->assertSee('My PMC Group Timetable')
            ->assertSee('PGDM Core Section A')
            ->assertSee('Growth Analytics Elective Group 1');

        $this->actingAs($fixture['student'])
            ->get(route('academics.pmc.official-timetable.index'))
            ->assertForbidden();
    }

    public function test_faculty_and_pmc_have_scoped_official_timetable_views(): void
    {
        $fixture = $this->seedFixture();
        $fixture['faculty']->assignRole('teacher');

        $this->actingAs($fixture['faculty'])
            ->get(route('teacher.pmc-timetable.index'))
            ->assertOk()
            ->assertSee('My PMC Teaching Timetable')
            ->assertSee('PGDM Core Section A');

        $this->actingAs($fixture['chair'])
            ->get(route('academics.pmc.official-timetable.index'))
            ->assertOk()
            ->assertSee('PMC Official Audience Timetable')
            ->assertSee('Decision Analytics Lab Group L1');
    }
}
