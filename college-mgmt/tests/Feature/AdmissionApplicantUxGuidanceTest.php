<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionApplicantUxGuidanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_applicant_dashboard_explains_next_action_and_links_core_workflows(): void
    {
        $applicant = User::where('email', 'priya.sharma@applicant.demo')->firstOrFail();

        $response = $this->actingAs($applicant)
            ->get(route('applicant.dashboard'))
            ->assertOk()
            ->assertSee('Next required action')
            ->assertSee('Owner:')
            ->assertSee('Your action')
            ->assertSee('Admission team')
            ->assertSee('Your Admission Tasks')
            ->assertSee('Admission Journey')
            ->assertSee('Follow this path in order')
            ->assertSee('Quick Links')
            ->assertSee('Application Summary')
            ->assertSee(route('applicant.checklist'), false)
            ->assertSee(route('applicant.status'), false)
            ->assertSee(route('applicant.documents.index'), false)
            ->assertSee(route('applicant.fees.index'), false)
            ->assertSee(route('applicant.admission-operations.index'), false)
            ->assertSee(route('applicant.offer-letters.index'), false)
            ->assertSee(route('applicant.notifications.edit'), false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);

        $this->assertApplicantPageHasNoMojibakeSeparators($response->getContent());
    }

    public function test_applicant_checklist_uses_operational_progress_and_blocker_language(): void
    {
        $applicant = User::where('email', 'priya.sharma@applicant.demo')->firstOrFail();

        $response = $this->actingAs($applicant)
            ->get(route('applicant.checklist'))
            ->assertOk()
            ->assertSee('Checklist progress')
            ->assertSee('Open blockers')
            ->assertSee('Focus first')
            ->assertSee('Owner:')
            ->assertSee('Your action')
            ->assertSee('Admission team')
            ->assertSee('Readiness Details')
            ->assertSee('Blocker / confirmation')
            ->assertSee('Resolve')
            ->assertSee(route('applicant.dashboard'), false)
            ->assertSee(route('applicant.status'), false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);

        $this->assertApplicantPageHasNoMojibakeSeparators($response->getContent());
    }

    public function test_applicant_dashboard_uses_readable_batch_fallback(): void
    {
        $program = Program::where('is_active', true)->firstOrFail();
        $user = User::factory()->create();
        $user->assignRole('applicant');

        Applicant::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'batch_id' => null,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user)
            ->get(route('applicant.dashboard'))
            ->assertOk()
            ->assertSee('Batch not assigned yet')
            ->assertDontSee('<dd class="col-7">-</dd>', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);

        $this->assertApplicantPageHasNoMojibakeSeparators($response->getContent());
    }

    public function test_applicant_status_remains_a_next_step_tracker(): void
    {
        $applicant = User::where('email', 'priya.sharma@applicant.demo')->firstOrFail();

        $this->actingAs($applicant)
            ->get(route('applicant.status'))
            ->assertOk()
            ->assertSee('Application Timeline')
            ->assertSee('Application Started')
            ->assertSee('Current Status')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_admission_operations_explains_missing_assessment_and_seat_records(): void
    {
        $program = Program::where('is_active', true)->firstOrFail();
        $user = User::factory()->create();
        $user->assignRole('applicant');

        Applicant::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($user)
            ->get(route('applicant.admission-operations.index'))
            ->assertOk()
            ->assertSee('No assessment slot has been assigned yet')
            ->assertSee('Admission staff will publish your interview, GD, case, WAT, or other assessment slot')
            ->assertSee(route('applicant.checklist'), false)
            ->assertSee(route('applicant.status'), false)
            ->assertSee('No seat or waitlist record yet')
            ->assertSee('Seat holds and waitlist ranks appear only after selection, offer-round, or seat-control decisions')
            ->assertSee(route('applicant.offer-letters.index'), false)
            ->assertDontSee('No assessment slot assigned yet.')
            ->assertDontSee('No seat or waitlist record yet.</div>', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);

        $this->assertApplicantPageHasNoMojibakeSeparators($response->getContent());
    }

    public function test_offer_letters_empty_state_explains_offer_round_and_next_steps(): void
    {
        $program = Program::where('is_active', true)->firstOrFail();
        $user = User::factory()->create();
        $user->assignRole('applicant');

        Applicant::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'status' => 'selected',
        ]);

        $this->actingAs($user)
            ->get(route('applicant.offer-letters.index'))
            ->assertOk()
            ->assertSee('No offer letters are available yet')
            ->assertSee('selection or waitlist movement')
            ->assertSee('publishes the offer round')
            ->assertSee('creates the seat-hold deadline')
            ->assertSee('status tracker for the published offer')
            ->assertSee(route('applicant.status'), false)
            ->assertSee(route('applicant.checklist'), false)
            ->assertSee(route('applicant.admission-operations.index'), false)
            ->assertSee(route('applicant.fees.index'), false)
            ->assertDontSee('You will see your offer letters here once the admission team has issued them.')
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_documents_page_explains_when_no_requirements_are_published(): void
    {
        $program = Program::factory()->create([
            'name' => 'BBA Digital Business',
            'is_active' => true,
        ]);
        $user = User::factory()->create();
        $user->assignRole('applicant');

        Applicant::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'status' => 'submitted',
        ]);

        $this->actingAs($user)
            ->get(route('applicant.documents.index'))
            ->assertOk()
            ->assertSee('0/0 Uploaded')
            ->assertSee('No document requirements are published yet')
            ->assertSee('Admission staff have not published the required document checklist')
            ->assertSee('upload format, size limit, verification status, and any rejection reason')
            ->assertSee(route('applicant.checklist'), false)
            ->assertSee(route('applicant.status'), false)
            ->assertSee(route('applicant.dashboard'), false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_application_form_select_placeholder_is_readable(): void
    {
        $program = Program::where('is_active', true)->firstOrFail();
        $user = User::factory()->create();
        $user->assignRole('applicant');

        Applicant::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user)
            ->get(route('applicant.application.show'))
            ->assertOk()
            ->assertSee('Application Progress')
            ->assertSee('Select an option')
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
        $this->assertApplicantPageHasNoMojibakeSeparators($response->getContent());
    }

    private function assertApplicantPageHasNoMojibakeSeparators(string $content): void
    {
        $this->assertStringNotContainsString("\xC3\x82", $content);
        $this->assertStringNotContainsString("\xC3\xA2", $content);
    }
}
