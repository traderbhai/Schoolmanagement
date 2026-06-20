<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalFrontendBetaReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_primary_portal_pages_open_without_debug_traces(): void
    {
        $routesByEmail = [
            'arjun.k@demo.edu' => [
                'student.dashboard',
                'student.timetable',
                'student.attendance',
                'student.results',
                'student.fees',
                'student.fee-payment.index',
                'student.resume.index',
            ],
            'anjali@demo.edu' => [
                'teacher.dashboard',
                'teacher.timetable.index',
                'teacher.attendance.mark',
                'teacher.exams.index',
                'teacher.students.index',
                'teacher.mentor.index',
            ],
            'parent@demo.edu' => [
                'parent.dashboard',
                'parent.children',
                'parent.notices',
            ],
            'priya.sharma@applicant.demo' => [
                'applicant.dashboard',
                'applicant.checklist',
                'applicant.application.show',
                'applicant.documents.index',
                'applicant.fees.index',
                'applicant.status',
            ],
        ];

        foreach ($routesByEmail as $email => $routes) {
            $user = User::where('email', $email)->firstOrFail();

            foreach ($routes as $route) {
                $response = $this->actingAs($user)->get(route($route));

                $response->assertOk()
                    ->assertDontSee('Whoops', false)
                    ->assertDontSee('SERVICE ERROR', false)
                    ->assertDontSee('Stack trace', false)
                    ->assertDontSee('Laravel\\', false)
                    ->assertSee('<title', false);
            }
        }
    }

    public function test_portal_dashboards_expose_real_action_links_for_daily_work(): void
    {
        $student = User::where('email', 'arjun.k@demo.edu')->firstOrFail();
        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee(route('student.timetable'), false)
            ->assertSee(route('student.attendance'), false)
            ->assertSee(route('student.results'), false)
            ->assertSee(route('student.fees'), false)
            ->assertDontSee('href="#"', false);

        $teacher = User::where('email', 'anjali@demo.edu')->firstOrFail();
        $this->actingAs($teacher)
            ->get(route('teacher.dashboard'))
            ->assertOk()
            ->assertSee(route('teacher.timetable.index'), false)
            ->assertSee(route('teacher.attendance.mark'), false)
            ->assertSee(route('teacher.assignments.index'), false)
            ->assertDontSee('href="#"', false);

        $parent = User::where('email', 'parent@demo.edu')->firstOrFail();
        $this->actingAs($parent)
            ->get(route('parent.dashboard'))
            ->assertOk()
            ->assertSee(route('parent.children'), false)
            ->assertSee(route('parent.notices'), false)
            ->assertDontSee('href="#"', false);

        $applicant = User::where('email', 'priya.sharma@applicant.demo')->firstOrFail();
        $this->actingAs($applicant)
            ->get(route('applicant.dashboard'))
            ->assertOk()
            ->assertSee(route('applicant.application.show'), false)
            ->assertSee(route('applicant.checklist'), false)
            ->assertSee(route('applicant.documents.index'), false)
            ->assertSee(route('applicant.fees.index'), false)
            ->assertDontSee('href="#"', false);
    }

    public function test_applicant_layout_uses_manifest_grouped_sidebar_links(): void
    {
        $applicant = User::where('email', 'priya.sharma@applicant.demo')->firstOrFail();

        $this->actingAs($applicant)
            ->get(route('applicant.dashboard'))
            ->assertOk()
            ->assertSee('Command')
            ->assertSee('Daily Work')
            ->assertSee('Track')
            ->assertSee('Dashboard')
            ->assertSee('Checklist')
            ->assertSee('Application')
            ->assertSee('Documents')
            ->assertSee('Fees')
            ->assertSee('Status Tracker')
            ->assertSee(route('applicant.dashboard'), false)
            ->assertSee(route('applicant.checklist'), false)
            ->assertSee(route('applicant.application.show'), false)
            ->assertSee(route('applicant.documents.index'), false)
            ->assertSee(route('applicant.fees.index'), false)
            ->assertSee(route('applicant.status'), false)
            ->assertDontSee('href="#"', false);

        $layout = file_get_contents(resource_path('views/layouts/applicant.blade.php'));
        $component = file_get_contents(resource_path('views/components/ui/manifest-sidebar.blade.php'));

        $this->assertStringContainsString('<x-ui.manifest-sidebar', $layout);
        $this->assertStringContainsString('$activePatterns = (array) ($item[\'active\'] ?? $item[\'route\']);', $component);
        $this->assertStringNotContainsString("preg_replace('/\\.[^.]+$/', '.*'", $component);
        $this->assertStringNotContainsString('Admission Checklist', $layout);
        $this->assertStringNotContainsString('Fees & Payments', $layout);
    }

    public function test_teacher_layout_uses_manifest_grouped_sidebar_links(): void
    {
        $teacher = User::where('email', 'anjali@demo.edu')->firstOrFail();

        $this->actingAs($teacher)
            ->get(route('teacher.dashboard'))
            ->assertOk()
            ->assertSee('Command')
            ->assertSee('Daily Work')
            ->assertSee('Academics / Delivery')
            ->assertSee('Students')
            ->assertSee('Settings')
            ->assertSee('Dashboard')
            ->assertSee('My Timetable')
            ->assertSee('Mark Attendance')
            ->assertSee('Enter Marks')
            ->assertSee('Study Materials')
            ->assertSee('Assignments')
            ->assertSee('Announcements')
            ->assertSee('My Students')
            ->assertSee('My Mentees')
            ->assertSee('My Feedback')
            ->assertSee('Leave')
            ->assertSee('My Profile')
            ->assertSee(route('teacher.dashboard'), false)
            ->assertSee(route('teacher.timetable.index'), false)
            ->assertSee(route('teacher.attendance.mark'), false)
            ->assertSee(route('teacher.exams.index'), false)
            ->assertSee(route('teacher.materials.index'), false)
            ->assertSee(route('teacher.assignments.index'), false)
            ->assertSee(route('teacher.students.index'), false)
            ->assertDontSee('href="#"', false);

        $layout = file_get_contents(resource_path('views/layouts/teacher.blade.php'));

        $this->assertStringContainsString('<x-ui.manifest-sidebar', $layout);
        $this->assertStringNotContainsString('<div class="section-label">Main</div>', $layout);
        $this->assertStringNotContainsString('<div class="section-label">Academics</div>', $layout);
        $this->assertStringNotContainsString('<div class="section-label">Mentoring</div>', $layout);
    }

    public function test_shared_admin_shell_teacher_branch_uses_manifest_sidebar(): void
    {
        $teacher = User::where('email', 'anjali@demo.edu')->firstOrFail();

        $this->actingAs($teacher)
            ->get(route('academics.course-delivery.index'))
            ->assertOk()
            ->assertSee('Command')
            ->assertSee('Daily Work')
            ->assertSee('Academics / Delivery')
            ->assertSee('Students')
            ->assertSee('Settings')
            ->assertSee('Dashboard')
            ->assertSee('Course Delivery')
            ->assertSee('My Timetable')
            ->assertSee('Mark Attendance')
            ->assertSee(route('teacher.dashboard'), false)
            ->assertSee(route('academics.course-delivery.index'), false)
            ->assertSee(route('teacher.timetable.index'), false)
            ->assertDontSee('href="#"', false);

        $layout = file_get_contents(resource_path('views/layouts/admin.blade.php'));

        $this->assertStringContainsString('<x-ui.manifest-sidebar role="teacher"', $layout);
        $this->assertStringNotContainsString('<div class="section-label">Main</div>' . PHP_EOL . '        <a href="{{ route(\'teacher.dashboard\') }}"', $layout);
    }

    public function test_parent_layout_uses_manifest_grouped_sidebar_links(): void
    {
        $parent = User::where('email', 'parent@demo.edu')->firstOrFail();

        $this->actingAs($parent)
            ->get(route('parent.dashboard'))
            ->assertOk()
            ->assertSee('Command')
            ->assertSee('Students')
            ->assertSee('Communication')
            ->assertSee('Dashboard')
            ->assertSee('My Children')
            ->assertSee('Notices')
            ->assertSee(route('parent.dashboard'), false)
            ->assertSee(route('parent.children'), false)
            ->assertSee(route('parent.notices'), false)
            ->assertDontSee('href="#"', false);

        $layout = file_get_contents(resource_path('views/layouts/parent.blade.php'));

        $this->assertStringContainsString('<x-ui.manifest-sidebar', $layout);
        $this->assertStringNotContainsString('<div class="section-label">Main</div>', $layout);
        $this->assertStringNotContainsString('<div class="section-label">Children</div>', $layout);
    }

    public function test_student_layout_uses_manifest_grouped_sidebar_links(): void
    {
        $student = User::where('email', 'arjun.k@demo.edu')->firstOrFail();

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Command')
            ->assertSee('Daily Work')
            ->assertSee('Academics / Delivery')
            ->assertSee('Finance')
            ->assertSee('Career')
            ->assertSee('Support')
            ->assertSee('Settings')
            ->assertSee('Dashboard')
            ->assertSee('My Timetable')
            ->assertSee('Attendance')
            ->assertSee('Results')
            ->assertSee('Admit Cards')
            ->assertSee('Subject Registration')
            ->assertSee('My Courses')
            ->assertSee('Assignments')
            ->assertSee('Fee Status')
            ->assertSee('Submit Payment')
            ->assertSee('Placements')
            ->assertSee('Library')
            ->assertSee('Academic Summary')
            ->assertSee('Notifications')
            ->assertSee(route('student.dashboard'), false)
            ->assertSee(route('student.timetable'), false)
            ->assertSee(route('student.attendance'), false)
            ->assertSee(route('student.results'), false)
            ->assertSee(route('student.fee-payment.index'), false)
            ->assertDontSee('href="#"', false);

        $layout = file_get_contents(resource_path('views/layouts/student.blade.php'));

        $this->assertStringContainsString('<x-ui.manifest-sidebar', $layout);
        $this->assertStringNotContainsString('<div class="section-label">Main</div>', $layout);
        $this->assertStringNotContainsString('<div class="section-label">Academics</div>', $layout);
        $this->assertStringNotContainsString('Results & Grades', $layout);
        $this->assertStringNotContainsString('Att. Condonation', $layout);
    }

    public function test_primary_portal_views_do_not_contain_placeholder_actions_or_broken_form_markup(): void
    {
        $viewPaths = [
            resource_path('views/student/dashboard.blade.php'),
            resource_path('views/student/timetable.blade.php'),
            resource_path('views/student/attendance.blade.php'),
            resource_path('views/student/results.blade.php'),
            resource_path('views/student/fees.blade.php'),
            resource_path('views/student/fee-payment-request/index.blade.php'),
            resource_path('views/student/resume/index.blade.php'),
            resource_path('views/teacher/dashboard.blade.php'),
            resource_path('views/teacher/timetable/index.blade.php'),
            resource_path('views/teacher/attendance/mark.blade.php'),
            resource_path('views/teacher/exams/index.blade.php'),
            resource_path('views/teacher/students.blade.php'),
            resource_path('views/teacher/mentor/index.blade.php'),
            resource_path('views/parent/dashboard.blade.php'),
            resource_path('views/parent/children.blade.php'),
            resource_path('views/parent/notices.blade.php'),
            resource_path('views/applicant/dashboard.blade.php'),
            resource_path('views/applicant/checklist.blade.php'),
            resource_path('views/applicant/application/show.blade.php'),
            resource_path('views/applicant/documents/index.blade.php'),
            resource_path('views/applicant/fees/index.blade.php'),
            resource_path('views/applicant/status.blade.php'),
        ];

        foreach ($viewPaths as $path) {
            $contents = file_get_contents($path);

            $this->assertStringNotContainsString('href="#"', $contents, $path);
            $this->assertStringNotContainsString("href='#'", $contents, $path);
            $this->assertStringNotContainsString('javascript:void', $contents, $path);
            $this->assertStringNotContainsString('×', $contents, $path);
            $this->assertStringNotContainsString('Laravel', $contents, $path);
            $this->assertStringNotContainsString('</form><form', $contents, $path);
            $this->assertStringNotContainsString('Ã‚', $contents, $path);
            $this->assertStringNotContainsString('Whoops', $contents, $path);
            $this->assertStringNotContainsString('Stack trace', $contents, $path);
        }
    }

    public function test_portal_visible_navigation_links_are_reachable_for_seeded_roles(): void
    {
        foreach ([
            ['arjun.k@demo.edu', 'student.dashboard'],
            ['anjali@demo.edu', 'teacher.dashboard'],
            ['parent@demo.edu', 'parent.dashboard'],
            ['priya.sharma@applicant.demo', 'applicant.dashboard'],
        ] as [$email, $routeName]) {
            $user = User::where('email', $email)->firstOrFail();
            $response = $this->actingAs($user)->get(route($routeName));

            $response->assertOk()
                ->assertDontSee('Whoops', false)
                ->assertDontSee('SERVICE ERROR', false)
                ->assertDontSee('Laravel\\', false);

            foreach ($this->internalGetLinks($response->getContent()) as $path) {
                $linkResponse = $this->actingAs($user)->get($path);

                $this->assertNotContains($linkResponse->getStatusCode(), [403, 404, 500], "{$email} visible link failed: {$path}");
            }
        }
    }

    public function test_portal_safe_action_entry_pages_are_reachable_and_guided(): void
    {
        $teacher = User::where('email', 'anjali@demo.edu')->firstOrFail();
        foreach ([
            'teacher.attendance.mark' => 'Mark Attendance',
            'teacher.materials.create' => 'Upload Material',
            'teacher.assignments.create' => 'Create Assignment',
            'teacher.students.index' => 'My Students',
        ] as $route => $heading) {
            $this->actingAs($teacher)
                ->get(route($route))
                ->assertOk()
                ->assertSee($heading, false)
                ->assertDontSee('Whoops', false)
                ->assertDontSee('SERVICE ERROR', false);
        }

        $student = User::where('email', 'arjun.k@demo.edu')->firstOrFail();
        foreach ([
            'student.fee-payment.create' => 'Submit Payment Proof',
            'student.documents.create' => 'Request a Document',
            'student.grievances.create' => 'Submit Grievance',
            'student.leave.create' => 'Apply for Leave',
        ] as $route => $heading) {
            $this->actingAs($student)
                ->get(route($route))
                ->assertOk()
                ->assertSee($heading)
                ->assertDontSee('Whoops', false)
                ->assertDontSee('SERVICE ERROR', false);
        }

        $applicant = User::where('email', 'priya.sharma@applicant.demo')->firstOrFail();
        foreach ([
            'applicant.checklist' => 'Admission Checklist',
            'applicant.documents.index' => 'Documents',
            'applicant.fees.index' => 'Fees &amp; Payments',
            'applicant.status' => 'Application Status',
        ] as $route => $heading) {
            $this->actingAs($applicant)
                ->get(route($route))
                ->assertOk()
                ->assertSee($heading, false)
                ->assertDontSee('Whoops', false)
                ->assertDontSee('SERVICE ERROR', false);
        }
    }

    public function test_parent_child_detail_links_and_portal_mobile_shell_are_usable(): void
    {
        $parent = User::where('email', 'parent@demo.edu')->firstOrFail();
        $child = \App\Models\ParentProfile::where('user_id', $parent->id)->firstOrFail()
            ->students()
            ->firstOrFail();

        foreach ([
            'parent.children.attendance' => 'Attendance',
            'parent.children.results' => 'Results',
            'parent.children.fees' => 'Fees',
        ] as $route => $heading) {
            $this->actingAs($parent)
                ->get(route($route, $child))
                ->assertOk()
                ->assertSee($heading)
                ->assertDontSee('Whoops', false)
                ->assertDontSee('SERVICE ERROR', false);
        }

        foreach ([
            ['arjun.k@demo.edu', 'student.dashboard'],
            ['anjali@demo.edu', 'teacher.dashboard'],
            ['parent@demo.edu', 'parent.dashboard'],
            ['priya.sharma@applicant.demo', 'applicant.dashboard'],
        ] as [$email, $route]) {
            $user = User::where('email', $email)->firstOrFail();

            $this->actingAs($user)
                ->get(route($route))
                ->assertOk()
                ->assertSee('id="mobileSidebar"', false)
                ->assertSee('sidebar-mobile-toggle', false)
                ->assertSee('aria-label="Open navigation menu"', false);
        }
    }

    public function test_student_personal_lists_show_operational_entries_or_empty_states(): void
    {
        $student = User::where('email', 'arjun.k@demo.edu')->firstOrFail();

        foreach ([
            'student.fees' => ['Fee Status', ['table-responsive', 'No fee demands']],
            'student.fee-payment.index' => ['Fee Payment Submissions', ['table-responsive', 'No payment submissions yet']],
            'student.documents.index' => ['Document Requests', ['table-responsive', 'No document requests yet']],
            'student.feedback.index' => ['Course Feedback', ['Give Feedback', 'No enrolled subjects found for feedback']],
            'student.grievances.index' => ['Grievances', ['table-responsive', 'No grievances']],
        ] as $route => [$heading, $expectedAny]) {
            $response = $this->actingAs($student)->get(route($route));

            $response->assertOk()
                ->assertSee($heading)
                ->assertDontSee('Whoops', false)
                ->assertDontSee('SERVICE ERROR', false);

            $this->assertTrue(
                collect($expectedAny)->contains(fn ($needle) => str_contains($response->getContent(), $needle)),
                "{$route} did not expose an operational list or empty state."
            );
        }
    }

    private function internalGetLinks(string $html): array
    {
        preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $matches);

        return collect($matches[1] ?? [])
            ->filter(fn ($href) => ! str_starts_with($href, '#'))
            ->filter(fn ($href) => ! str_starts_with($href, 'javascript:'))
            ->filter(fn ($href) => ! str_starts_with($href, 'mailto:'))
            ->filter(fn ($href) => ! preg_match('/\.(css|js|png|jpg|jpeg|svg|ico|json|webmanifest)(\?|$)/i', $href))
            ->map(function ($href) {
                if (str_starts_with($href, url('/'))) {
                    return parse_url($href, PHP_URL_PATH) . (parse_url($href, PHP_URL_QUERY) ? '?' . parse_url($href, PHP_URL_QUERY) : '');
                }

                return $href;
            })
            ->filter(fn ($href) => str_starts_with($href, '/'))
            ->unique()
            ->values()
            ->all();
    }
}
