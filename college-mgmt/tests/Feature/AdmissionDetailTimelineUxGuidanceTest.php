<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Lead;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionDetailTimelineUxGuidanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_lead_detail_explains_operating_sequence(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();
        $lead = Lead::firstOrFail();

        $this->actingAs($head)
            ->get(route('admission.leads.show', $lead))
            ->assertOk()
            ->assertSee('Lead operating sequence')
            ->assertSee('Confirm ownership')
            ->assertSee('Check source, program, and priority')
            ->assertSee('Log call or follow-up')
            ->assertSee('Resolve quality flags')
            ->assertSee('Convert only when ready')
            ->assertSee('Ownership decides who must follow up')
            ->assertSee('Read communications, calls, and data-quality flags together')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_applicant_detail_explains_review_sequence(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();
        $applicant = Applicant::firstOrFail();

        $this->actingAs($head)
            ->get(route('admission.applicants.show', $applicant))
            ->assertOk()
            ->assertSee('Applicant review sequence')
            ->assertSee('Check action center blockers')
            ->assertSee('Verify application profile')
            ->assertSee('Clear documents and payments')
            ->assertSee('Log counselling and notes')
            ->assertSee('Move status only when ready')
            ->assertSee('Application tab:')
            ->assertSee('Counselling tab:')
            ->assertSee('Read communications, calls, journey version, and quality flags together')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_applicant_detail_empty_profile_sections_explain_next_staff_action(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();
        $applicantUser = User::factory()->create(['name' => 'Empty Profile Applicant']);
        $program = Program::firstOrFail();

        $applicant = Applicant::factory()->create([
            'user_id' => $applicantUser->id,
            'program_id' => $program->id,
            'personal_data' => [],
            'academic_data' => [],
            'family_data' => [],
            'additional_data' => [],
        ]);

        $this->actingAs($head)
            ->get(route('admission.applicants.show', $applicant))
            ->assertOk()
            ->assertSeeText('Personal Details not submitted yet')
            ->assertSeeText('Ask the applicant to complete profile basics')
            ->assertSeeText('Academic Details not submitted yet')
            ->assertSeeText('Collect qualification, institution, score, and entrance details')
            ->assertSeeText('Family Details not submitted yet')
            ->assertSeeText('Capture parent or guardian decision-maker details')
            ->assertSeeText('Additional Info not submitted yet')
            ->assertSeeText('capture hostel, transport, scholarship, objection, or special-support needs')
            ->assertSeeText('No applicant documents are uploaded yet')
            ->assertSeeText('Ask the applicant to upload mandatory documents from the checklist')
            ->assertSeeText('No counselling interactions are logged yet')
            ->assertSeeText("Log the first call, email, WhatsApp, or walk-in outcome")
            ->assertSeeText('No internal team notes are recorded yet')
            ->assertSeeText('Add a concise staff-only note for exceptions')
            ->assertSeeText('Select Scheme')
            ->assertSeeText('Award Amount (Rs.)')
            ->assertSee('placeholder="Optional notes"', false)
            ->assertDontSee('N/A', false)
            ->assertDontSee('â', false)
            ->assertDontSee('Â', false)
            ->assertDontSee('ð', false)
            ->assertDontSee('₹', false)
            ->assertDontSeeText('No data submitted.')
            ->assertDontSeeText('No documents uploaded yet.')
            ->assertDontSeeText('No interactions logged yet.')
            ->assertDontSeeText('No internal notes yet.')
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Whoops', false);
    }

    public function test_conversation_timeline_explains_event_reading_order(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();
        $applicant = Applicant::firstOrFail();

        $this->actingAs($head)
            ->get(route('admission.conversation-timeline.show', ['applicant', $applicant->id]))
            ->assertOk()
            ->assertSee('Timeline reading order')
            ->assertSee('Calls')
            ->assertSee('Counselling notes')
            ->assertSee('Reminders')
            ->assertSee('Messages')
            ->assertSee('Assessment/payment/document events')
            ->assertSee('Use the latest event plus open blockers')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }
}
