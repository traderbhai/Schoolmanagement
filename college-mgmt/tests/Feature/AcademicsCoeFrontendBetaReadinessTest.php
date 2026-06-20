<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Exam;
use App\Models\ExamRegistration;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsCoeFrontendBetaReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_primary_coe_surfaces_open_without_debug_traces(): void
    {
        $examUser = User::where('email', 'exam@college.com')->firstOrFail();

        $routes = [
            'academics.coe.index',
            'academics.coe.exam-readiness',
            'academics.coe.marks-results',
            'academics.coe.hall-ticket-readiness',
            'academics.coe.transcripts',
            'academics.coe.appeals-anomalies',
            'academics.coe.reports',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($examUser)->get(route($route));

            $response->assertOk()
                ->assertDontSee('Whoops', false)
                ->assertDontSee('SERVICE ERROR', false)
                ->assertDontSee('Stack trace', false)
                ->assertSee('page-body', false);
        }
    }

    public function test_coe_section_filters_are_source_backed_and_metric_links_are_not_placeholders(): void
    {
        $examUser = User::where('email', 'exam@college.com')->firstOrFail();
        $target = $this->createHallTicketFixture('Frontend CoE Target');
        $other = $this->createHallTicketFixture('Frontend CoE Hidden');

        $response = $this->actingAs($examUser)->get(route('academics.coe.hall-ticket-readiness', [
            'search' => $target->student->user->name,
            'status' => 'Approval pending',
        ]));

        $response->assertOk()
            ->assertSee($target->student->user->name)
            ->assertDontSee($other->student->user->name)
            ->assertSee('Visible filter summary: Search: ' . $target->student->user->name . ' | Status: Approval pending')
            ->assertSee('Export current view')
            ->assertSee(route('exam-cell.hall-tickets', ['exam_id' => $target->exam_id]), false)
            ->assertDontSee('href="#source-list"', false)
            ->assertDontSee('href="#"', false);
    }

    public function test_primary_coe_views_do_not_contain_placeholder_actions_or_broken_form_markup(): void
    {
        $viewPaths = [
            resource_path('views/academics/coe/dashboard.blade.php'),
            resource_path('views/academics/coe/section.blade.php'),
        ];

        foreach ($viewPaths as $path) {
            $contents = file_get_contents($path);

            $this->assertStringNotContainsString('href="#"', $contents, $path);
            $this->assertStringNotContainsString("href='#'", $contents, $path);
            $this->assertStringNotContainsString('href="#source-list"', $contents, $path);
            $this->assertStringNotContainsString('</form><form', $contents, $path);
            $this->assertStringNotContainsString('Â', $contents, $path);
        }
    }

    public function test_exam_cell_shared_shell_uses_manifest_grouped_sidebar_links(): void
    {
        $examUser = User::where('email', 'exam@college.com')->firstOrFail();

        $response = $this->actingAs($examUser)->get(route('academics.coe.index'));

        $response->assertOk()
            ->assertSee('CoE OS')
            ->assertSee('CoE Workspace')
            ->assertSee('Academics Governance')
            ->assertSee('Schedule Exam')
            ->assertSee('Anomaly Log')
            ->assertSee('Legacy Transcripts')
            ->assertSee(route('academics.workspaces.show', 'coe'), false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Whoops', false);
    }

    private function createHallTicketFixture(string $studentName): ExamRegistration
    {
        $program = Program::where('code', 'PGDM')->first() ?? Program::query()->first();
        $department = $program?->department ?? Department::factory()->create(['code' => fake()->unique()->lexify('COE??')]);

        if (! $program) {
            $program = Program::factory()->create([
                'department_id' => $department->id,
                'code' => fake()->unique()->lexify('PG??'),
                'is_active' => true,
            ]);
        }
        $subject = Subject::factory()->create([
            'department_id' => $department->id,
            'program_id' => $program->id,
            'is_active' => true,
        ]);
        $semester = Semester::factory()->create(['is_current' => true]);
        $studentUser = User::factory()->create(['name' => $studentName]);
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'department_id' => $department->id,
            'program_id' => $program->id,
            'status' => 'active',
        ]);
        $exam = Exam::factory()->create([
            'program_id' => $program->id,
            'semester_id' => $semester->id,
            'subject_id' => $subject->id,
            'name' => 'Frontend Hall Ticket Check',
            'exam_date' => now()->addDays(7),
            'published_at' => null,
        ]);

        return ExamRegistration::create([
            'student_id' => $student->id,
            'exam_id' => $exam->id,
            'status' => 'pending',
            'attendance_eligible' => true,
            'fee_cleared' => true,
        ])->load(['student.user', 'exam']);
    }
}
