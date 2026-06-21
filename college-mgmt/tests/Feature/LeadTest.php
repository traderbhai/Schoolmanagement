<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Applicant;
use App\Models\Batch;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admission_officer', 'guard_name' => 'web']);
    }

    public function test_lead_can_be_created(): void
    {
        $lead = Lead::create([
            'name'   => 'John Doe',
            'email'  => 'john@example.com',
            'phone'  => '1234567890',
            'source' => 'web_form',
            'status' => 'new',
        ]);

        $this->assertDatabaseHas('leads', [
            'email' => 'john@example.com',
            'status' => 'new',
        ]);
    }

    public function test_lead_status_transitions(): void
    {
        $lead = Lead::factory()->create(['status' => 'new']);

        $this->assertTrue($lead->isPending === null); // Not a method, just checking model works
        $this->assertEquals('new', $lead->status);

        $lead->markContacted();
        $this->assertEquals('contacted', $lead->status);
        $this->assertNotNull($lead->last_contacted_at);

        $lead->markInterested();
        $this->assertEquals('interested', $lead->status);

        $lead->markNotInterested();
        $this->assertEquals('not_interested', $lead->status);
    }

    public function test_lead_conversion(): void
    {
        $lead = Lead::factory()->create(['status' => 'interested']);
        $program = Program::factory()->create();

        $applicantUser = User::factory()->create();
        $applicant = \App\Models\Applicant::factory()->create([
            'user_id' => $applicantUser->id,
            'program_id' => $program->id,
        ]);

        $lead->convertToApplicant($applicant);

        $this->assertTrue($lead->isConverted());
        $this->assertEquals('converted', $lead->status);
        $this->assertEquals($applicant->id, $lead->converted_applicant_id);
        $this->assertNotNull($lead->converted_at);
    }

    public function test_lead_conversion_route_stores_readable_source_note(): void
    {
        Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);

        $officer = User::factory()->create();
        $officer->assignRole('admission_officer');

        $lead = Lead::factory()->create([
            'name' => 'Readable Lead',
            'email' => 'readable-lead@example.com',
            'phone' => '9999999999',
            'status' => 'interested',
            'source' => 'web_form',
        ]);
        $program = Program::factory()->create();

        $this->actingAs($officer)->post(route('admission.leads.convert', $lead), [
            'program_id' => $program->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('applicants', [
            'program_id' => $program->id,
            'notes' => 'Converted from lead Readable Lead - readable-lead@example.com',
        ]);
        $this->assertDatabaseMissing('applicants', [
            'notes' => 'Converted from Lead #'.$lead->id.' (web_form)',
        ]);
    }

    public function test_lead_conversion_route_requires_active_program_and_matching_open_batch(): void
    {
        Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);

        $officer = User::factory()->create();
        $officer->assignRole('admission_officer');

        $activeProgram = Program::factory()->create(['is_active' => true]);
        $inactiveProgram = Program::factory()->create(['is_active' => false]);
        $validBatch = Batch::factory()->create(['program_id' => $activeProgram->id, 'status' => 'active']);
        $wrongProgramBatch = Batch::factory()->create(['status' => 'active']);
        $closedBatch = Batch::factory()->create(['program_id' => $activeProgram->id, 'status' => 'completed']);

        $inactiveLead = Lead::factory()->create(['status' => 'interested', 'email' => 'inactive-convert@example.test']);
        $wrongBatchLead = Lead::factory()->create(['status' => 'interested', 'email' => 'wrong-batch-convert@example.test']);
        $closedBatchLead = Lead::factory()->create(['status' => 'interested', 'email' => 'closed-batch-convert@example.test']);
        $validLead = Lead::factory()->create(['status' => 'interested', 'email' => 'valid-convert@example.test']);

        $this->actingAs($officer)
            ->from(route('admission.leads.show', $inactiveLead))
            ->post(route('admission.leads.convert', $inactiveLead), [
                'program_id' => $inactiveProgram->id,
                'batch_id' => null,
            ])
            ->assertRedirect(route('admission.leads.show', $inactiveLead))
            ->assertSessionHasErrors('program_id');

        $this->actingAs($officer)
            ->from(route('admission.leads.show', $wrongBatchLead))
            ->post(route('admission.leads.convert', $wrongBatchLead), [
                'program_id' => $activeProgram->id,
                'batch_id' => $wrongProgramBatch->id,
            ])
            ->assertRedirect(route('admission.leads.show', $wrongBatchLead))
            ->assertSessionHasErrors('batch_id');

        $this->actingAs($officer)
            ->from(route('admission.leads.show', $closedBatchLead))
            ->post(route('admission.leads.convert', $closedBatchLead), [
                'program_id' => $activeProgram->id,
                'batch_id' => $closedBatch->id,
            ])
            ->assertRedirect(route('admission.leads.show', $closedBatchLead))
            ->assertSessionHasErrors('batch_id');

        $this->actingAs($officer)
            ->post(route('admission.leads.convert', $validLead), [
                'program_id' => $activeProgram->id,
                'batch_id' => $validBatch->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('applicants', [
            'program_id' => $activeProgram->id,
            'batch_id' => $validBatch->id,
            'status' => 'draft',
        ]);
        $this->assertSame(1, Applicant::count());
        $this->assertTrue($validLead->fresh()->isConverted());
        $this->assertFalse($inactiveLead->fresh()->isConverted());
        $this->assertFalse($wrongBatchLead->fresh()->isConverted());
        $this->assertFalse($closedBatchLead->fresh()->isConverted());
    }

    public function test_officer_can_view_leads(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('admission_officer');

        Lead::factory()->create(['status' => 'new', 'name' => 'New Visible Lead']);
        Lead::factory()->create(['status' => 'contacted', 'name' => 'Hidden Contacted Lead']);
        Lead::factory()->create(['status' => 'converted']);

        $response = $this->actingAs($officer)->get(route('admission.leads.index'));
        $response->assertOk()
            ->assertSee('Leads & Enquiries')
            ->assertSee('3 records after filters')
            ->assertSee('Analytics')
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Whoops', false);
    }

    public function test_officer_can_filter_leads_by_status(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('admission_officer');

        Lead::factory()->create(['status' => 'new', 'name' => 'New Visible Lead']);
        Lead::factory()->create(['status' => 'contacted', 'name' => 'Hidden Contacted Lead']);

        $response = $this->actingAs($officer)->get(route('admission.leads.index', ['status' => 'new']));
        $response->assertOk()
            ->assertSee('Status: New')
            ->assertSee('1 records after filters')
            ->assertSee('New Visible Lead')
            ->assertDontSee('Hidden Contacted Lead')
            ->assertDontSee('SERVICE ERROR', false);
    }

    public function test_officer_can_filter_leads_by_source(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('admission_officer');

        Lead::factory()->create(['source' => 'web_form', 'name' => 'Web Source Visible']);
        Lead::factory()->create(['source' => 'referral', 'name' => 'Referral Source Hidden']);

        $response = $this->actingAs($officer)->get(route('admission.leads.index', ['source' => 'web_form']));
        $response->assertOk()
            ->assertSee('Source: Web form')
            ->assertSee('1 records after filters')
            ->assertSee('Web Source Visible')
            ->assertDontSee('Referral Source Hidden')
            ->assertDontSee('SERVICE ERROR', false);
    }

    public function test_officer_can_mark_lead_contacted(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('admission_officer');

        $lead = Lead::factory()->create(['status' => 'new']);

        $response = $this->actingAs($officer)->post(route('admission.leads.contact', $lead), [
            'notes' => 'Called the lead',
        ]);

        $lead->refresh();
        $this->assertEquals('contacted', $lead->status);
        $this->assertNotNull($lead->last_contacted_at);
    }

    public function test_officer_can_mark_lead_interested(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('admission_officer');

        $lead = Lead::factory()->create(['status' => 'contacted']);

        $response = $this->actingAs($officer)->post(route('admission.leads.interested', $lead), [
            'notes' => 'Lead showed interest',
        ]);

        $lead->refresh();
        $this->assertEquals('interested', $lead->status);
    }

    public function test_officer_can_mark_lead_not_interested(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('admission_officer');

        $lead = Lead::factory()->create(['status' => 'interested']);

        $response = $this->actingAs($officer)->post(route('admission.leads.not-interested', $lead), [
            'notes' => 'Lead declined',
        ]);

        $lead->refresh();
        $this->assertEquals('not_interested', $lead->status);
    }

    public function test_lead_analytics_view(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('admission_officer');

        Lead::factory(10)->create(['status' => 'new']);
        Lead::factory(5)->create(['status' => 'contacted']);
        Lead::factory(2)->create(['status' => 'converted']);

        $response = $this->actingAs($officer)->get(route('admission.leads.analytics'));
        $response->assertOk()
            ->assertSee('Lead Analytics')
            ->assertSee('Total Leads')
            ->assertSee('17')
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Whoops', false);
    }

    public function test_lead_analytics_empty_state_explains_missing_source_data(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('admission_officer');

        $this->actingAs($officer)
            ->get(route('admission.leads.analytics'))
            ->assertOk()
            ->assertSee('No lead source data is available yet')
            ->assertSee('Capture or import leads with a source to compare channel performance')
            ->assertSee('No program-linked leads are available yet')
            ->assertSee('Link leads to programs to compare program demand and conversion')
            ->assertSee('No lead activity has been captured in the last 30 days')
            ->assertDontSee('No data')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('SERVICE ERROR', false);
    }

    public function test_lead_source_labels(): void
    {
        $webLead = Lead::factory()->create(['source' => 'web_form']);
        $this->assertEquals('Web Form', $webLead->source_label);

        $refLead = Lead::factory()->create(['source' => 'referral']);
        $this->assertEquals('Referral', $refLead->source_label);

        $advLead = Lead::factory()->create(['source' => 'advertisement']);
        $this->assertEquals('Advertisement', $advLead->source_label);
    }
}
