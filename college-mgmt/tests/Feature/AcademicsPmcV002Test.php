<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\AcademicPmcOperatingService;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcademicsPmcV002Test extends TestCase
{
    use RefreshDatabase;

    private function seedPmcFixture(): array
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        $subject = Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT101', 'name' => 'Management Foundations', 'is_active' => true]);
        $semester = Semester::factory()->create(['number' => 1, 'is_current' => true]);
        $studentUser = User::factory()->create(['name' => 'Aarav Sharma']);
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'department_id' => $department->id,
            'program_id' => $program->id,
            'status' => 'active',
        ]);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        return compact('department', 'program', 'subject', 'semester', 'student');
    }

    public function test_program_chair_can_open_pmc_operating_dashboard_with_real_operational_sections(): void
    {
        $this->seedPmcFixture();
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($chair)
            ->get(route('academics.pmc.index'))
            ->assertOk()
            ->assertSee('PMC Operating System')
            ->assertSee('Curriculum Readiness')
            ->assertSee('Faculty Allocation')
            ->assertSee('Timetable Readiness')
            ->assertSee('Student Monitoring')
            ->assertSee('Industry Immersion Lab');
    }

    public function test_pmc_source_lists_are_database_backed_and_link_to_existing_modules(): void
    {
        $this->seedPmcFixture();
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($chair)
            ->get(route('academics.pmc.student-monitoring'))
            ->assertOk()
            ->assertSee('Student Monitoring')
            ->assertSee('Filtered Source List')
            ->assertSee('Aarav Sharma')
            ->assertSee(route('chair.students.at-risk'), false);

        $this->actingAs($chair)
            ->get(route('academics.pmc.reports'))
            ->assertOk()
            ->assertSee('PMC Reports')
            ->assertSee('Curriculum readiness')
            ->assertSee('Faculty workload');
    }

    public function test_pmc_service_respects_program_scope(): void
    {
        $fixture = $this->seedPmcFixture();
        $otherProgram = Program::factory()->create(['code' => 'BBA-HIDDEN', 'is_active' => true]);
        Subject::factory()->create([
            'program_id' => $otherProgram->id,
            'name' => 'Hidden Program Subject',
            'is_active' => true,
        ]);

        $chair = User::where('email', 'chair@college.com')->firstOrFail();
        $data = app(AcademicPmcOperatingService::class)->curriculumReadiness($chair);
        $titles = collect($data['items'])->pluck('title');

        $this->assertTrue($titles->contains('Industry Immersion Lab'));
        $this->assertFalse($titles->contains('Hidden Program Subject'));
        $this->assertEquals($fixture['program']->id, Program::where('code', 'PGDM')->value('id'));
    }

    public function test_non_academic_user_cannot_access_pmc_operating_system(): void
    {
        $this->seedPmcFixture();
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('student');

        $this->actingAs($user)
            ->get(route('academics.pmc.index'))
            ->assertForbidden();
    }
}
