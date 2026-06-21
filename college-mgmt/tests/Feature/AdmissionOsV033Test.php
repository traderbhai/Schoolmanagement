<?php

namespace Tests\Feature;

use App\Models\AdmissionCommunicationTemplate;
use App\Models\AdmissionManagerReview;
use App\Models\AdmissionReminderSchedule;
use App\Models\AdmissionWalkIn;
use App\Models\Lead;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionOsV033Test extends TestCase
{
    use RefreshDatabase;

    public function test_v033_operational_tables_are_paginated_filterable_and_seeded(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);
        $head = User::where('email', 'head@college.com')->firstOrFail();

        $this->actingAs($head)
            ->get(route('admission.reminders.index', ['per_page' => 10, 'status' => 'scheduled']))
            ->assertOk()
            ->assertSee('reminders after filters')
            ->assertSee('Showing')
            ->assertDontSee('QueryException');

        $this->actingAs($head)
            ->get(route('admission.walk-ins.index', [
                'per_page' => 10,
                'search' => 'Walk-in Demo',
                'sort' => 'visitor_name',
                'direction' => 'asc',
            ]))
            ->assertOk()
            ->assertSee('visits after filters')
            ->assertSee('Walk-in workflow')
            ->assertSee('Visible filter summary')
            ->assertSee('Sort: visitor name asc')
            ->assertSee('Walk-in Demo')
            ->assertSee('Showing')
            ->assertSee('Conversion Report')
            ->assertSee('sort=visited_at', false)
            ->assertDontSee('N/A', false)
            ->assertDontSee('QueryException');

        $this->actingAs($head)
            ->get(route('admission.walk-ins.index', ['search' => 'no-matching-walk-in-visitor']))
            ->assertOk()
            ->assertSee('No walk-in visits match the current scope or filters.')
            ->assertSee('Clear Filters')
            ->assertSee('No scoped walk-in visits are available for conversion reporting yet.')
            ->assertDontSee('N/A', false)
            ->assertDontSee('QueryException');

        $this->actingAs($head)
            ->get(route('admission.assessment-panels.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('panels after filters')
            ->assertSee('Showing')
            ->assertDontSee('QueryException');

        $this->actingAs($head)
            ->get(route('admission.manager-reviews.index', ['per_page' => 10, 'status' => 'pending']))
            ->assertOk()
            ->assertSee('reviews after filters')
            ->assertSee('Showing')
            ->assertDontSee('QueryException');
    }

    public function test_v033_operational_actions_respect_hierarchy_scope(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);

        $counsellor = User::where('email', 'counsellor@college.com')->firstOrFail();
        $officer = User::where('email', 'officer@college.com')->firstOrFail();
        $manager = User::where('email', 'admission.manager@college.com')->firstOrFail();
        $program = Program::firstOrFail();
        $lead = Lead::factory()->create([
            'program_id' => $program->id,
            'assigned_to' => $officer->id,
            'owner_user_id' => $officer->id,
            'current_handler_user_id' => $officer->id,
        ]);
        $template = AdmissionCommunicationTemplate::where('is_active', true)->firstOrFail();

        $reminder = AdmissionReminderSchedule::create([
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
            'template_id' => $template->id,
            'owner_user_id' => $officer->id,
            'assigned_to' => $officer->id,
            'target' => 'lead',
            'reason' => 'scope_guard_test',
            'channel' => $template->channel,
            'status' => 'scheduled',
            'priority' => 'normal',
            'due_at' => now()->addHour(),
        ]);

        $this->actingAs($counsellor)
            ->post(route('admission.reminders.send', $reminder))
            ->assertForbidden();
        $this->assertSame('scheduled', $reminder->fresh()->status);
        $this->assertSame(0, $reminder->fresh()->attempt_count);

        $review = AdmissionManagerReview::create([
            'reviewable_type' => Lead::class,
            'reviewable_id' => $lead->id,
            'review_type' => 'scope_guard_review',
            'status' => 'pending',
            'assigned_manager_id' => $manager->id,
            'finding' => 'Manager-only review',
        ]);

        $this->actingAs($counsellor)
            ->patch(route('admission.manager-reviews.resolve', $review), ['resolution_notes' => 'Trying to resolve'])
            ->assertForbidden();
        $this->assertSame('pending', $review->fresh()->status);

        $walkIn = AdmissionWalkIn::create([
            'visitor_name' => 'Scoped Walk In',
            'visitor_email' => 'scoped.walkin@example.test',
            'assigned_counsellor_id' => $officer->id,
            'purpose' => 'admission_enquiry',
            'status' => 'open',
            'visited_at' => now(),
        ]);

        $this->actingAs($counsellor)
            ->post(route('admission.walk-ins.convert', $walkIn))
            ->assertForbidden();
        $this->assertNull($walkIn->fresh()->lead_id);
    }
}
