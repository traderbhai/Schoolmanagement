<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionFrontendBetaReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_primary_admission_operating_surfaces_open_without_debug_traces(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();

        foreach ([
            'admission.dashboard',
            'admission.command-center.index',
            'admission.calling-desk.index',
            'admission.counsellor-desk.index',
            'admission.assessment-control-room.index',
            'admission.offer-rounds.index',
        ] as $routeName) {
            $response = $this->actingAs($head)->get(route($routeName));

            $response
                ->assertOk()
                ->assertDontSee('SERVICE ERROR')
                ->assertDontSee('Whoops')
                ->assertDontSee('Laravel')
                ->assertSee('<title', false);
        }
    }

    public function test_admission_daily_work_metrics_link_to_source_lists(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();

        $this->actingAs($head)
            ->get(route('admission.command-center.index'))
            ->assertOk()
            ->assertSee(route('admission.applicants.index'), false)
            ->assertSee(route('admission.attention.index'), false)
            ->assertSee(route('admission.calling-desk.index'), false)
            ->assertSee(route('admission.forecasting.index'), false);

        $this->actingAs($head)
            ->get(route('admission.counsellor-desk.index'))
            ->assertOk()
            ->assertSee(route('admission.calling-desk.index'), false)
            ->assertSee(route('admission.applicants.index', ['status' => 'under_review']), false)
            ->assertSee(route('admission.assessment-control-room.index'), false)
            ->assertSee(route('admission.reminders.index'), false);

        $this->actingAs($head)
            ->get(route('admission.calling-desk.index'))
            ->assertOk()
            ->assertSee(route('admission.counsellor-performance.index'), false)
            ->assertSee(route('admission.reminders.index', ['reason' => 'callback_retry']), false)
            ->assertSee(route('admission.parent-journeys.index'), false);
    }

    public function test_primary_admission_views_do_not_have_broken_action_markup(): void
    {
        foreach ([
            'admission/v003/command-center.blade.php',
            'admission/v0036/counsellor-desk.blade.php',
            'admission/v0036/assessment-control-room.blade.php',
            'admission/v0038/calling-desk.blade.php',
            'admission/v0038/offer-seat-control.blade.php',
        ] as $view) {
            $contents = file_get_contents(resource_path("views/{$view}"));

            $this->assertStringNotContainsString('href="#"', $contents, "{$view} contains a placeholder action link.");
            $this->assertStringNotContainsString("href='#'", $contents, "{$view} contains a placeholder action link.");
            $this->assertStringNotContainsString('Â', $contents, "{$view} contains mojibake output.");
            $this->assertStringNotContainsString('</form><form', $contents, "{$view} contains adjacent forms without stable layout markup.");
        }
    }

    public function test_admission_shared_shell_uses_manifest_grouped_sidebar_links(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();

        $response = $this->actingAs($head)->get(route('admission.dashboard'));

        $response->assertOk()
            ->assertSee('Command Center')
            ->assertSee('Calling Desk')
            ->assertSee('Document Queue')
            ->assertSee('Assessment Scheduling')
            ->assertSee('Merit List')
            ->assertSee('Offer Letters')
            ->assertSee('Seat Control')
            ->assertSee('All Leads')
            ->assertSee('Consent &amp; Safety', false)
            ->assertSee('Department Controls')
            ->assertSee('Department Hierarchy')
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Whoops', false);
    }
}
