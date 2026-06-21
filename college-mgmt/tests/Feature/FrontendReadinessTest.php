<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\FrontendNavigation;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_primary_layouts_do_not_use_placeholder_action_links(): void
    {
        foreach (self::primaryLayoutProvider() as [$layout]) {
            $contents = file_get_contents(resource_path("views/layouts/{$layout}.blade.php"));

            $this->assertStringNotContainsString('href="#"', $contents, "{$layout} layout contains a placeholder href.");
            $this->assertStringNotContainsString("href='#'", $contents, "{$layout} layout contains a placeholder href.");
        }
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
}
