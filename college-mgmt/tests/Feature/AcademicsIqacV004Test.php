<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\AcademicIqacOperatingService;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcademicsIqacV004Test extends TestCase
{
    use RefreshDatabase;

    private function seedIqacFixture(): array
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        $subject = Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT101', 'name' => 'Management Foundations', 'is_active' => true]);
        $studentUser = User::factory()->create(['name' => 'Naina Kapoor']);
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

    public function test_iqac_head_can_open_operating_dashboard(): void
    {
        $this->seedIqacFixture();
        $iqac = User::where('email', 'iqac.head@college.com')->firstOrFail();

        $this->actingAs($iqac)
            ->get(route('academics.iqac.index'))
            ->assertOk()
            ->assertSee('IQAC Operating System')
            ->assertSee('OBE Readiness')
            ->assertSee('Attainment Monitoring')
            ->assertSee('Feedback Quality')
            ->assertSee('Audit And Compliance')
            ->assertSee('CO-IQAC-GAP');
    }

    public function test_iqac_source_lists_are_database_backed_and_link_to_source_workflows(): void
    {
        $this->seedIqacFixture();
        $iqac = User::where('email', 'iqac.head@college.com')->firstOrFail();

        $this->actingAs($iqac)
            ->get(route('academics.iqac.attainment-monitoring'))
            ->assertOk()
            ->assertSee('Attainment Monitoring')
            ->assertSee('Filtered Source List')
            ->assertSee('CO-IQAC-1')
            ->assertSee(route('academic.obe.attainment'), false);

        $this->actingAs($iqac)
            ->get(route('academics.iqac.feedback-quality'))
            ->assertOk()
            ->assertSee('Feedback And Survey Quality')
            ->assertSee('IQAC Indirect Attainment Survey');
    }

    public function test_iqac_reports_page_lists_quality_reports(): void
    {
        $this->seedIqacFixture();
        $iqac = User::where('email', 'iqac.head@college.com')->firstOrFail();

        $this->actingAs($iqac)
            ->get(route('academics.iqac.reports'))
            ->assertOk()
            ->assertSee('IQAC Reports')
            ->assertSee('OBE readiness')
            ->assertSee('Attainment monitoring')
            ->assertSee('Feedback quality');
    }

    public function test_iqac_service_respects_program_scope(): void
    {
        $this->seedIqacFixture();
        $otherProgram = Program::factory()->create(['code' => 'BBA-HIDDEN', 'is_active' => true]);
        Subject::factory()->create(['program_id' => $otherProgram->id, 'name' => 'Hidden Quality Subject', 'is_active' => true]);

        $manager = User::where('email', 'iqac.manager@college.com')->firstOrFail();
        $data = app(AcademicIqacOperatingService::class)->obeReadiness($manager);
        $titles = collect($data['items'])->pluck('title');

        $this->assertTrue($titles->contains(fn ($title) => str_contains($title, 'CO-IQAC-GAP')));
        $this->assertFalse($titles->contains('Hidden Quality Subject'));
    }

    public function test_non_academic_user_cannot_access_iqac_operating_system(): void
    {
        $this->seedIqacFixture();
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('student');

        $this->actingAs($user)
            ->get(route('academics.iqac.index'))
            ->assertForbidden();
    }
}
