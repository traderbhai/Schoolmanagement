<?php

namespace Tests\Feature;

use App\Models\AcademicDeanOperatingRecord;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsDeanFrontendBetaReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_primary_dean_operating_surfaces_open_without_debug_traces(): void
    {
        $dean = User::where('email', 'dean@college.com')->firstOrFail();

        foreach ([
            'academics.dean-os.index',
            'academics.dean-os.planning.index',
            'academics.dean-os.approval-cockpit.index',
            'academics.dean-os.faculty-workload.index',
            'academics.dean-os.student-success.index',
            'academics.dean-os.exam-readiness.index',
            'academics.dean-os.quality-command.index',
            'academics.dean-os.analytics.index',
        ] as $routeName) {
            $this->actingAs($dean)
                ->get(route($routeName))
                ->assertOk()
                ->assertDontSee('SERVICE ERROR')
                ->assertDontSee('Whoops')
                ->assertDontSee('Laravel')
                ->assertSee('<title', false);
        }
    }

    public function test_admin_all_scope_dean_os_dashboard_opens_without_service_error(): void
    {
        $admin = User::where('email', 'admin@demo.edu')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('academics.dean-os.index'))
            ->assertOk()
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('Call to a member function getKey', false)
            ->assertSee('Dean Academics Command OS');
    }

    public function test_dean_operating_surface_filters_sort_and_exports_current_view(): void
    {
        $dean = User::where('email', 'dean@college.com')->firstOrFail();
        $program = Program::where('is_active', true)->firstOrFail();

        AcademicDeanOperatingRecord::create([
            'record_type' => 'faculty_workload',
            'title' => 'ZZZ Critical Beta Faculty Overload',
            'program_id' => $program->id,
            'status' => 'open',
            'severity' => 'critical',
            'score' => 95,
            'due_at' => now()->subDay(),
            'source_type' => 'faculty_load',
            'source_key' => 'BETA-CRITICAL',
        ]);

        AcademicDeanOperatingRecord::create([
            'record_type' => 'faculty_workload',
            'title' => 'AAA Closed Beta Faculty Note',
            'program_id' => $program->id,
            'status' => 'closed',
            'severity' => 'low',
            'score' => 10,
            'due_at' => now()->addWeek(),
            'source_type' => 'faculty_load',
            'source_key' => 'BETA-CLOSED',
        ]);

        $response = $this->actingAs($dean)->get(route('academics.dean-os.faculty-workload.index', [
            'search' => 'Beta Faculty',
            'severity' => 'critical',
            'status' => 'open',
            'sort' => 'score',
            'direction' => 'desc',
        ]));

        $response
            ->assertOk()
            ->assertSee('ZZZ Critical Beta Faculty Overload')
            ->assertDontSee('AAA Closed Beta Faculty Note')
            ->assertSee('Visible filter summary')
            ->assertSee('Search: Beta Faculty')
            ->assertSee('Severity: critical')
            ->assertSee(e(route('academics.dean-os.export', [
                'report' => 'faculty_workload',
                'search' => 'Beta Faculty',
                'severity' => 'critical',
                'status' => 'open',
                'sort' => 'score',
                'direction' => 'desc',
            ])), false);
    }

    public function test_dean_shared_shell_uses_manifest_grouped_sidebar_links(): void
    {
        $dean = User::where('email', 'dean@college.com')->firstOrFail();

        $this->actingAs($dean)
            ->get(route('academics.dean-os.index'))
            ->assertOk()
            ->assertSee('Command')
            ->assertSee('Governance')
            ->assertSee('Operations')
            ->assertSee('Students')
            ->assertSee('Curriculum')
            ->assertSee('Exams')
            ->assertSee('Approvals')
            ->assertSee('Reports')
            ->assertSee('Dean OS')
            ->assertSee('Legacy Dashboard')
            ->assertSee('Academics Command')
            ->assertSee('Academics Governance')
            ->assertSee('PMC Operating')
            ->assertSee('CoE Operating')
            ->assertSee('IQAC Operating')
            ->assertSee('Academics Overview')
            ->assertSee('Curriculum Changes')
            ->assertSee('OBE Framework')
            ->assertSee('Hall Tickets')
            ->assertSee('Dean Reports')
            ->assertSee(route('academics.dean-os.index'), false)
            ->assertSee(route('dean.dashboard'), false)
            ->assertSee(route('academics.command-center.index'), false)
            ->assertSee(route('academics.governance.index'), false)
            ->assertSee(route('academics.pmc.index'), false)
            ->assertSee(route('academics.coe.index'), false)
            ->assertSee(route('dean.students'), false)
            ->assertSee(route('academic.curriculum-changes.index'), false)
            ->assertSee(route('exam-cell.hall-tickets'), false)
            ->assertDontSee('href="#"', false);

        $layout = file_get_contents(resource_path('views/layouts/admin.blade.php'));

        $this->assertStringContainsString('<x-ui.manifest-sidebar role="dean"', $layout);
    }

    public function test_primary_dean_views_do_not_have_placeholder_or_broken_action_links(): void
    {
        foreach ([
            'academics/dean-os/dashboard.blade.php',
            'academics/dean-os/v008/operating-surface.blade.php',
            'academics/dean-os/v008/planning.blade.php',
            'academics/dean-os/v008/approvals.blade.php',
            'academics/dean-os/v008/analytics.blade.php',
        ] as $view) {
            $contents = file_get_contents(resource_path("views/{$view}"));

            $this->assertStringNotContainsString('href="#"', $contents, "{$view} contains a placeholder action link.");
            $this->assertStringNotContainsString("href='#'", $contents, "{$view} contains a placeholder action link.");
            $this->assertStringNotContainsString('Â', $contents, "{$view} contains mojibake output.");
            $this->assertStringNotContainsString('</form><form', $contents, "{$view} contains adjacent forms without stable layout markup.");
        }
    }
}
