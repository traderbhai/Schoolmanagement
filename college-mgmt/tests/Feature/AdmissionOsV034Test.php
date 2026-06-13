<?php

namespace Tests\Feature;

use App\Models\AdmissionCallLog;
use App\Models\AdmissionReminderSchedule;
use App\Models\Applicant;
use App\Models\Lead;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionOsV034Test extends TestCase
{
    use RefreshDatabase;

    public function test_v034_lead_and_applicant_detail_pages_show_action_centers(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);

        $head = User::where('email', 'head@college.com')->firstOrFail();
        $lead = Lead::where('status', '!=', 'converted')->firstOrFail();
        $applicant = Applicant::whereNotNull('assigned_to')->firstOrFail();

        $this->actingAs($head)
            ->get(route('admission.leads.show', $lead))
            ->assertOk()
            ->assertSee('Lead Action Center')
            ->assertSee('Needs attention')
            ->assertSee('Quick commands')
            ->assertSee('Recent operating activity')
            ->assertSee('Schedule tomorrow reminder')
            ->assertDontSee('QueryException');

        $this->actingAs($head)
            ->get(route('admission.applicants.show', $applicant))
            ->assertOk()
            ->assertSee('Applicant Action Center')
            ->assertSee('Needs attention')
            ->assertSee('Quick commands')
            ->assertSee('Recent operating activity')
            ->assertSee('Schedule reminder')
            ->assertDontSee('QueryException');
    }

    public function test_v034_next_action_commands_create_operational_records(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);

        $head = User::where('email', 'head@college.com')->firstOrFail();
        $program = Program::firstOrFail();
        $lead = Lead::factory()->create([
            'program_id' => $program->id,
            'status' => 'new',
            'assigned_to' => $head->id,
            'owner_user_id' => $head->id,
            'current_handler_user_id' => $head->id,
            'phone' => '9999990000',
        ]);

        $this->actingAs($head)
            ->post(route('admission.reminders.store'), [
                'subject_type' => 'lead',
                'subject_id' => $lead->id,
                'reason' => 'no_response_follow_up',
                'channel' => 'email',
                'priority' => 'high',
                'due_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'notes' => 'v0.034 action center reminder',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('admission_reminder_schedules', [
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
            'reason' => 'no_response_follow_up',
            'notes' => 'v0.034 action center reminder',
        ]);

        $this->actingAs($head)
            ->post(route('admission.call-queue.log'), [
                'subject_type' => 'lead',
                'subject_id' => $lead->id,
                'phone' => '9999990000',
                'disposition' => 'connected',
                'duration_seconds' => 180,
                'notes' => 'v0.034 action center call',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('admission_call_logs', [
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
            'caller_user_id' => $head->id,
            'disposition' => 'connected',
            'notes' => 'v0.034 action center call',
        ]);

        $this->assertSame(1, AdmissionReminderSchedule::where('subject_id', $lead->id)->where('subject_type', Lead::class)->where('notes', 'v0.034 action center reminder')->count());
        $this->assertSame(1, AdmissionCallLog::where('subject_id', $lead->id)->where('subject_type', Lead::class)->where('notes', 'v0.034 action center call')->count());
    }
}
