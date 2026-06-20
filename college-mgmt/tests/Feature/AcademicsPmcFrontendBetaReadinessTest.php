<?php

namespace Tests\Feature;

use App\Models\AcademicPmcOperatingRecord;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcFrontendBetaReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_primary_pmc_operating_surfaces_open_without_debug_traces(): void
    {
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        foreach ([
            'academics.pmc.command',
            'academics.pmc.planning.index',
            'academics.pmc.curriculum-governance.index',
            'academics.pmc.faculty-allocation-v004.index',
            'academics.pmc.timetable-governance.index',
            'academics.pmc.course-delivery.index',
            'academics.pmc.student-success-v004.index',
            'academics.pmc.approvals.index',
            'academics.pmc.analytics.index',
            'academics.pmc.course-allocation.index',
            'academics.pmc.course-groups.index',
            'academics.pmc.timetable-planner.index',
        ] as $routeName) {
            $this->actingAs($chair)
                ->get(route($routeName))
                ->assertOk()
                ->assertDontSee('SERVICE ERROR')
                ->assertDontSee('Whoops')
                ->assertDontSee('Laravel')
                ->assertSee('<title', false);
        }
    }

    public function test_pmc_command_and_surfaces_have_source_linked_filters_and_exports(): void
    {
        $chair = User::where('email', 'chair@college.com')->firstOrFail();
        $program = Program::where('is_active', true)->firstOrFail();

        AcademicPmcOperatingRecord::create([
            'record_type' => 'faculty_allocation',
            'title' => 'ZZZ Beta PMC Critical Faculty Load',
            'description' => 'Beta PMC source-backed filter test.',
            'program_id' => $program->id,
            'status' => 'open',
            'category' => 'beta_category',
            'risk_band' => 'critical',
            'score' => 91,
            'due_at' => now()->subDay(),
            'created_by' => $chair->id,
            'owner_user_id' => $chair->id,
        ]);

        AcademicPmcOperatingRecord::create([
            'record_type' => 'faculty_allocation',
            'title' => 'AAA Beta PMC Closed Faculty Load',
            'description' => 'Should be filtered out.',
            'program_id' => $program->id,
            'status' => 'closed',
            'category' => 'beta_category',
            'risk_band' => 'low',
            'score' => 5,
            'due_at' => now()->addWeek(),
            'created_by' => $chair->id,
            'owner_user_id' => $chair->id,
        ]);

        $this->actingAs($chair)
            ->get(route('academics.pmc.command'))
            ->assertOk()
            ->assertSee(route('academics.pmc.faculty-allocation-v004.index', ['risk_band' => 'high']), false)
            ->assertSee(route('academics.pmc.action-governance.index', ['due' => 'overdue']), false);

        $response = $this->actingAs($chair)->get(route('academics.pmc.faculty-allocation-v004.index', [
            'search' => 'Beta PMC',
            'risk_band' => 'critical',
            'status' => 'open',
            'due' => 'overdue',
        ]));

        $response
            ->assertOk()
            ->assertSee('ZZZ Beta PMC Critical Faculty Load')
            ->assertDontSee('AAA Beta PMC Closed Faculty Load')
            ->assertSee('Visible filter summary')
            ->assertSee('Search: Beta PMC')
            ->assertSee('Due: overdue')
            ->assertSee(e(route('academics.pmc.export', [
                'report' => 'faculty-allocation-v004',
                'search' => 'Beta PMC',
                'risk_band' => 'critical',
                'status' => 'open',
                'due' => 'overdue',
            ])), false);
    }

    public function test_primary_pmc_views_do_not_have_placeholder_or_broken_action_links(): void
    {
        foreach ([
            'academics/pmc/v004/command.blade.php',
            'academics/pmc/v004/surface.blade.php',
            'academics/pmc/v004/approvals.blade.php',
            'academics/pmc/v041/dashboard.blade.php',
            'academics/pmc/v041/surface.blade.php',
        ] as $view) {
            $contents = file_get_contents(resource_path("views/{$view}"));

            $this->assertStringNotContainsString('href="#"', $contents, "{$view} contains a placeholder action link.");
            $this->assertStringNotContainsString("href='#'", $contents, "{$view} contains a placeholder action link.");
            $this->assertStringNotContainsString('Â', $contents, "{$view} contains mojibake output.");
            $this->assertStringNotContainsString('</form><form', $contents, "{$view} contains adjacent forms without stable layout markup.");
        }
    }

    public function test_program_chair_shared_shell_uses_manifest_grouped_sidebar_links(): void
    {
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $response = $this->actingAs($chair)->get(route('academics.pmc.command'));

        $response->assertOk()
            ->assertSee('PMC Command')
            ->assertSee('PMC Workspace')
            ->assertSee('Academics Governance')
            ->assertSee('Curriculum Governance')
            ->assertSee('Timetable Builder')
            ->assertSee('Student Success')
            ->assertSee('Faculty Allocation')
            ->assertSee('Subject Performance')
            ->assertSee(route('academics.workspaces.show', 'pmc'), false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Whoops', false);
    }
}
