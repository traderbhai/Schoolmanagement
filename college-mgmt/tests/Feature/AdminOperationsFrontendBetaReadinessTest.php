<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOperationsFrontendBetaReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_primary_admin_and_operations_pages_open_without_debug_traces(): void
    {
        $admin = User::where('email', 'admin@demo.edu')->firstOrFail();
        $accounts = User::where('email', 'accounts@college.com')->firstOrFail();
        $cmc = User::where('email', 'cmc@college.com')->firstOrFail();

        $routesByUser = [
            $admin->email => [
                'admin.dashboard',
                'admin.search',
                'admin.analytics',
                'admin.students.index',
                'admin.teachers.index',
                'admin.roles.permissions.index',
                'admin.settings',
                'admin.api-docs',
                'admin.fees.collect',
                'admin.library.index',
                'admin.library.books',
                'admin.hostel.index',
                'admin.hostel.allocations',
                'admin.transport.index',
                'admin.assets.index',
            ],
            $accounts->email => [
                'accounts.dashboard',
                'accounts.fee-collections',
                'accounts.outstanding',
                'accounts.reconciliation',
                'accounts.reports',
            ],
            $cmc->email => [
                'cmc.dashboard',
                'cmc.drives',
                'cmc.companies',
                'cmc.events',
                'cmc.analytics',
            ],
        ];

        foreach ($routesByUser as $email => $routes) {
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

    public function test_admin_and_operations_dashboards_expose_real_workflow_links(): void
    {
        $admin = User::where('email', 'admin@demo.edu')->firstOrFail();
        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.students.index'), false)
            ->assertSee(route('admin.fees.collect'), false)
            ->assertSee(route('admin.audit.index'), false)
            ->assertDontSee('href="#"', false);

        $this->actingAs($admin)
            ->get(route('admin.library.index'))
            ->assertOk()
            ->assertSee(route('admin.library.books'), false)
            ->assertSee(route('admin.library.issues'), false)
            ->assertSee(route('admin.library.reservations'), false)
            ->assertDontSee('href="#"', false);

        $this->actingAs($admin)
            ->get(route('admin.hostel.index'))
            ->assertOk()
            ->assertSee(route('admin.hostel.allocations'), false)
            ->assertSee(route('admin.hostel.fees'), false)
            ->assertSee(route('admin.hostel.complaints'), false)
            ->assertDontSee('href="#"', false);

        $accounts = User::where('email', 'accounts@college.com')->firstOrFail();
        $this->actingAs($accounts)
            ->get(route('accounts.dashboard'))
            ->assertOk()
            ->assertSee(route('accounts.admission-payments'), false)
            ->assertSee(route('accounts.outstanding'), false)
            ->assertSee(route('accounts.reports'), false)
            ->assertDontSee('href="#"', false);

        $cmc = User::where('email', 'cmc@college.com')->firstOrFail();
        $this->actingAs($cmc)
            ->get(route('cmc.dashboard'))
            ->assertOk()
            ->assertSee(route('cmc.drives'), false)
            ->assertSee(route('cmc.events.create'), false)
            ->assertSee(route('cmc.analytics'), false)
            ->assertDontSee('href="#"', false);
    }

    public function test_accounts_and_cmc_branches_use_manifest_grouped_sidebar_links(): void
    {
        $accounts = User::where('email', 'accounts@college.com')->firstOrFail();
        $this->actingAs($accounts)
            ->get(route('accounts.dashboard'))
            ->assertOk()
            ->assertSee('Command')
            ->assertSee('Finance')
            ->assertSee('Dashboard')
            ->assertSee('Fee Collections')
            ->assertSee('Admission Payments')
            ->assertSee('Outstanding')
            ->assertSee('Reconciliation')
            ->assertSee('Reports')
            ->assertSee(route('accounts.dashboard'), false)
            ->assertSee(route('accounts.fee-collections'), false)
            ->assertSee(route('accounts.admission-payments'), false)
            ->assertSee(route('accounts.reconciliation'), false)
            ->assertDontSee('href="#"', false);

        $cmc = User::where('email', 'cmc@college.com')->firstOrFail();
        $this->actingAs($cmc)
            ->get(route('cmc.dashboard'))
            ->assertOk()
            ->assertSee('Command')
            ->assertSee('Placement')
            ->assertSee('Students')
            ->assertSee('Reports')
            ->assertSee('Dashboard')
            ->assertSee('Placement Drives')
            ->assertSee('Companies')
            ->assertSee('Career Events')
            ->assertSee('Placement Stats')
            ->assertSee('Internships')
            ->assertSee('Alumni Database')
            ->assertSee('Analytics')
            ->assertSee(route('cmc.dashboard'), false)
            ->assertSee(route('cmc.drives'), false)
            ->assertSee(route('cmc.companies'), false)
            ->assertSee(route('cmc.events'), false)
            ->assertSee(route('cmc.analytics'), false)
            ->assertDontSee('href="#"', false);

        $layout = file_get_contents(resource_path('views/layouts/admin.blade.php'));

        $this->assertStringContainsString('<x-ui.manifest-sidebar role="accounts"', $layout);
        $this->assertStringContainsString('<x-ui.manifest-sidebar role="cmc"', $layout);
    }

    public function test_admin_branch_uses_manifest_grouped_sidebar_links(): void
    {
        $admin = User::where('email', 'admin@demo.edu')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Command')
            ->assertSee('Governance')
            ->assertSee('Timetable')
            ->assertSee('Admission')
            ->assertSee('Assessments')
            ->assertSee('Academics / Delivery')
            ->assertSee('Finance')
            ->assertSee('Operations')
            ->assertSee('Settings')
            ->assertSee('Academic Years')
            ->assertSee('Weekly Timetable')
            ->assertSee('Student Documents')
            ->assertSee('Command Center')
            ->assertSee('Assessment Panels')
            ->assertSee('Applicants CRM')
            ->assertSee('All Leads')
            ->assertSee('Communication Hub')
            ->assertSee('Academics Command')
            ->assertSee('Exams &amp; Results', false)
            ->assertSee('Scholarship Schemes')
            ->assertSee('Role Hierarchy')
            ->assertSee('System Settings')
            ->assertSee('Library')
            ->assertSee(route('admin.academic-years.index'), false)
            ->assertSee(route('admission.command-center.index'), false)
            ->assertSee(route('admission.assessment-panels.index'), false)
            ->assertSee(route('academics.command-center.index'), false)
            ->assertSee(route('admin.roles.hierarchy'), false)
            ->assertDontSee('href="#"', false);

        $layout = file_get_contents(resource_path('views/layouts/admin.blade.php'));

        $this->assertStringContainsString('<x-ui.manifest-sidebar role="admin"', $layout);
        $this->assertSame(2, substr_count($layout, 'role="admin" brand-sub="Admin Portal"'));
        $this->assertStringNotContainsString('Academic Setup</div>', $layout);
    }

    public function test_primary_admin_and_operations_views_do_not_contain_placeholder_or_framework_trace_text(): void
    {
        $viewPaths = [
            resource_path('views/admin/dashboard.blade.php'),
            resource_path('views/admin/settings/index.blade.php'),
            resource_path('views/admin/settings/api-docs.blade.php'),
            resource_path('views/admin/roles/permissions/index.blade.php'),
            resource_path('views/admin/fees/collect.blade.php'),
            resource_path('views/admin/library/index.blade.php'),
            resource_path('views/admin/library/books.blade.php'),
            resource_path('views/admin/hostel/index.blade.php'),
            resource_path('views/admin/hostel/allocations.blade.php'),
            resource_path('views/admin/transport/index.blade.php'),
            resource_path('views/admin/assets/index.blade.php'),
            resource_path('views/departmental/accounts/dashboard.blade.php'),
            resource_path('views/departmental/accounts/fee-collections.blade.php'),
            resource_path('views/departmental/cmc/dashboard.blade.php'),
            resource_path('views/departmental/cmc/drives.blade.php'),
        ];

        foreach ($viewPaths as $path) {
            $contents = file_get_contents($path);

            $this->assertStringNotContainsString('href="#"', $contents, $path);
            $this->assertStringNotContainsString("href='#'", $contents, $path);
            $this->assertStringNotContainsString('javascript:void', $contents, $path);
            $this->assertStringNotContainsString('â', $contents, $path);
            $this->assertStringNotContainsString('Ã', $contents, $path);
            $this->assertStringNotContainsString('Â', $contents, $path);
            $this->assertStringNotContainsString('</form><form', $contents, $path);
            $this->assertStringNotContainsString('Ã‚', $contents, $path);
            $this->assertStringNotContainsString('Whoops', $contents, $path);
            $this->assertStringNotContainsString('Stack trace', $contents, $path);
            $this->assertStringNotContainsString('Laravel', $contents, $path);
        }
    }
}
