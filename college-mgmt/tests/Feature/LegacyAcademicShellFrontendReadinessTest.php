<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyAcademicShellFrontendReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_hod_shared_shell_uses_manifest_grouped_sidebar_links(): void
    {
        $hod = User::where('email', 'hod@college.com')->firstOrFail();

        $response = $this->actingAs($hod)->get(route('hod.dashboard'));

        $response->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Faculty Roster')
            ->assertSee('Faculty Workload')
            ->assertSee('Leave Approvals')
            ->assertSee('Dept Performance')
            ->assertSee('Student Grievances')
            ->assertSee('Approvals')
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Whoops', false);
    }

    public function test_director_shared_shell_uses_manifest_grouped_sidebar_links(): void
    {
        $director = User::where('email', 'director@college.com')->firstOrFail();

        $response = $this->actingAs($director)->get(route('director.dashboard'));

        $response->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Programs')
            ->assertSee('Reports')
            ->assertSee('Analytics')
            ->assertSee('Institutional KPI')
            ->assertSee('AICTE Report')
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Whoops', false);
    }
}
