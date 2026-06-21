<?php

namespace Tests\Feature;

use App\Mail\OfferLetterMail;
use App\Models\Applicant;
use App\Models\Batch;
use App\Models\Department;
use App\Models\DepartmentMember;
use App\Models\DepartmentRole;
use App\Models\MeritListEntry;
use App\Models\OfferLetter;
use App\Models\Program;
use App\Models\SeatMatrix;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OfferLetterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admission_officer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);
    }

    public function test_admission_officer_can_view_offer_letters_index(): void
    {
        $program = Program::factory()->create();
        $officer = User::factory()->create(['password' => Hash::make('password')]);
        $officer->assignRole('admission_officer');

        $response = $this->actingAs($officer)->get(route('admission.offer-letters.index', $program));
        $response->assertOk()
            ->assertSee('Offer Letters')
            ->assertSee($program->name)
            ->assertSee('Generate Offer Letters')
            ->assertSee('Total Offers')
            ->assertSee('No offer letters match the current program or filters.')
            ->assertSee('Generate offers from selected merit-list applicants')
            ->assertSee('Open Merit List')
            ->assertDontSee('No offer letters found.')
            ->assertDontSee('Offer Letters —', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Whoops', false);
    }

    public function test_staff_offer_letter_detail_uses_readable_missing_data_labels(): void
    {
        $officer = User::factory()->create(['password' => Hash::make('password')]);
        $officer->assignRole('admission_officer');
        $this->actingAs($officer);
        View::share('errors', new ViewErrorBag);

        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $applicant = Applicant::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'selected',
        ]);
        $offer = OfferLetter::create([
            'applicant_id' => $applicant->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'issued',
            'acceptance_deadline' => now()->addDays(7)->toDateString(),
            'issued_by' => null,
            'issued_at' => now(),
        ]);

        $offer->load(['applicant.user', 'program', 'batch', 'issuedBy']);
        $offer->applicant->setRelation('user', null);
        $offer->applicant->application_number = null;
        $offer->applicant->applied_at = null;
        $offer->issued_at = null;
        $offer->acceptance_deadline = null;

        $html = view('admission.offer-letters.show', [
            'offerLetter' => $offer,
            'locked' => false,
        ])->render();

        $this->assertStringContainsString($program->name, $html);
        $this->assertStringContainsString($batch->name, $html);
        $this->assertStringContainsString('Issuing staff not recorded', $html);
        $this->assertStringContainsString('Issue time not recorded', $html);
        $this->assertStringContainsString('Applicant name not recorded', $html);
        $this->assertStringContainsString('Email not recorded', $html);
        $this->assertStringContainsString('Application number pending', $html);
        $this->assertStringContainsString('Application date not recorded', $html);
        $this->assertStringContainsString('Acceptance deadline not published', $html);
        $this->assertStringNotContainsString('N/A', $html);
        $this->assertStringNotContainsString('â', $html);
        $this->assertStringNotContainsString('Ã', $html);
        $this->assertStringNotContainsString('Â', $html);
    }

    public function test_non_officer_cannot_access_offer_letters(): void
    {
        $program = Program::factory()->create();
        $applicant = User::factory()->create(['password' => Hash::make('password')]);
        $applicant->assignRole('applicant');

        $response = $this->actingAs($applicant)->get(route('admission.offer-letters.index', $program));
        $this->assertTrue(in_array($response->getStatusCode(), [403, 302]));
    }

    public function test_admission_officer_can_generate_offer_letters(): void
    {
        Mail::fake();

        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $officer = User::factory()->create(['password' => Hash::make('password')]);
        $officer->assignRole('admission_officer');

        $applicantUser = User::factory()->create();
        $applicant = Applicant::factory()->create([
            'user_id' => $applicantUser->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'shortlisted'
        ]);

        MeritListEntry::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'applicant_id' => $applicant->id,
            'rank' => 1,
            'total_weighted_score' => 85.5,
            'composite_score' => 85.5,
            'decision' => 'selected',
            'decided_by' => $officer->id,
            'decided_at' => now(),
        ]);

        $response = $this->actingAs($officer)->post(route('admission.offer-letters.bulk-generate', $program), [
            'acceptance_days' => 14,
            'batch_id' => $batch->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('offer_letters', [
            'applicant_id' => $applicant->id,
            'program_id' => $program->id,
            'status' => 'issued',
        ]);

        $offer = OfferLetter::where('applicant_id', $applicant->id)->first();
        $this->assertNotNull($offer);
        $this->assertEquals('issued', $offer->status);
        $this->assertTrue($offer->acceptance_deadline->isFuture());
    }

    public function test_staff_offer_generation_skips_final_state_applicants(): void
    {
        Mail::fake();

        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $officer = User::factory()->create(['password' => Hash::make('password')]);
        $officer->assignRole('admission_officer');

        $withdrawn = Applicant::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'withdrawn',
        ]);
        $enrolled = Applicant::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'enrolled',
        ]);

        foreach ([[$withdrawn, 1], [$enrolled, 2]] as [$applicant, $rank]) {
            MeritListEntry::create([
                'program_id' => $program->id,
                'batch_id' => $batch->id,
                'applicant_id' => $applicant->id,
                'rank' => $rank,
                'total_weighted_score' => 90 - $rank,
                'composite_score' => 90 - $rank,
                'decision' => 'selected',
                'decided_by' => $officer->id,
                'decided_at' => now(),
            ]);
        }

        $this->actingAs($officer)
            ->post(route('admission.offer-letters.bulk-generate', $program), [
                'acceptance_days' => 14,
                'batch_id' => $batch->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Generated 0 offer letter(s).');

        $this->assertDatabaseMissing('offer_letters', ['applicant_id' => $withdrawn->id]);
        $this->assertDatabaseMissing('offer_letters', ['applicant_id' => $enrolled->id]);
    }

    public function test_staff_direct_merit_offer_generation_skips_final_state_applicant_ids(): void
    {
        Mail::fake();

        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id, 'status' => 'active']);
        $officer = User::factory()->create();
        $officer->assignRole('admission_officer');
        $rejected = Applicant::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'rejected',
        ]);

        $this->actingAs($officer)
            ->post(route('admission.admission.offer-letters.bulk-generate'), [
                'program_id' => $program->id,
                'applicant_ids' => [$rejected->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Offer letters generated: 0. Skipped (already exist): 1.');

        $this->assertDatabaseMissing('offer_letters', ['applicant_id' => $rejected->id]);
    }

    public function test_applicant_can_accept_offer(): void
    {
        $applicantUser = User::factory()->create();
        $applicantUser->assignRole('applicant');

        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);

        $applicant = Applicant::factory()->create([
            'user_id' => $applicantUser->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
        ]);

        $offer = OfferLetter::create([
            'applicant_id' => $applicant->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'issued',
            'acceptance_deadline' => now()->addDays(14),
            'issued_by' => User::factory()->create()->id,
        ]);

        $response = $this->actingAs($applicantUser)->post(route('applicant.offer-letters.accept', $offer));

        $response->assertStatus(200);
        $this->assertDatabaseHas('offer_letters', [
            'id' => $offer->id,
            'status' => 'accepted',
        ]);
    }

    public function test_applicant_cannot_accept_expired_offer(): void
    {
        $applicantUser = User::factory()->create();
        $applicantUser->assignRole('applicant');

        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);

        $applicant = Applicant::factory()->create([
            'user_id' => $applicantUser->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
        ]);

        $offer = OfferLetter::create([
            'applicant_id' => $applicant->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'issued',
            'acceptance_deadline' => now()->subDays(1),
            'issued_by' => User::factory()->create()->id,
        ]);

        $response = $this->actingAs($applicantUser)->post(route('applicant.offer-letters.accept', $offer));

        $response->assertStatus(400);
    }

    public function test_applicant_can_decline_offer(): void
    {
        $applicantUser = User::factory()->create();
        $applicantUser->assignRole('applicant');

        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);

        $applicant = Applicant::factory()->create([
            'user_id' => $applicantUser->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'selected'
        ]);

        $officer = User::factory()->create();
        $officer->assignRole('admission_officer');

        $offer = OfferLetter::create([
            'applicant_id' => $applicant->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'issued',
            'acceptance_deadline' => now()->addDays(14),
            'issued_by' => $officer->id,
        ]);

        $response = $this->actingAs($applicantUser)->post(route('applicant.offer-letters.decline', $offer), [
            'reason' => 'Chose another program',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('offer_letters', [
            'id' => $offer->id,
            'status' => 'declined',
            'declined_reason' => 'Chose another program',
        ]);
    }

    public function test_applicant_cannot_accept_stale_offer_after_final_admission_status(): void
    {
        $applicantUser = User::factory()->create();
        $applicantUser->assignRole('applicant');

        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);

        $applicant = Applicant::factory()->create([
            'user_id' => $applicantUser->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'withdrawn',
        ]);

        $offer = OfferLetter::create([
            'applicant_id' => $applicant->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'issued',
            'acceptance_deadline' => now()->addDays(14),
            'issued_by' => User::factory()->create()->id,
        ]);

        $response = $this->actingAs($applicantUser)->post(route('applicant.offer-letters.accept', $offer));

        $response->assertStatus(400);
        $this->assertSame('issued', $offer->fresh()->status);
        $this->assertSame('withdrawn', $applicant->fresh()->status);
        $response->assertJson(['error' => 'This applicant is in a final admission state and the offer cannot be changed.']);
    }

    public function test_staff_cannot_decline_stale_offer_after_final_admission_status(): void
    {
        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $officer = User::factory()->create();
        $officer->assignRole('admission_officer');
        $applicant = Applicant::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'rejected',
        ]);

        $offer = OfferLetter::create([
            'applicant_id' => $applicant->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'issued',
            'acceptance_deadline' => now()->addDays(14),
            'issued_by' => $officer->id,
        ]);

        $this->actingAs($officer)
            ->post(route('admission.offer-letters.decline', $offer), [
                'reason' => 'Trying stale staff decline.',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'This applicant is in a final admission state and the offer cannot be changed.');

        $this->assertSame('issued', $offer->fresh()->status);
        $this->assertSame('rejected', $applicant->fresh()->status);
    }

    public function test_stale_pending_offer_pages_hide_actions_for_final_state_applicants(): void
    {
        $applicantUser = User::factory()->create();
        $applicantUser->assignRole('applicant');
        $officer = User::factory()->create();
        $officer->assignRole('admission_officer');

        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $applicant = Applicant::factory()->create([
            'user_id' => $applicantUser->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'enrolled',
        ]);

        $offer = OfferLetter::create([
            'applicant_id' => $applicant->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'issued',
            'acceptance_deadline' => now()->addDays(14),
            'issued_by' => $officer->id,
        ]);

        $this->actingAs($applicantUser)
            ->get(route('applicant.offer-letters.show', $offer))
            ->assertOk()
            ->assertSee('Offer Response Closed')
            ->assertDontSee('Accept Offer')
            ->assertDontSee('Decline Offer')
            ->assertDontSee('Yes, Accept')
            ->assertDontSee('Yes, Decline');

        $this->actingAs($officer)
            ->get(route('admission.offer-letters.show', $offer))
            ->assertOk()
            ->assertSee('Offer actions locked.')
            ->assertDontSee('Mark as Accepted')
            ->assertDontSee('Mark as Declined');
    }

    public function test_offer_letter_promotion_on_decline(): void
    {
        Mail::fake();

        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        SeatMatrix::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'total_seats' => 1,
            'general_seats' => 1,
            'obc_seats' => 0,
            'sc_seats' => 0,
            'st_seats' => 0,
            'ews_seats' => 0,
            'management_quota' => 0,
            'nri_quota' => 0,
            'defence_quota' => 0,
        ]);

        // Create selected applicant with offer
        $selectedUser = User::factory()->create();
        $selectedUser->assignRole('applicant');
        $selected = Applicant::factory()->create([
            'user_id' => $selectedUser->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'selected'
        ]);

        // Create waitlisted applicant
        $waitlistedUser = User::factory()->create();
        $waitlistedUser->assignRole('applicant');
        $waitlisted = Applicant::factory()->create([
            'user_id' => $waitlistedUser->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
        ]);

        $officer = User::factory()->create();
        $officer->assignRole('admission_officer');

        // Create merit list entries
        MeritListEntry::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'applicant_id' => $selected->id,
            'rank' => 1,
            'total_weighted_score' => 90,
            'composite_score' => 90,
            'decision' => 'selected',
            'decided_by' => $officer->id,
            'decided_at' => now(),
        ]);

        MeritListEntry::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'applicant_id' => $waitlisted->id,
            'rank' => 2,
            'total_weighted_score' => 88,
            'composite_score' => 88,
            'decision' => 'waitlisted',
            'decided_by' => $officer->id,
            'decided_at' => now(),
        ]);

        $offer = OfferLetter::create([
            'applicant_id' => $selected->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'issued',
            'acceptance_deadline' => now()->addDays(14),
            'issued_by' => $officer->id,
        ]);

        // Decline the offer
        $this->actingAs($selectedUser)->post(route('applicant.offer-letters.decline', $offer), [
            'reason' => 'Going elsewhere'
        ]);

        // Check that waitlisted applicant was promoted to selected
        $promoted = MeritListEntry::where('applicant_id', $waitlisted->id)->first();
        $this->assertEquals('selected', $promoted->decision);

        // Check that new offer was generated for promoted applicant
        $newOffer = OfferLetter::where('applicant_id', $waitlisted->id)->first();
        $this->assertNotNull($newOffer);
        $this->assertEquals('issued', $newOffer->status);
    }

    public function test_offer_decline_does_not_auto_promote_without_seat_matrix(): void
    {
        Mail::fake();

        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $selectedUser = User::factory()->create();
        $selectedUser->assignRole('applicant');
        $selected = Applicant::factory()->create([
            'user_id' => $selectedUser->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'category' => 'general',
            'status' => 'selected',
        ]);
        $waitlisted = Applicant::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'category' => 'general',
            'status' => 'shortlisted',
        ]);
        $officer = User::factory()->create();
        $officer->assignRole('admission_officer');

        MeritListEntry::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'applicant_id' => $selected->id,
            'rank' => 1,
            'total_weighted_score' => 90,
            'composite_score' => 90,
            'decision' => 'selected',
            'category' => 'general',
            'decided_by' => $officer->id,
            'decided_at' => now(),
        ]);
        $entry = MeritListEntry::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'applicant_id' => $waitlisted->id,
            'rank' => 2,
            'total_weighted_score' => 88,
            'composite_score' => 88,
            'decision' => 'waitlisted',
            'category' => 'general',
            'decided_by' => $officer->id,
            'decided_at' => now(),
        ]);
        $offer = OfferLetter::create([
            'applicant_id' => $selected->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'issued',
            'acceptance_deadline' => now()->addDays(14),
            'issued_by' => $officer->id,
        ]);

        $this->actingAs($selectedUser)->post(route('applicant.offer-letters.decline', $offer), [
            'reason' => 'Going elsewhere',
        ])->assertOk();

        $this->assertSame('waitlisted', $entry->fresh()->decision);
        $this->assertDatabaseMissing('offer_letters', ['applicant_id' => $waitlisted->id]);
    }

    public function test_staff_offer_decline_does_not_auto_promote_when_waitlist_category_is_full(): void
    {
        Mail::fake();

        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        SeatMatrix::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'total_seats' => 2,
            'general_seats' => 1,
            'obc_seats' => 1,
            'sc_seats' => 0,
            'st_seats' => 0,
            'ews_seats' => 0,
            'management_quota' => 0,
            'nri_quota' => 0,
            'defence_quota' => 0,
        ]);
        $officer = User::factory()->create();
        $officer->assignRole('admission_officer');
        $generalSelected = Applicant::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'category' => 'general',
            'status' => 'selected',
        ]);
        $obcCommitted = Applicant::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'category' => 'obc',
            'status' => 'selected',
        ]);
        $obcWaitlisted = Applicant::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'category' => 'obc',
            'status' => 'shortlisted',
        ]);

        foreach ([[$generalSelected, 1, 'general', 'selected'], [$obcCommitted, 2, 'obc', 'selected']] as [$applicant, $rank, $category, $decision]) {
            MeritListEntry::create([
                'program_id' => $program->id,
                'batch_id' => $batch->id,
                'applicant_id' => $applicant->id,
                'rank' => $rank,
                'total_weighted_score' => 95 - $rank,
                'composite_score' => 95 - $rank,
                'decision' => $decision,
                'category' => $category,
                'decided_by' => $officer->id,
                'decided_at' => now(),
            ]);
        }

        $entry = MeritListEntry::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'applicant_id' => $obcWaitlisted->id,
            'rank' => 3,
            'total_weighted_score' => 88,
            'composite_score' => 88,
            'decision' => 'waitlisted',
            'category' => 'obc',
            'decided_by' => $officer->id,
            'decided_at' => now(),
        ]);
        $offer = OfferLetter::create([
            'applicant_id' => $generalSelected->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'issued',
            'acceptance_deadline' => now()->addDays(14),
            'issued_by' => $officer->id,
        ]);

        $this->actingAs($officer)
            ->post(route('admission.offer-letters.decline', $offer), ['reason' => 'Seat released'])
            ->assertRedirect();

        $this->assertSame('waitlisted', $entry->fresh()->decision);
        $this->assertDatabaseMissing('offer_letters', ['applicant_id' => $obcWaitlisted->id]);
    }

    public function test_applicant_can_view_own_offer_letters(): void
    {
        $applicantUser = User::factory()->create();
        $applicantUser->assignRole('applicant');

        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);

        $applicant = Applicant::factory()->create([
            'user_id' => $applicantUser->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
        ]);

        $offer = OfferLetter::create([
            'applicant_id' => $applicant->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'issued',
            'acceptance_deadline' => now()->addDays(14),
            'issued_by' => User::factory()->create()->id,
        ]);

        $response = $this->actingAs($applicantUser)->get(route('applicant.offer-letters.index'));
        $response->assertOk()
            ->assertSee('My Offer Letters')
            ->assertSee($program->name)
            ->assertSee('Offer Number')
            ->assertSee('Download PDF')
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Whoops', false);
    }

    public function test_applicant_offer_detail_uses_plain_text_response_guidance(): void
    {
        $applicantUser = User::factory()->create();
        $applicantUser->assignRole('applicant');

        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);

        $applicant = Applicant::factory()->create([
            'user_id' => $applicantUser->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'selected',
        ]);

        $offer = OfferLetter::create([
            'applicant_id' => $applicant->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'issued',
            'acceptance_deadline' => now()->addDays(14),
            'issued_by' => User::factory()->create()->id,
        ]);

        $pendingResponse = $this->actingAs($applicantUser)
            ->get(route('applicant.offer-letters.show', $offer))
            ->assertOk()
            ->assertSee('Accept Offer')
            ->assertSee('Decline Offer')
            ->assertSee('Important:')
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false);
        $this->assertStringNotContainsString("\u{2713}", $pendingResponse->getContent());
        $this->assertStringNotContainsString("\u{2717}", $pendingResponse->getContent());
        $this->assertStringNotContainsString("\u{26A0}", $pendingResponse->getContent());
        $this->assertStringNotContainsString("\xC3\xA2", $pendingResponse->getContent());

        $offer->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        $acceptedResponse = $this->actingAs($applicantUser)
            ->get(route('applicant.offer-letters.show', $offer))
            ->assertOk()
            ->assertSee('Offer Accepted');
        $this->assertStringNotContainsString("\u{2713}", $acceptedResponse->getContent());
        $this->assertStringNotContainsString("\xC3\xA2", $acceptedResponse->getContent());
    }

    public function test_offer_letter_pdf_download(): void
    {
        $applicantUser = User::factory()->create();
        $applicantUser->assignRole('applicant');

        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);

        $applicant = Applicant::factory()->create([
            'user_id' => $applicantUser->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
        ]);

        $offer = OfferLetter::create([
            'applicant_id' => $applicant->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'issued',
            'acceptance_deadline' => now()->addDays(14),
            'issued_by' => User::factory()->create()->id,
        ]);

        $response = $this->actingAs($applicantUser)->get(route('applicant.offer-letters.pdf', $offer));
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }

    public function test_admin_can_download_offer_letter_pdf(): void
    {
        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);

        $applicantUser = User::factory()->create();
        $applicant = Applicant::factory()->create([
            'user_id' => $applicantUser->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
        ]);

        $officer = User::factory()->create();
        $officer->assignRole('admission_officer');

        $offer = OfferLetter::create([
            'applicant_id' => $applicant->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'issued',
            'acceptance_deadline' => now()->addDays(14),
            'issued_by' => $officer->id,
        ]);

        $response = $this->actingAs($officer)->get(route('admission.offer-letters.export', $offer));
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }

    public function test_staff_offer_letters_respect_assignment_scope(): void
    {
        Mail::fake();
        Role::firstOrCreate(['name' => 'admission_counsellor', 'guard_name' => 'web']);

        $department = Department::where('code', 'ADM')->firstOrFail();
        $counsellorRole = DepartmentRole::where('department_id', $department->id)
            ->where('code', 'admission_counsellor')
            ->firstOrFail();

        $assignedCounsellor = User::factory()->create();
        $assignedCounsellor->assignRole('admission_counsellor');
        $peerCounsellor = User::factory()->create();
        $peerCounsellor->assignRole('admission_counsellor');

        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $assignedCounsellor->id,
        ]);
        DepartmentMember::create([
            'department_id' => $department->id,
            'department_role_id' => $counsellorRole->id,
            'user_id' => $peerCounsellor->id,
        ]);

        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);

        $hiddenUser = User::factory()->create(['name' => 'Hidden Offer Applicant']);
        $hiddenApplicant = Applicant::factory()->create([
            'user_id' => $hiddenUser->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'selected',
            'assigned_to' => $assignedCounsellor->id,
        ]);
        $visibleUser = User::factory()->create(['name' => 'Visible Offer Applicant']);
        $visibleApplicant = Applicant::factory()->create([
            'user_id' => $visibleUser->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'selected',
            'assigned_to' => $peerCounsellor->id,
        ]);

        foreach ([[$hiddenApplicant, 1], [$visibleApplicant, 2]] as [$applicant, $rank]) {
            MeritListEntry::create([
                'program_id' => $program->id,
                'batch_id' => $batch->id,
                'applicant_id' => $applicant->id,
                'rank' => $rank,
                'total_weighted_score' => 90 - $rank,
                'composite_score' => 90 - $rank,
                'decision' => 'selected',
                'decided_by' => $assignedCounsellor->id,
                'decided_at' => now(),
            ]);
        }

        $hiddenOffer = OfferLetter::create([
            'applicant_id' => $hiddenApplicant->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'issued',
            'acceptance_deadline' => now()->addDays(14),
            'issued_by' => $assignedCounsellor->id,
        ]);
        $visibleOffer = OfferLetter::create([
            'applicant_id' => $visibleApplicant->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'issued',
            'acceptance_deadline' => now()->addDays(14),
            'issued_by' => $peerCounsellor->id,
        ]);

        $response = $this->actingAs($peerCounsellor)
            ->get(route('admission.offer-letters.index', $program));

        $response->assertOk()
            ->assertSee('Visible Offer Applicant')
            ->assertDontSee('Hidden Offer Applicant')
            ->assertViewHas('stats', fn (array $stats) => $stats['total'] === 1 && $stats['issued'] === 1);
        $this->assertCount(1, $response->viewData('offerLetters'));

        $this->actingAs($peerCounsellor)
            ->get(route('admission.offer-letters.show', $hiddenOffer))
            ->assertForbidden();

        $this->actingAs($peerCounsellor)
            ->get(route('admission.offer-letters.export', $hiddenOffer))
            ->assertForbidden();

        $this->actingAs($peerCounsellor)
            ->post(route('admission.offer-letters.accept', $hiddenOffer))
            ->assertForbidden();

        $this->actingAs($peerCounsellor)
            ->post(route('admission.offer-letters.decline', $hiddenOffer), ['reason' => 'Out of scope'])
            ->assertForbidden();

        $this->actingAs($peerCounsellor)
            ->post(route('admission.offer-letters.bulk-generate', $program), [
                'acceptance_days' => 14,
                'batch_id' => $batch->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Generated 0 offer letter(s).');

        $hiddenOffer->delete();
        $visibleOffer->delete();

        $this->actingAs($peerCounsellor)
            ->post(route('admission.offer-letters.bulk-generate', $program), [
                'acceptance_days' => 14,
                'batch_id' => $batch->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Generated 1 offer letter(s).');

        $this->assertDatabaseMissing('offer_letters', [
            'applicant_id' => $hiddenApplicant->id,
        ]);
        $this->assertDatabaseHas('offer_letters', [
            'applicant_id' => $visibleApplicant->id,
            'status' => 'issued',
        ]);
    }

    public function test_offer_letter_pdf_download_is_blocked_after_final_admission_status(): void
    {
        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);

        $applicantUser = User::factory()->create();
        $applicantUser->assignRole('applicant');
        $applicant = Applicant::factory()->create([
            'user_id' => $applicantUser->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'enrolled',
        ]);

        $offer = OfferLetter::create([
            'applicant_id' => $applicant->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'issued',
            'acceptance_deadline' => now()->addDays(14),
            'issued_by' => User::factory()->create()->id,
        ]);

        $this->actingAs($applicantUser)
            ->get(route('applicant.offer-letters.pdf', $offer))
            ->assertRedirect(route('applicant.offer-letters.show', $offer))
            ->assertSessionHas('error', 'This offer letter is no longer downloadable because your admission application is already in a final state.');
    }

    public function test_staff_offer_letter_pdf_export_is_blocked_after_final_admission_status(): void
    {
        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $officer = User::factory()->create();
        $officer->assignRole('admission_officer');

        $applicant = Applicant::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'withdrawn',
        ]);

        $offer = OfferLetter::create([
            'applicant_id' => $applicant->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'issued',
            'acceptance_deadline' => now()->addDays(14),
            'issued_by' => $officer->id,
        ]);

        $this->actingAs($officer)
            ->from(route('admission.offer-letters.show', $offer))
            ->get(route('admission.offer-letters.export', $offer))
            ->assertRedirect(route('admission.offer-letters.show', $offer))
            ->assertSessionHas('error', 'This offer letter is locked because the applicant is already in a final admission state.');
    }
}
