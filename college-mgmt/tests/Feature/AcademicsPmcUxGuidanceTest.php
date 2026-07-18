<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcUxGuidanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_pmc_command_explains_daily_operating_sequence(): void
    {
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($chair)
            ->get(route('academics.pmc.command'))
            ->assertOk()
            ->assertSee('PMC daily operating sequence')
            ->assertSee('Check semester readiness')
            ->assertSee('Clear curriculum and subject blockers')
            ->assertSee('Resolve faculty load gaps')
            ->assertSee('Fix timetable conflicts')
            ->assertSee('Close student/delivery actions')
            ->assertSee('Each metric links to the scoped source list')
            ->assertSee('Owner: PMC scope and assigned program teams')
            ->assertSee('Source: Planning, curriculum, faculty load, timetable, delivery, student success')
            ->assertSee('Owner / Source')
            ->assertSee('Source: PMC attention queue')
            ->assertSee('Review')
            ->assertDontSee('Review source record and close blocker with evidence.')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_pmc_v004_surfaces_explain_record_and_readiness_workflow(): void
    {
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($chair)
            ->get(route('academics.pmc.planning.index'))
            ->assertOk()
            ->assertSee('PMC record workflow')
            ->assertSee('Filter scope')
            ->assertSee('Review open/critical/overdue')
            ->assertSee('Create action for blocker')
            ->assertSee('Update owner and evidence')
            ->assertSee('Export current view')
            ->assertSee('Move plans through review only after readiness evidence and blockers are clear')
            ->assertSee('Blockers should become owned work items before publication or Dean escalation')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_pmc_timetable_dashboard_explains_pre_timetable_sequence(): void
    {
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-os.index'))
            ->assertOk()
            ->assertSee('Timetable build sequence')
            ->assertSee('Allocate student course baskets')
            ->assertSee('Build sections and elective groups')
            ->assertSee('Assign faculty to exact groups')
            ->assertSee('Lock fixed slots and rooms')
            ->assertSee('Generate, validate, approve, freeze')
            ->assertSee('Hard conflicts block publish')
            ->assertSee('Owner: PMC with Dean override')
            ->assertSee('Source: Course baskets, groups, faculty allocations, locked slots, generator runs')
            ->assertSee('Open Quality Score source list', false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_pmc_v041_source_surfaces_explain_how_to_fix_records_before_generation(): void
    {
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        foreach ([
            'academics.pmc.course-allocation.index',
            'academics.pmc.student-course-baskets.index',
            'academics.pmc.course-groups.index',
            'academics.pmc.section-faculty-allocation.index',
            'academics.pmc.timetable-generator.index',
            'academics.pmc.timetable-planner.index',
        ] as $routeName) {
            $this->actingAs($chair)
                ->get(route($routeName))
                ->assertOk()
                ->assertSee('PMC timetable source workflow')
                ->assertSee('Filter program/batch/term')
                ->assertSee('Resolve diagnostics')
                ->assertSee('Update source records')
                ->assertSee('Recheck readiness')
                ->assertSee('Export or continue to generator')
                ->assertSee('Fix records here before generating, publishing, freezing, or notifying students and faculty')
                ->assertDontSee('href="#"', false)
                ->assertDontSee('Whoops', false)
                ->assertDontSee('SERVICE ERROR', false)
                ->assertDontSee('Laravel\\', false);
        }
    }

    public function test_pmc_version_lifecycle_actions_have_impact_confirmations_and_accessible_names(): void
    {
        $contents = file_get_contents(resource_path('views/academics/pmc/v041/tables/versions.blade.php'));

        $this->assertStringContainsString('Confirm publish checks, faculty acknowledgement, attendance bridge sync, room readiness, and impact records', $contents);
        $this->assertStringContainsString('Affected faculty, students, rooms, attendance, and bridge rows must be reviewed after reopening.', $contents);
        $this->assertStringContainsString('Confirm the impact preview, official sessions, bridge rows, faculty notices, and student timetable changes before publishing a rollback version.', $contents);
        $this->assertStringContainsString('aria-label="Freeze timetable version', $contents);
        $this->assertStringContainsString('aria-label="Unfreeze timetable version', $contents);
        $this->assertStringContainsString('aria-label="Rollback timetable version', $contents);
    }

    public function test_pmc_timetable_and_reconciliation_actions_use_specific_operational_labels(): void
    {
        $timetable = file_get_contents(resource_path('views/academics/pmc/v041/scoped-timetable.blade.php'));
        $reconciliation = file_get_contents(resource_path('views/academics/pmc/v041/data-reconciliation.blade.php'));
        $generator = file_get_contents(resource_path('views/academics/pmc/v041/tables/generator.blade.php'));
        $reports = file_get_contents(resource_path('views/academics/pmc/v041/tables/reports.blade.php'));
        $launch = file_get_contents(resource_path('views/academics/pmc/v041/launch-wizard.blade.php'));
        $surface = file_get_contents(resource_path('views/academics/pmc/v041/surface.blade.php'));

        $this->assertStringContainsString('Apply timetable filters', $timetable);
        $this->assertStringContainsString('Clear timetable filters', $timetable);
        $this->assertStringNotContainsString('>Filter</button>', $timetable);
        $this->assertStringNotContainsString('>Reset</a>', $timetable);

        foreach ([
            'Apply run filters',
            'Clear run filters',
            'Export run history',
            'Mark stale run failed',
            'Apply audit filters',
            'Clear audit filters',
            'Apply reconciliation filters',
            'Clear reconciliation filters',
            'Refresh reconciliation checks',
            'Repair reconciliation check',
            'Confirm the mismatch sample, canonical timetable source, affected bridge/report rows, and audit trail before applying the repair.',
        ] as $snippet) {
            $this->assertStringContainsString($snippet, $reconciliation);
        }

        $this->assertStringNotContainsString('>Filter</button>', $reconciliation);
        $this->assertStringNotContainsString('>Reset</a>', $reconciliation);
        $this->assertStringNotContainsString('>Repair</button>', $reconciliation);

        foreach ([
            'Validate run',
            'Preview impact',
            'Publish canonical run',
            'Apply alternative D',
            'Move canonical session',
            'Confirm readiness checks, unscheduled sessions, hard conflicts, impact preview, faculty acknowledgement, room readiness, and compatibility bridge sync',
        ] as $snippet) {
            $this->assertStringContainsString($snippet, $generator);
        }

        foreach ([
            'Update notification status',
            'Retry timetable notification',
            'Refresh room readiness',
            'Save room readiness decision',
        ] as $snippet) {
            $this->assertStringContainsString($snippet, $reports);
        }

        $this->assertStringContainsString('Check launch readiness', $launch);
        $this->assertStringContainsString('Clear readiness filters', $launch);
        $this->assertStringContainsString('Apply source filters', $surface);
        $this->assertStringContainsString('Clear source filters', $surface);
        $this->assertStringContainsString('Export current source view', $surface);
        $this->assertStringNotContainsString('>Publish</button>', $generator);
        $this->assertStringNotContainsString('>Impact</button>', $generator);
        $this->assertStringNotContainsString('>Retry</button>', $reports);
        $this->assertStringNotContainsString('>Filter</button>', $surface);
    }
}
