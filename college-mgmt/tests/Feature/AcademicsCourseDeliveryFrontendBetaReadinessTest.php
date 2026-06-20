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

class AcademicsCourseDeliveryFrontendBetaReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_primary_course_delivery_surfaces_open_without_debug_traces(): void
    {
        $faculty = User::where('email', 'pmc.faculty@college.com')->firstOrFail();

        $routes = [
            'academics.course-delivery.index',
            'academics.course-delivery.course-load',
            'academics.course-delivery.session-delivery',
            'academics.course-delivery.attendance-interventions',
            'academics.course-delivery.course-engagement',
            'academics.course-delivery.mentor-actions',
            'academics.course-delivery.reports',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($faculty)->get(route($route));

            $response->assertOk()
                ->assertDontSee('Whoops', false)
                ->assertDontSee('SERVICE ERROR', false)
                ->assertDontSee('Stack trace', false)
                ->assertSee('page-body', false);
        }
    }

    public function test_admin_all_scope_course_delivery_dashboard_opens_without_service_error(): void
    {
        $admin = User::where('email', 'admin@demo.edu')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('academics.course-delivery.index'))
            ->assertOk()
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('Call to a member function getKey', false)
            ->assertSee('Course Delivery OS');
    }

    public function test_course_delivery_section_filters_are_source_backed_and_metric_links_are_not_placeholders(): void
    {
        $dean = User::where('email', 'dean@college.com')->firstOrFail();
        $target = $this->createAttendanceRiskFixture('Frontend Delivery Target');
        $other = $this->createAttendanceRiskFixture('Frontend Delivery Hidden');

        $response = $this->actingAs($dean)->get(route('academics.course-delivery.attendance-interventions', [
            'search' => $target->student->user->name,
            'status' => 'Follow-up due',
        ]));

        $response->assertOk()
            ->assertSee($target->student->user->name)
            ->assertDontSee($other->student->user->name)
            ->assertSee('Visible filter summary: Search: ' . $target->student->user->name . ' | Status: Follow-up due')
            ->assertSee('Export current view')
            ->assertSee(route('chair.students.at-risk'), false)
            ->assertDontSee('href="#source-list"', false)
            ->assertDontSee('href="#"', false);
    }

    public function test_primary_course_delivery_views_do_not_contain_placeholder_actions_or_broken_form_markup(): void
    {
        $viewPaths = [
            resource_path('views/academics/course-delivery/dashboard.blade.php'),
            resource_path('views/academics/course-delivery/section.blade.php'),
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

    private function createAttendanceRiskFixture(string $studentName): Attendance
    {
        $program = Program::where('code', 'PGDM')->first() ?? Program::query()->first();
        $department = $program?->department ?? Department::factory()->create(['code' => fake()->unique()->lexify('CD??')]);

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
