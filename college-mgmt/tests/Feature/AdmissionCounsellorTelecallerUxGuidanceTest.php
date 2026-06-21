<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdmissionCounsellorTelecallerUxGuidanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_counsellor_desk_explains_daily_work_sequence_and_queue_actions(): void
    {
        $counsellor = User::where('email', 'counsellor@college.com')->firstOrFail();

        $this->actingAs($counsellor)
            ->get(route('admission.counsellor-desk.index'))
            ->assertOk()
            ->assertSee('Recommended workflow for today')
            ->assertSee('Start Calling')
            ->assertSee('Resolve applicant blockers')
            ->assertSee('Send due reminders')
            ->assertSee('Open matching work queue')
            ->assertSee('Call top to bottom')
            ->assertSee('Fix before enrollment')
            ->assertSee('Queue messages, do not send manually')
            ->assertSee(route('admission.calling-desk.index'), false)
            ->assertSee(route('admission.reminders.index'), false)
            ->assertSee(route('admission.counsellor-playbooks.index'), false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_calling_desk_explains_call_sequence_and_outcome_form_purpose(): void
    {
        $telecaller = User::where('email', 'telecaller@college.com')->firstOrFail();

        $response = $this->actingAs($telecaller)
            ->get(route('admission.calling-desk.index'));

        $response->assertOk()
            ->assertSee('Call sequence')
            ->assertSee('Review profile and last action')
            ->assertSee('Follow the script checklist')
            ->assertSee('Save disposition/outcome')
            ->assertSee('Set retry or next action')
            ->assertSee('Active Call')
            ->assertSee('Save outcome before moving on')
            ->assertSee('Recommended action')
            ->assertSee(route('admission.counsellor-desk.index'), false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);

        $content = $response->getContent();
        if (str_contains($content, 'No eligible calls in your scope')) {
            $response->assertSee('Your assigned callbacks, retries, hot leads, and parent follow-ups are clear for now.');
        } else {
            $response->assertSee('Disposition')
                ->assertSee('Outcome')
                ->assertSee('Next Action')
                ->assertSee('This updates the timeline, script compliance, retry queue, and next action.');
        }
    }

    public function test_calling_desk_explains_empty_next_call_queue_for_scoped_telecaller(): void
    {
        Role::firstOrCreate(['name' => 'admission_telecaller', 'guard_name' => 'web']);
        $telecaller = User::factory()->create();
        $telecaller->assignRole('admission_telecaller');

        $this->actingAs($telecaller)
            ->get(route('admission.calling-desk.index'))
            ->assertOk()
            ->assertSee('No eligible calls in your scope')
            ->assertSee('Your assigned callbacks, retries, hot leads, and parent follow-ups are clear for now')
            ->assertSee('No eligible next-call records')
            ->assertSee('Your scoped callbacks, no-response retries, hot leads, and parent follow-ups are clear')
            ->assertSee('Open Callback Reminders')
            ->assertSee('Open Leads')
            ->assertSee('No objection trends in this scope')
            ->assertSee('Objection patterns appear after calls are logged with structured objections')
            ->assertDontSee('No objection trends.')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('N/A', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_counsellor_workspace_explains_empty_queues_without_vague_fallbacks(): void
    {
        Role::firstOrCreate(['name' => 'admission_counsellor', 'guard_name' => 'web']);
        $counsellor = User::factory()->create();
        $counsellor->assignRole('admission_counsellor');
        Lead::factory()->create([
            'name' => 'Unmapped Programme Lead',
            'program_id' => null,
            'assigned_to' => $counsellor->id,
            'next_action' => null,
        ]);

        $this->actingAs($counsellor)
            ->get(route('admission.counsellor-workspace.index'))
            ->assertOk()
            ->assertSee('Unmapped Programme Lead')
            ->assertSee('Program not assigned')
            ->assertSee('Next action not set')
            ->assertSee('No assigned applicants in your queue')
            ->assertSee('Applicant records appear here after ownership is assigned')
            ->assertSee('No reminders are due now')
            ->assertSee('Scheduled reminder work is clear for your current scope')
            ->assertDontSee('N/A')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }
}
