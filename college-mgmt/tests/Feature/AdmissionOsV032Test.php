<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionOsV032Test extends TestCase
{
    use RefreshDatabase;

    public function test_admission_dashboard_and_lists_are_compact_clickable_and_query_backed(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);

        $head = User::where('email', 'head@college.com')->firstOrFail();
        $submitted = Applicant::where('status', 'submitted')->firstOrFail();
        $lead = Lead::whereNotNull('assigned_to')->firstOrFail();

        $this->actingAs($head)
            ->get(route('admission.dashboard'))
            ->assertOk()
            ->assertSee(route('admission.applicants.index', ['status' => 'submitted']), false)
            ->assertSee(route('admission.leads.index'), false)
            ->assertDontSee('QueryException')
            ->assertDontSee('LARAVEL');

        $this->actingAs($head)
            ->get(route('admission.applicants.index', [
                'status' => $submitted->status,
                'sort' => 'application_number',
                'direction' => 'asc',
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertSee($submitted->application_number)
            ->assertSee('records after filters')
            ->assertSee('App #')
            ->assertDontSee('QueryException');

        $this->actingAs($head)
            ->get(route('admission.leads.index', [
                'search' => $lead->email,
                'sort' => 'name',
                'direction' => 'asc',
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertSee($lead->name)
            ->assertSee('records after filters')
            ->assertSee('Export')
            ->assertDontSee('QueryException');
    }
}
