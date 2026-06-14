<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\AcademicProgramLeadershipService;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcademicsProgramLeadershipV005Test extends TestCase
{
    use RefreshDatabase;

    private function seedProgramFixture(): array
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        $subject = Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT101', 'name' => 'Management Foundations', 'is_active' => true]);
        $studentUser = User::factory()->create(['name' => 'Kabir Malhotra']);
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'department_id' => $department->id,
            'program_id' => $program->id,
            'status' => 'active',
        ]);
        Semester::factory()->create(['number' => 1, 'is_current' => true]);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        return compact('department', 'program', 'subject', 'student');
    }

    public function test_program_director_can_open_program_leadership_dashboard(): void
    {
        $this->seedProgramFixture();
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($chair)
            ->get(route('academics.program-leadership.index'))
            ->assertOk()
            ->assertSee('Program Leadership OS')
            ->assertSee('Program Portfolio')
            ->assertSee('Course Delivery')
            ->assertSee('Student Success')
            ->assertSee('Quality Signals')
            ->assertSee('PGDM');
    }

    public function test_program_leadership_source_lists_are_database_backed_and_linked(): void
    {
        $this->seedProgramFixture();
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($chair)
            ->get(route('academics.program-leadership.student-success'))
            ->assertOk()
            ->assertSee('Student Success')
            ->assertSee('Filtered Source List')
            ->assertSee('Kabir Malhotra')
            ->assertSee(route('chair.students.at-risk'), false);

        $this->actingAs($chair)
            ->get(route('academics.program-leadership.course-delivery'))
            ->assertOk()
            ->assertSee('Course Delivery')
            ->assertSee('Industry Immersion Lab');
    }

    public function test_program_leadership_reports_page_lists_operational_reports(): void
    {
        $this->seedProgramFixture();
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($chair)
            ->get(route('academics.program-leadership.reports'))
            ->assertOk()
            ->assertSee('Program Leadership Reports')
            ->assertSee('Program portfolio')
            ->assertSee('Course delivery')
            ->assertSee('Student success');
    }

    public function test_program_leadership_service_respects_program_scope(): void
    {
        $this->seedProgramFixture();
        $otherProgram = Program::factory()->create(['code' => 'BBA-HIDDEN', 'is_active' => true]);
        Subject::factory()->create(['program_id' => $otherProgram->id, 'name' => 'Hidden Program Leadership Subject', 'is_active' => true]);

        $leader = User::where('email', 'hod@college.com')->firstOrFail();
        $data = app(AcademicProgramLeadershipService::class)->courseDelivery($leader);
        $titles = collect($data['items'])->pluck('title');

        $this->assertFalse($titles->contains('Hidden Program Leadership Subject'));
    }

    public function test_non_academic_user_cannot_access_program_leadership_os(): void
    {
        $this->seedProgramFixture();
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('student');

        $this->actingAs($user)
            ->get(route('academics.program-leadership.index'))
            ->assertForbidden();
    }
}
