<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Course;
use App\Models\Department;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableEntry;
use App\Models\TimetableSlot;
use App\Models\User;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsProgramLeadershipFrontendBetaReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_primary_program_leadership_surfaces_open_without_debug_traces(): void
    {
        $leader = User::where('email', 'chair@college.com')->firstOrFail();

        $routes = [
            'academics.program-leadership.index',
            'academics.program-leadership.portfolio',
            'academics.program-leadership.course-delivery',
            'academics.program-leadership.student-success',
            'academics.program-leadership.quality-signals',
            'academics.program-leadership.reports',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($leader)->get(route($route));

            $response->assertOk()
                ->assertDontSee('Whoops', false)
                ->assertDontSee('SERVICE ERROR', false)
                ->assertDontSee('Stack trace', false)
                ->assertSee('page-body', false);
        }
    }

    public function test_program_leadership_section_filters_are_source_backed_and_metric_links_are_not_placeholders(): void
    {
        $leader = User::where('email', 'chair@college.com')->firstOrFail();
        $target = $this->createAttendanceRiskFixture('Frontend Program Target');
        $other = $this->createAttendanceRiskFixture('Frontend Program Hidden');

        $response = $this->actingAs($leader)->get(route('academics.program-leadership.student-success', [
            'search' => $target->student->user->name,
            'status' => 'Intervention due',
        ]));

        $response->assertOk()
            ->assertSee($target->student->user->name)
            ->assertDontSee($other->student->user->name)
            ->assertSee('Visible filter summary: Search: ' . $target->student->user->name . ' | Status: Intervention due')
            ->assertSee('Export current view')
            ->assertSee(route('chair.students.at-risk'), false)
            ->assertDontSee('href="#source-list"', false)
            ->assertDontSee('href="#"', false);
    }

    public function test_primary_program_leadership_views_do_not_contain_placeholder_actions_or_broken_form_markup(): void
    {
        $viewPaths = [
            resource_path('views/academics/program-leadership/dashboard.blade.php'),
            resource_path('views/academics/program-leadership/section.blade.php'),
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

    public function test_program_leadership_shared_shell_uses_manifest_grouped_sidebar_links(): void
    {
        $leader = User::where('email', 'chair@college.com')->firstOrFail();

        $response = $this->actingAs($leader)->get(route('academics.program-leadership.index'));

        $response->assertOk()
            ->assertSee('Program Workspace')
            ->assertSee('Program Leadership')
            ->assertSee('Portfolio')
            ->assertSee('Student Success')
            ->assertSee('Student Monitoring')
            ->assertSee('Quality Signals')
            ->assertSee('Program Reports')
            ->assertSee(route('academics.workspaces.show', 'program'), false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Whoops', false);
    }

    private function createAttendanceRiskFixture(string $studentName): Attendance
    {
        $program = Program::where('code', 'PGDM')->first() ?? Program::query()->first();
        $department = $program?->department ?? Department::factory()->create(['code' => fake()->unique()->lexify('PL??')]);

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
        $studentUser = User::factory()->create(['name' => $studentName]);
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'department_id' => $department->id,
            'program_id' => $program->id,
            'status' => 'active',
        ]);
        $entry = TimetableEntry::factory()->create([
            'semester_id' => Semester::factory()->create(['is_current' => true])->id,
            'course_id' => Course::factory()->create(['department_id' => $department->id])->id,
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'teacher_id' => Teacher::factory()->create(['department_id' => $department->id])->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => 1,
            'is_active' => true,
            'status' => 'published',
            'timetable_version_id' => null,
        ]);

        Attendance::create([
            'student_id' => $student->id,
            'timetable_entry_id' => $entry->id,
            'date' => now()->subDays(2)->toDateString(),
            'status' => 'absent',
        ]);

        return Attendance::create([
            'student_id' => $student->id,
            'timetable_entry_id' => $entry->id,
            'date' => now()->subDay()->toDateString(),
            'status' => 'late',
        ])->load('student.user');
    }
}
