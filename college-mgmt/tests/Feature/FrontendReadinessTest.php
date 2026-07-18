<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\FrontendNavigation;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FrontendReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_frontend_navigation_manifest_uses_existing_named_routes(): void
    {
        foreach (FrontendNavigation::flatRoutes() as $item) {
            $this->assertTrue(
                FrontendNavigation::routeExists($item['route']),
                "Missing route [{$item['route']}] for {$item['role']} / {$item['group']} / {$item['label']}."
            );
        }
    }

    public function test_frontend_navigation_manifest_has_compact_operational_groups(): void
    {
        $allowedGroups = [
            'Landing',
            'Command',
            'Daily Work',
            'Admission',
            'People',
            'Students',
            'Students / Applicants',
            'Applicants',
            'Assessments',
            'Academics / Delivery',
            'Curriculum',
            'Exams',
            'Process',
            'Leads',
            'Planning',
            'Placement',
            'Timetable',
            'Quality',
            'Finance',
            'Career',
            'Communication',
            'Approvals',
            'Operations',
            'Support',
            'Governance',
            'Reports',
            'Settings',
            'Track',
        ];

        foreach (FrontendNavigation::manifest() as $role => $config) {
            $this->assertArrayHasKey('landing', $config, "Missing landing for {$role}.");
            $this->assertArrayHasKey('email', $config, "Missing demo email for {$role}.");
            $this->assertArrayHasKey('groups', $config, "Missing groups for {$role}.");

            foreach (array_keys($config['groups']) as $group) {
                $this->assertContains($group, $allowedGroups, "Unexpected frontend nav group [{$group}] for {$role}.");
            }
        }
    }

    public function test_manifest_demo_users_can_open_landing_pages_without_debug_traces(): void
    {
        foreach (FrontendNavigation::manifest() as $role => $config) {
            $user = User::where('email', $config['email'])->first();

            $this->assertNotNull($user, "Expected seeded user {$config['email']} for {$role}.");

            $response = $this->actingAs($user)->get(route($config['landing']));

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "Landing route [{$config['landing']}] for {$role} returned {$response->getStatusCode()}."
            );

            $response
                ->assertDontSee('SERVICE ERROR')
                ->assertDontSee('Laravel')
                ->assertDontSee('Whoops')
                ->assertSee('<title', false);
        }
    }

    public function test_manifest_top_workflow_routes_open_for_seeded_demo_users(): void
    {
        foreach (FrontendNavigation::manifest() as $role => $config) {
            $user = User::where('email', $config['email'])->firstOrFail();
            $routes = collect($config['groups'])
                ->flatten(1)
                ->reject(fn (array $item) => isset($item['paramsFrom']))
                ->unique(fn (array $item) => $item['route'] . serialize($item['params'] ?? []))
                ->take(5);

            foreach ($routes as $item) {
                $routeName = $item['route'];

                if (! Route::has($routeName)) {
                    $this->fail("Missing route [{$routeName}] for {$role}.");
                }

                $response = $this->actingAs($user)->get(route($routeName, $item['params'] ?? []));

                $this->assertNotContains(
                    $response->getStatusCode(),
                    [404, 500],
                    "Frontend route [{$routeName}] for {$role} returned {$response->getStatusCode()}."
                );

                if ($response->isOk()) {
                    $response
                        ->assertDontSee('SERVICE ERROR')
                        ->assertDontSee('Laravel')
                        ->assertDontSee('Whoops');
                }
            }
        }
    }

    public function test_manifest_landing_pages_render_operational_page_shells(): void
    {
        foreach (FrontendNavigation::manifest() as $role => $config) {
            $user = User::where('email', $config['email'])->firstOrFail();
            $response = $this->actingAs($user)->get(route($config['landing']));

            $this->assertSame(200, $response->getStatusCode(), "{$role} landing page did not load.");

            $html = (string) $response->getContent();

            $this->assertStringContainsString('<title', $html, "{$role} landing page is missing a document title.");
            $this->assertMatchesRegularExpression('/<main\b[^>]*id="main-content"/i', $html, "{$role} landing page is missing the main content landmark.");
            $this->assertStringContainsString('href="#main-content"', $html, "{$role} landing page is missing the skip link.");
            $this->assertStringNotContainsString('SERVICE ERROR', $html, "{$role} landing page rendered the service error screen.");
            $this->assertStringNotContainsString('Whoops', $html, "{$role} landing page rendered a debug exception.");
            $this->assertStringNotContainsString('href="#"', $html, "{$role} landing page contains a dead-end placeholder link.");
        }
    }

    public function test_priority_role_pages_render_clear_titles_and_headings(): void
    {
        foreach (self::priorityVisibleLinkPages() as $label => $page) {
            $user = User::where('email', $page['email'])->firstOrFail();
            $response = $this->actingAs($user)->get($page['path']);

            $this->assertSame(200, $response->getStatusCode(), "{$label} page did not load.");

            $document = new \DOMDocument();
            libxml_use_internal_errors(true);
            $document->loadHTML((string) $response->getContent());
            libxml_clear_errors();

            $title = trim((string) optional($document->getElementsByTagName('title')->item(0))->textContent);
            $headings = collect(iterator_to_array($document->getElementsByTagName('h1')))
                ->map(fn (\DOMElement $heading) => trim(preg_replace('/\s+/', ' ', $heading->textContent)))
                ->filter()
                ->values();

            $this->assertNotSame('', $title, "{$label} page has an empty document title.");
            $this->assertNotContains($title, [
                'EduManage - Portal',
                'EduManage - Student',
                'EduManage - Teacher',
                'EduManage - Applicant Portal',
                'EduManage - Parent',
            ], "{$label} page is using a generic layout document title.");
            $this->assertNotEmpty($headings, "{$label} page is missing a clear H1 page heading.");
            $this->assertNotContains('', $headings->all(), "{$label} page has an empty H1 page heading.");
        }
    }

    public function test_priority_role_pages_expose_keyboard_skip_and_main_landmark(): void
    {
        foreach (self::priorityVisibleLinkPages() as $label => $page) {
            $user = User::where('email', $page['email'])->firstOrFail();
            $response = $this->actingAs($user)->get($page['path']);

            $this->assertSame(200, $response->getStatusCode(), "{$label} page did not load.");

            $html = (string) $response->getContent();

            $this->assertMatchesRegularExpression('/<main\b[^>]*id="main-content"/i', $html, "{$label} page is missing the main content landmark.");
            $this->assertStringContainsString('href="#main-content"', $html, "{$label} page is missing the skip-to-content link.");
        }
    }

    public function test_priority_role_pages_do_not_render_dead_end_visible_links(): void
    {
        foreach (self::priorityVisibleLinkPages() as $label => $page) {
            $user = User::where('email', $page['email'])->firstOrFail();
            $response = $this->actingAs($user)->get($page['path']);

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "{$label} source page [{$page['path']}] returned {$response->getStatusCode()}."
            );

            $links = $this->sameAppLinks((string) $response->getContent(), $page['path']);

            foreach ($links as $href) {
                $linkResponse = $this->actingAs($user)->get($href);

                $this->assertNotContains(
                    $linkResponse->getStatusCode(),
                    [403, 404, 500],
                    "{$label} rendered visible link [{$href}] returned {$linkResponse->getStatusCode()}."
                );

                if ($linkResponse->isOk()) {
                    $linkResponse
                        ->assertDontSee('SERVICE ERROR')
                        ->assertDontSee('Laravel')
                        ->assertDontSee('Whoops');
                }
            }
        }
    }

    public function test_primary_layouts_do_not_use_placeholder_action_links(): void
    {
        foreach (self::primaryLayoutProvider() as [$layout]) {
            $contents = file_get_contents(resource_path("views/layouts/{$layout}.blade.php"));

            $this->assertStringNotContainsString('href="#"', $contents, "{$layout} layout contains a placeholder href.");
            $this->assertStringNotContainsString("href='#'", $contents, "{$layout} layout contains a placeholder href.");
        }
    }

    public function test_blade_views_do_not_use_dead_or_javascript_links(): void
    {
        $deadLinks = [];
        $blockedPatterns = [
            '/\bhref\s*=\s*(["\'])\s*#\s*\1/i',
            '/\bhref\s*=\s*(["\'])\s*\1/i',
            '/\bhref\s*=\s*(["\'])\s*javascript:/i',
        ];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            foreach ($blockedPatterns as $pattern) {
                preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE);

                foreach ($matches[0] as [$attribute, $offset]) {
                    $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                    $deadLinks[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . $line . ' ' . $attribute;
                }
            }
        }

        $this->assertSame([], $deadLinks, 'Links must point to real routes, anchors, or external targets; use buttons for JavaScript-only actions.');
    }

    public function test_new_tab_links_use_noopener_rel(): void
    {
        $unsafeLinks = [];
        $blockedPattern = '/<a\b(?=[^>]*\btarget\s*=\s*(["\'])_blank\1)(?![^>]*\brel\s*=\s*(["\'])[^"\']*noopener[^"\']*\2)[^>]*>/i';
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            preg_match_all($blockedPattern, $contents, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as [$link, $offset]) {
                $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                $unsafeLinks[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . $line . ' ' . $link;
            }
        }

        $this->assertSame([], $unsafeLinks, 'Links that open a new tab need rel="noopener" to avoid unsafe opener access.');
    }

    public function test_shared_destructive_action_modals_explain_downstream_impact(): void
    {
        $expectations = [
            'admin' => 'linked timetable, attendance, finance, admissions, reports, or audit records',
            'teacher' => 'linked timetable, attendance, materials, assignments, reports, or audit records',
        ];

        foreach ($expectations as $layout => $impactText) {
            $contents = file_get_contents(resource_path("views/layouts/{$layout}.blade.php"));

            $this->assertStringContainsString('This action cannot be undone', $contents);
            $this->assertStringContainsString($impactText, $contents);
            $this->assertStringContainsString('aria-labelledby="deleteModalLabel"', $contents);
            $this->assertStringContainsString('aria-hidden="true"', $contents);
        }
    }

    public function test_priority_ux_surfaces_keep_accessible_action_names(): void
    {
        $expectations = [
            'views/layouts/admin.blade.php' => [
                'aria-label="Open navigation menu"',
                'aria-label="Global search"',
                'aria-label="Notifications"',
            ],
            'views/layouts/student.blade.php' => [
                'aria-label="Open navigation menu"',
                'aria-label="Close navigation menu"',
            ],
            'views/layouts/teacher.blade.php' => [
                'aria-label="Open navigation menu"',
                'aria-label="Search assigned students"',
                'aria-label="Notifications"',
            ],
            'views/academics/pmc/v041/scoped-timetable.blade.php' => [
                'aria-label="Open timetable session details',
                'visually-hidden',
            ],
            'views/student/fees.blade.php' => [
                'aria-label="Download receipt PDF"',
            ],
            'views/student/dashboard.blade.php' => [
                'aria-label="Open attendance details"',
                'aria-label="Open fee outstanding details"',
            ],
        ];

        foreach ($expectations as $relativePath => $needles) {
            $contents = file_get_contents(resource_path($relativePath));

            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $contents, "{$relativePath} is missing {$needle}.");
            }
        }
    }

    public function test_priority_workflow_pages_avoid_vague_action_labels(): void
    {
        $blockedLabels = [];
        $priorityPages = [
            'views/admin/dashboard.blade.php',
            'views/admin/fees/index.blade.php',
            'views/admin/library/index.blade.php',
            'views/admission/dashboard.blade.php',
            'views/admission/workbench.blade.php',
            'views/admission/applicants/index.blade.php',
            'views/admission/leads/index.blade.php',
            'views/admission/v003/call-queue.blade.php',
            'views/admission/v0031/counsellor-workspace.blade.php',
            'views/admission/v0031/calendar.blade.php',
            'views/admission/v0031/manager-workspace.blade.php',
            'views/admission/v0031/manager-reviews.blade.php',
            'views/admission/v0031/reminders.blade.php',
            'views/admission/v0031/walk-ins.blade.php',
            'views/admission/v0036/assessment-control-room.blade.php',
            'views/admission/v0038/quick-search.blade.php',
            'views/academics/coe/dashboard.blade.php',
            'views/academics/coe/section.blade.php',
            'views/academics/course-delivery/dashboard.blade.php',
            'views/academics/course-delivery/section.blade.php',
            'views/academics/iqac/dashboard.blade.php',
            'views/academics/iqac/section.blade.php',
            'views/academics/pmc/dashboard.blade.php',
            'views/academics/pmc/section.blade.php',
            'views/academics/program-leadership/dashboard.blade.php',
            'views/academics/program-leadership/section.blade.php',
            'views/academics/dean-os/calendar.blade.php',
            'views/academics/dean-os/dashboard.blade.php',
            'views/academics/dean-os/reviews.blade.php',
            'views/academics/dean-os/v008/approvals.blade.php',
            'views/academics/dean-os/v008/operating-surface.blade.php',
            'views/academics/dean-os/v008/review-templates.blade.php',
            'views/academics/pmc/v041/dashboard.blade.php',
            'views/academics/pmc/v041/launch-wizard.blade.php',
            'views/student/dashboard.blade.php',
        ];

        foreach ($priorityPages as $relativePath) {
            $contents = file_get_contents(resource_path($relativePath));
            preg_match_all('/>\s*(View All|All|Open|Go|Submit|Apply)\s*<\/(?:a|button)>/i', $contents, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as [$label, $offset]) {
                $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                $blockedLabels[] = "{$relativePath}:{$line} {$label}";
            }
        }

        $this->assertSame([], $blockedLabels, 'Priority workflow pages should use destination-specific action text.');
    }

    public function test_visible_action_controls_use_specific_labels(): void
    {
        $blockedLabels = [];
        $blockedPattern = '/>\s*(Open|Go|Apply|Submit|Save|Update|Send|Confirm|Approve|Reject|View all|All)\s*<\/(?:a|button)>/i';
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            preg_match_all($blockedPattern, $contents, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as [$label, $offset]) {
                $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                $blockedLabels[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . $line . ' ' . $label;
            }
        }

        $this->assertSame([], $blockedLabels, 'Visible link and button text should describe the target or submitted action.');
    }

    public function test_confirmation_prompts_explain_operational_impact(): void
    {
        $terseConfirmations = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            preg_match_all("/confirm\\('([^']{0,90})'\\)/", $contents, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[1] as [$copy, $offset]) {
                $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                $terseConfirmations[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . $line . ' ' . $copy;
            }
        }

        $this->assertSame([], $terseConfirmations, 'Confirmation prompts should name the operational consequence, dependencies, or downstream visibility before the user commits an action.');
    }

    public function test_blade_views_do_not_contain_encoding_or_tag_artifacts(): void
    {
        $artifacts = [];
        $blockedPatterns = [
            '/[â�ÃÂ]/u',
            '/\bi(?:option|td|\/option|\/td|\/tr|tr|thead|tbody|table|div|span|form|input|button|select|label|strong|code|i)\b/',
            '/(?:<\/?(?:a|button|div|form|input|label|option|select|span|table|tbody|td|th|thead|tr)R\b|\$[A-Za-z_][A-Za-z0-9_]*-R[A-Za-z_?])/',
        ];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            foreach ($blockedPatterns as $pattern) {
                preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE);

                foreach ($matches[0] as [$match, $offset]) {
                    $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                    $artifacts[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . $line . ' ' . $match;
                }
            }
        }

        $this->assertSame([], $artifacts, 'Blade views should not contain mojibake or malformed tag artifacts.');
    }

    public function test_instructional_links_use_destination_specific_text(): void
    {
        $vagueLinks = [];
        $blockedPattern = '/>\s*(Click here|Here|More|Read more)\s*<\/a>/i';
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            preg_match_all($blockedPattern, $contents, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as [$label, $offset]) {
                $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                $vagueLinks[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . $line . ' ' . $label;
            }
        }

        $this->assertSame([], $vagueLinks, 'Instructional links should name the destination or object, not use vague text such as Read more or Click here.');
    }

    public function test_empty_states_explain_what_is_missing(): void
    {
        $genericEmptyStates = [];
        $blockedPattern = '/>\s*(No data found|No records found|No results found|No items found|No entries found|No information found|No details found|No data available|No records available|No results available|Nothing found|No matching records)\s*(?:<|for\b)/i';
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            preg_match_all($blockedPattern, $contents, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as [$copy, $offset]) {
                $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                $genericEmptyStates[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . $line . ' ' . trim(strip_tags($copy));
            }
        }

        $this->assertSame([], $genericEmptyStates, 'Empty states should name the missing object or next step instead of using generic "No records found" style copy.');
    }

    public function test_icon_only_action_controls_have_accessible_names(): void
    {
        $unnamedIconControls = [];
        $blockedPatterns = [
            '/<(a|button)\b(?!(?=[^>]*\baria-label=)|(?=[^>]*\baria-labelledby=)|(?=[^>]*\btitle=))[^>]*>\s*<i\b[^>]*>\s*<\/i>\s*<\/\1>/is',
            '/<(a|button)\b(?!(?=[^>]*\baria-label=)|(?=[^>]*\baria-labelledby=)|(?=[^>]*\btitle=))[^>]*>\s*<span\b[^>]*class=(["\'])[^"\']*icon[^"\']*\2[^>]*>\s*<\/span>\s*<\/\1>/is',
        ];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            foreach ($blockedPatterns as $pattern) {
                preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE);

                foreach ($matches[0] as [$control, $offset]) {
                    $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                    $unnamedIconControls[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . $line . ' ' . $control;
                }
            }
        }

        $this->assertSame([], $unnamedIconControls, 'Icon-only links and buttons need aria-label, aria-labelledby, or title.');
    }

    public function test_bootstrap_ui_buttons_declare_button_type(): void
    {
        $implicitSubmitButtons = [];
        $blockedPattern = '/<button\b(?![^>]*\btype=)(?=[^>]*\b(?:data-bs-toggle|data-bs-dismiss)=)[^>]*>/i';
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            preg_match_all($blockedPattern, $contents, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as [$button, $offset]) {
                $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                $implicitSubmitButtons[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . $line . ' ' . $button;
            }
        }

        $this->assertSame([], $implicitSubmitButtons, 'Bootstrap toggle/dismiss buttons need type="button" so they cannot accidentally submit nearby forms.');
    }

    public function test_images_have_meaningful_alt_text(): void
    {
        $imageIssues = [];
        $genericAltPattern = '/\balt\s*=\s*(["\'])(?:Photo|Image|Picture|Avatar)\1/i';
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            preg_match_all('/<img\b(?:"[^"]*"|\'[^\']*\'|[^>])*>/is', $contents, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as [$tag, $offset]) {
                if (! preg_match('/\balt\s*=/i', $tag) || preg_match($genericAltPattern, $tag)) {
                    $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                    $imageIssues[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . $line . ' ' . $tag;
                }
            }
        }

        $this->assertSame([], $imageIssues, 'Images need alt text that describes the image purpose; avoid generic labels such as Photo, Image, Picture, or Avatar.');
    }

    public function test_primary_layouts_expose_skip_links_and_main_landmarks(): void
    {
        $layoutExpectations = [
            'layouts/admin.blade.php' => [
                'href="#main-content"',
                'class="skip-link"',
                '<main id="main-content" class="page-body" tabindex="-1">',
                'aria-label="Open navigation menu"',
                'aria-label="Close alert"',
            ],
            'layouts/teacher.blade.php' => [
                'href="#main-content"',
                'class="skip-link"',
                '<main id="main-content" class="page-body" tabindex="-1">',
                'aria-label="Open navigation menu"',
                'aria-label="Close alert"',
            ],
            'layouts/student.blade.php' => [
                'href="#main-content"',
                'class="skip-link"',
                '<main id="main-content" class="content-area" tabindex="-1">',
                'aria-label="Open navigation menu"',
                'aria-label="Notifications"',
                'aria-label="Close alert"',
            ],
            'layouts/parent.blade.php' => [
                'href="#main-content"',
                'class="skip-link"',
                '<main id="main-content" class="page-body" tabindex="-1">',
                'aria-label="Open navigation menu"',
                'aria-label="Close alert"',
            ],
            'layouts/applicant.blade.php' => [
                'href="#main-content"',
                'class="skip-link"',
                '<main id="main-content" class="main-content" tabindex="-1">',
                'aria-label="Open navigation menu"',
                'aria-label="Close navigation menu"',
                'aria-label="Close alert"',
            ],
            'layouts/admission-partner.blade.php' => [
                'href="#main-content"',
                'class="skip-link"',
                '<main id="main-content" class="main-content" tabindex="-1">',
                'aria-label="Open navigation menu"',
                'aria-label="Close navigation menu"',
                'aria-label="Close alert"',
            ],
            'layouts/app.blade.php' => [
                'href="#main-content"',
                'Skip to main content',
                '<main id="main-content" tabindex="-1">',
            ],
        ];

        foreach ($layoutExpectations as $relativePath => $needles) {
            $contents = file_get_contents(resource_path("views/{$relativePath}"));

            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $contents, "{$relativePath} is missing {$needle}.");
            }
        }

        $css = file_get_contents(public_path('css/app.css'));

        foreach (['.skip-link', '.skip-link:focus', 'transform: translateY(0)'] as $needle) {
            $this->assertStringContainsString($needle, $css, "Shared CSS is missing {$needle}.");
        }
    }

    public function test_dismiss_controls_have_accessible_names(): void
    {
        $missingLabels = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            preg_match_all('/<button(?=[^>]*data-bs-dismiss="(?:alert|modal|offcanvas)")(?![^>]*aria-label)[^>]*>(.*?)<\/button>/is', $contents, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as $index => [$tag, $offset]) {
                $buttonText = trim(strip_tags($matches[1][$index][0]));

                if ($buttonText !== '') {
                    continue;
                }

                $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                $missingLabels[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . $line . ' ' . $tag;
            }
        }

        $this->assertSame([], $missingLabels);
    }

    public function test_table_headers_expose_semantic_scope(): void
    {
        $missingScope = [];
        $emptyColumnHeaders = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            preg_match_all('/<th\b(?![^>]*\bscope=)[^>]*>/i', $contents, $unscopedHeaders, PREG_OFFSET_CAPTURE);
            preg_match_all('/<th\b(?=[^>]*\bscope="col")(?![^>]*\baria-label=)[^>]*>\s*<\/th>/i', $contents, $emptyHeaders, PREG_OFFSET_CAPTURE);

            foreach ($unscopedHeaders[0] as [$tag, $offset]) {
                $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                $missingScope[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . $line . ' ' . $tag;
            }

            foreach ($emptyHeaders[0] as [$tag, $offset]) {
                $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                $emptyColumnHeaders[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . $line . ' ' . $tag;
            }
        }

        $this->assertSame([], $missingScope, 'Every table header must declare scope="col" or scope="row".');
        $this->assertSame([], $emptyColumnHeaders, 'Empty column headers need an aria-label, usually for action columns.');
    }

    public function test_placeholder_form_controls_have_accessible_names(): void
    {
        $unnamedPlaceholderControls = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            preg_match_all('/<(input|select|textarea)\b(?![^>]*\btype="hidden")(?![^>]*\b(?:id|aria-label|aria-labelledby)=)(?=[^>]*\bplaceholder=)[^>]*>/i', $contents, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as [$tag, $offset]) {
                $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                $unnamedPlaceholderControls[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . $line . ' ' . $tag;
            }
        }

        $this->assertSame([], $unnamedPlaceholderControls, 'Controls that rely on placeholder text still need an id, aria-label, or aria-labelledby.');
    }

    public function test_non_hidden_form_controls_have_accessible_names(): void
    {
        $unnamedControls = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());

            if (str_contains($relativePath, 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR)) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            preg_match_all('/<(input|select|textarea)\b[^>]*>/i', $contents, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as [$tag, $offset]) {
                if (preg_match('/\btype\s*=\s*(["\'])hidden\1/i', $tag)) {
                    continue;
                }

                if (preg_match('/\b(?:id|aria-label|aria-labelledby)\s*=/i', $tag)) {
                    continue;
                }

                $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                $unnamedControls[] = $relativePath . ':' . $line . ' ' . $tag;
            }
        }

        $this->assertSame([], $unnamedControls, 'Every non-hidden form control needs an id, aria-label, or aria-labelledby.');
    }

    public function test_elements_do_not_repeat_aria_label_attributes(): void
    {
        $duplicateAriaLabels = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            preg_match_all('/<[^>\r\n]*\baria-label\s*=\s*(["\'])[^"\']*\1[^>\r\n]*\baria-label\s*=\s*(["\'])[^"\']*\2[^>\r\n]*>/i', $contents, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as [$tag, $offset]) {
                $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                $duplicateAriaLabels[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . $line . ' ' . $tag;
            }
        }

        $this->assertSame([], $duplicateAriaLabels, 'Elements must not contain duplicate aria-label attributes.');
    }

    public function test_blade_views_do_not_repeat_static_id_attributes(): void
    {
        $duplicateIds = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            preg_match_all('/\bid\s*=\s*(["\'])([^"\']+)\1/i', $contents, $matches, PREG_OFFSET_CAPTURE);
            $seen = [];

            foreach ($matches[2] as [$id, $offset]) {
                if (str_contains($id, '{{') || str_contains($id, '$') || str_contains($id, '@')) {
                    continue;
                }

                $seen[$id][] = substr_count(substr($contents, 0, $offset), "\n") + 1;
            }

            foreach ($seen as $id => $lines) {
                if (count($lines) <= 1) {
                    continue;
                }

                $duplicateIds[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ' id="' . $id . '" lines ' . implode(', ', $lines);
            }
        }

        $this->assertSame([], $duplicateIds, 'Blade views must not repeat literal id attributes because labels, modals, tabs, and scripts depend on unique IDs.');
    }

    public function test_static_label_for_attributes_target_existing_ids(): void
    {
        $orphanedLabels = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            preg_match_all('/\bid\s*=\s*(["\'])([^"\']+)\1/i', $contents, $idMatches);
            $ids = [];

            foreach ($idMatches[2] as $id) {
                if (str_contains($id, '{{') || str_contains($id, '$') || str_contains($id, '@')) {
                    continue;
                }

                $ids[$id] = true;
            }

            preg_match_all('/<label\b[^>]*\bfor\s*=\s*(["\'])([^"\']+)\1[^>]*>/i', $contents, $labelMatches, PREG_OFFSET_CAPTURE);

            foreach ($labelMatches[2] as [$target, $offset]) {
                if (str_contains($target, '{{') || str_contains($target, '$') || str_contains($target, '@')) {
                    continue;
                }

                if (isset($ids[$target])) {
                    continue;
                }

                $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                $orphanedLabels[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . $line . ' for="' . $target . '"';
            }
        }

        $this->assertSame([], $orphanedLabels, 'Static label for attributes must point to an existing static id in the same Blade view.');
    }

    public function test_aria_labels_do_not_use_example_placeholder_text(): void
    {
        $placeholderLabels = [];
        $blockedPatterns = [
            '/\baria-label\s*=\s*(["\'])(?:you@example\.com|\+91\s+\d|minimum\s+\d+\s+characters|enter your full name|password confirmation)\1/i',
            '/\baria-label\s*=\s*(["\']).*(?:••|example\.com).*\1/i',
        ];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            foreach ($blockedPatterns as $pattern) {
                preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE);

                foreach ($matches[0] as [$attribute, $offset]) {
                    $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                    $placeholderLabels[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . $line . ' ' . $attribute;
                }
            }
        }

        $this->assertSame([], $placeholderLabels, 'ARIA labels must describe the field or action, not repeat placeholder examples.');
    }

    public function test_student_and_teacher_portal_aria_labels_do_not_copy_placeholder_guidance(): void
    {
        $placeholderLabels = [];
        $blockedPattern = '/\baria-label\s*=\s*(["\'])(?=[^"\']*(?:\.\.\.|e\.g\.|Example:|Optional|Please|What you did|Write your|Provide more|https:\/\/))[^"\']*\1/i';

        foreach ([resource_path('views/student'), resource_path('views/teacher')] as $path) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());
                preg_match_all($blockedPattern, $contents, $matches, PREG_OFFSET_CAPTURE);

                foreach ($matches[0] as [$attribute, $offset]) {
                    $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                    $placeholderLabels[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . $line . ' ' . $attribute;
                }
            }
        }

        $this->assertSame([], $placeholderLabels, 'Student and teacher portal ARIA labels should name the field purpose instead of copying examples or placeholder guidance.');
    }

    public function test_admin_fee_and_admission_queue_aria_labels_do_not_copy_placeholder_guidance(): void
    {
        $placeholderLabels = [];
        $blockedPattern = '/\baria-label\s*=\s*(["\'])(?=[^"\']*(?:\.\.\.|e\.g\.|Example:|Optional|Please|Write your|Provide|https:\/\/))[^"\']*\1/i';
        $paths = [
            resource_path('views/admin/fees'),
            resource_path('views/admission/applicants'),
            resource_path('views/admission/documents'),
            resource_path('views/admission/payments'),
        ];

        foreach ($paths as $path) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());
                preg_match_all($blockedPattern, $contents, $matches, PREG_OFFSET_CAPTURE);

                foreach ($matches[0] as [$attribute, $offset]) {
                    $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                    $placeholderLabels[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . $line . ' ' . $attribute;
                }
            }
        }

        $this->assertSame([], $placeholderLabels, 'Admin fee and admission queue ARIA labels should name the field purpose instead of copying examples or placeholder guidance.');
    }

    public function test_academic_operation_aria_labels_do_not_copy_placeholder_guidance(): void
    {
        $placeholderLabels = [];
        $blockedPattern = '/\baria-label\s*=\s*(["\'])(?=[^"\']*(?:\.\.\.|e\.g\.|Example:|Optional|Please|Write your|Provide|https:\/\/))[^"\']*\1/i';
        $paths = [
            resource_path('views/academic'),
            resource_path('views/academics'),
            resource_path('views/departmental/program-chair'),
        ];

        foreach ($paths as $path) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());
                preg_match_all($blockedPattern, $contents, $matches, PREG_OFFSET_CAPTURE);

                foreach ($matches[0] as [$attribute, $offset]) {
                    $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                    $placeholderLabels[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . $line . ' ' . $attribute;
                }
            }
        }

        $this->assertSame([], $placeholderLabels, 'Academic operation ARIA labels should name the field purpose instead of copying examples or placeholder guidance.');
    }

    public function test_applicant_and_career_aria_labels_do_not_copy_placeholder_guidance(): void
    {
        $placeholderLabels = [];
        $blockedPattern = '/\baria-label\s*=\s*(["\'])(?=[^"\']*(?:\.\.\.|e\.g\.|Example:|Optional|Please|Write your|Provide|https:\/\/))[^"\']*\1/i';
        $paths = [
            resource_path('views/applicant'),
            resource_path('views/departmental/cmc'),
            resource_path('views/departmental/alumni'),
            resource_path('views/departmental/internships'),
        ];

        foreach ($paths as $path) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());
                preg_match_all($blockedPattern, $contents, $matches, PREG_OFFSET_CAPTURE);

                foreach ($matches[0] as [$attribute, $offset]) {
                    $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                    $placeholderLabels[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . $line . ' ' . $attribute;
                }
            }
        }

        $this->assertSame([], $placeholderLabels, 'Applicant and career workflow ARIA labels should name the field purpose instead of copying examples or placeholder guidance.');
    }

    public function test_admin_operation_aria_labels_do_not_copy_placeholder_guidance(): void
    {
        $placeholderLabels = [];
        $blockedPattern = '/\baria-label\s*=\s*(["\'])(?=[^"\']*(?:\.\.\.|e\.g\.|Example:|Optional|Please|Write your|Provide|https:\/\/))[^"\']*\1/i';
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views/admin'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            preg_match_all($blockedPattern, $contents, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as [$attribute, $offset]) {
                $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                $placeholderLabels[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . $line . ' ' . $attribute;
            }
        }

        $this->assertSame([], $placeholderLabels, 'Admin operation ARIA labels should name the field purpose instead of copying examples or placeholder guidance.');
    }

    public function test_aria_labels_do_not_copy_placeholder_guidance_anywhere(): void
    {
        $placeholderLabels = [];
        $blockedPattern = '/\baria-label\s*=\s*(["\'])(?=[^"\']*(?:\.\.\.|e\.g\.|Example:|Optional|Please|Write your|Provide|https:\/\/|placeholder|sample|dummy|test value))[^"\']*\1/i';
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            preg_match_all($blockedPattern, $contents, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as [$attribute, $offset]) {
                $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                $placeholderLabels[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . $line . ' ' . $attribute;
            }
        }

        $this->assertSame([], $placeholderLabels, 'ARIA labels should name the field or action purpose instead of copying examples, placeholders, or helper text.');
    }

    public function test_aria_labels_do_not_use_generated_field_names(): void
    {
        $generatedLabels = [];
        $blockedPatterns = [
            '/\baria-label\s*=\s*(["\'])(?:Input Field|Textarea Field|Select Field|Applicant Ids|Subject Ids|Promotion Ids|Student Ids|Document Ids|Evaluator Ids)\1/i',
            '/\baria-label\s*=\s*(["\'])[^"\']+\s+\d+\1/i',
        ];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            foreach ($blockedPatterns as $pattern) {
                preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE);

                foreach ($matches[0] as [$attribute, $offset]) {
                    $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                    $generatedLabels[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . $line . ' ' . $attribute;
                }
            }
        }

        $this->assertSame([], $generatedLabels, 'ARIA labels must describe the specific field, action, or record instead of generated placeholder names.');
    }

    public function test_blade_views_do_not_contain_mojibake_or_replacement_text(): void
    {
        $encodingIssues = [];
        $blockedBytes = [
            "\xEF\xBF\xBD" => 'replacement character',
            "\xC3\xA2" => 'mojibake a-circumflex',
            "\xC3\x83" => 'mojibake A-tilde',
            "\xC3\x82" => 'mojibake A-circumflex',
        ];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            foreach ($blockedBytes as $bytes => $label) {
                $offset = strpos($contents, $bytes);

                if ($offset === false) {
                    continue;
                }

                $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                $encodingIssues[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . $line . ' ' . $label;
            }
        }

        $this->assertSame([], $encodingIssues, 'Blade views must not contain mojibake or replacement characters in visible UI text.');
    }

    public function test_blade_views_do_not_contain_unfinished_placeholder_copy(): void
    {
        $unfinishedCopy = [];
        $blockedPatterns = [
            '/\bTODO\b/i' => 'TODO marker',
            '/\bFIXME\b/i' => 'FIXME marker',
            '/Lorem ipsum/i' => 'lorem ipsum filler',
            '/under construction/i' => 'under construction copy',
            '/not implemented/i' => 'not implemented copy',
            '/coming soon/i' => 'coming soon copy',
            '/dummy data/i' => 'dummy data copy',
            '/sample data/i' => 'sample data copy',
            '/work in progress/i' => 'work in progress copy',
        ];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            foreach ($blockedPatterns as $pattern => $label) {
                preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE);

                foreach ($matches[0] as [$match, $offset]) {
                    $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                    $unfinishedCopy[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . $line . " {$label} [{$match}]";
                }
            }
        }

        $this->assertSame([], $unfinishedCopy, 'Blade views must not ship unfinished placeholder copy such as TODO markers, filler text, or coming-soon labels.');
    }

    public function test_blade_views_are_valid_utf8_files(): void
    {
        $invalidFiles = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            if (! preg_match('//u', $contents)) {
                $invalidFiles[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertSame([], $invalidFiles, 'Blade views must be saved as valid UTF-8 so visible UI text does not render broken characters.');
    }

    public function test_shared_ui_component_files_exist(): void
    {
        foreach ([
            'page-header',
            'kpi-strip',
            'filter-bar',
            'status-badge',
            'empty-state',
            'data-table',
            'timeline-item',
            'manifest-sidebar',
        ] as $component) {
            $this->assertFileExists(resource_path("views/components/ui/{$component}.blade.php"));
        }
    }

    public function test_manifest_sidebar_component_is_backed_by_frontend_navigation_registry(): void
    {
        $component = file_get_contents(resource_path('views/components/ui/manifest-sidebar.blade.php'));

        $this->assertStringContainsString('FrontendNavigation::manifest()', $component);
        $this->assertStringContainsString('$activePatterns', $component);
        $this->assertStringContainsString('request()->routeIs($pattern)', $component);
        $this->assertStringNotContainsString('href="#"', $component);
    }

    public function test_compact_frontend_design_system_css_is_available(): void
    {
        $css = file_get_contents(public_path('css/app.css'));

        foreach ([
            '.skip-link',
            'a:focus-visible',
            'button:focus-visible',
            '.sidebar .nav-link:focus-visible',
            '.ui-page-header',
            '.ui-kpi-strip',
            '.ui-filter-bar',
            '.ui-data-table',
            '.ui-status-success',
            '.ui-empty-state',
            '.ui-timeline-item',
            'overflow-y: auto;',
        ] as $selector) {
            $this->assertStringContainsString($selector, $css);
        }
    }

    public function test_frontend_npm_scripts_are_registered(): void
    {
        $package = json_decode(file_get_contents(base_path('package.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('vite build', $package['scripts']['frontend:build'] ?? null);
        $this->assertSame('node scripts/frontend-smoke.mjs', $package['scripts']['frontend:smoke'] ?? null);
        $this->assertSame('node scripts/frontend-smoke.mjs --mobile', $package['scripts']['frontend:smoke:mobile'] ?? null);
        $this->assertFileExists(base_path('scripts/frontend-smoke.mjs'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function primaryLayoutProvider(): array
    {
        return [
            'admin' => ['admin'],
            'teacher' => ['teacher'],
            'student' => ['student'],
            'parent' => ['parent'],
            'applicant' => ['applicant'],
        ];
    }

    /**
     * @return array<string, array{email: string, path: string}>
     */
    public static function priorityVisibleLinkPages(): array
    {
        return [
            'admin dashboard' => ['email' => 'admin@college.com', 'path' => '/admin/dashboard'],
            'dean shared profile navigation' => ['email' => 'dean@college.com', 'path' => '/profile'],
            'pmc command' => ['email' => 'chair@college.com', 'path' => '/academics/pmc/command'],
            'pmc official timetable' => ['email' => 'chair@college.com', 'path' => '/academics/pmc/official-timetable'],
            'program chair curriculum' => ['email' => 'chair@college.com', 'path' => '/program-chair/curriculum'],
            'admission reminders' => ['email' => 'admission.manager@college.com', 'path' => '/admission/reminders'],
            'accounts shared profile navigation' => ['email' => 'accounts@college.com', 'path' => '/profile'],
            'exam governance' => ['email' => 'exam@college.com', 'path' => '/academics/governance'],
            'cmc shared profile navigation' => ['email' => 'cmc@college.com', 'path' => '/profile'],
            'teacher attendance' => ['email' => 'ravi@college.com', 'path' => '/teacher/attendance/mark?date=2026-07-14'],
            'student timetable' => ['email' => 'aarav@college.com', 'path' => '/student/timetable'],
            'applicant dashboard' => ['email' => 'priya.sharma@applicant.demo', 'path' => '/applicant/dashboard'],
            'parent dashboard' => ['email' => 'parent@demo.edu', 'path' => '/parent/dashboard'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function sameAppLinks(string $html, string $sourcePath): array
    {
        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();

        $links = [];

        foreach ($document->getElementsByTagName('a') as $anchor) {
            $href = trim((string) $anchor->getAttribute('href'));

            if ($href === '' || $href === '#' || Str::startsWith($href, ['#', 'javascript:', 'mailto:', 'tel:'])) {
                continue;
            }

            if (Str::contains($href, ['/logout', '/download', '/export', '/pdf', '/proof'])) {
                continue;
            }

            $path = parse_url($href, PHP_URL_PATH);
            $query = parse_url($href, PHP_URL_QUERY);

            if (! $path || Str::startsWith($path, ['http://', 'https://']) || ! Str::startsWith($path, '/')) {
                continue;
            }

            $normalized = $path . ($query ? '?' . $query : '');

            if ($normalized === $sourcePath) {
                continue;
            }

            $links[] = $normalized;
        }

        return collect($links)->unique()->values()->all();
    }
}
