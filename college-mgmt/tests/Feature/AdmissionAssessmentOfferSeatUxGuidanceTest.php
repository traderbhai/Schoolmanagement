<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\MasterDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdmissionAssessmentOfferSeatUxGuidanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDemoSeeder::class);
    }

    public function test_assessment_control_room_explains_assessment_day_sequence(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();

        $this->actingAs($head)
            ->get(route('admission.assessment-control-room.index'))
            ->assertOk()
            ->assertSee('Assessment-day control sequence')
            ->assertSee('Confirm panel readiness')
            ->assertSee('Move candidates through lifecycle')
            ->assertSee('Chase pending scores')
            ->assertSee('Review variance before committee decisions')
            ->assertSee('Evaluator, rubric, venue, capacity, and pending score status')
            ->assertSee('Track invited to completed/no-show/rescheduled')
            ->assertSee('Ask evaluator or chair to finalize')
            ->assertSee(route('admission.evaluator-scoring.index'), false)
            ->assertSee(route('admission.assessment-normalization.index'), false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_assessment_scheduling_explains_logistics_workflow(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();

        $this->actingAs($head)
            ->get(route('admission.assessment-slots.index'))
            ->assertOk()
            ->assertSee('Scheduling workflow')
            ->assertSee('Create slots/resources')
            ->assertSee('Assign candidates')
            ->assertSee('Confirm evaluators')
            ->assertSee('Build GD/submission queues')
            ->assertSee('Run check-in and resolve conflicts')
            ->assertSee('Start assessment logistics here')
            ->assertSee('Assign candidates or bulk-fill capacity')
            ->assertSee('Move candidates through invited, confirmed, checked-in, waiting, in-progress, completed, no-show, rescheduled')
            ->assertSee(route('admission.assessment-control-room.index'), false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_selection_committee_explains_decision_sequence(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();

        $this->actingAs($head)
            ->get(route('admission.selection-committee.index'))
            ->assertOk()
            ->assertSee('Committee decision sequence')
            ->assertSee('Review score evidence and variance')
            ->assertSee('Check attendance/documents/payment readiness')
            ->assertSee('Save selected/waitlist/rejected/hold/reschedule decision with reason')
            ->assertSee('Reason is required for every decision')
            ->assertSee('Use normalized/outlier signals before deciding')
            ->assertSee(route('admission.assessment-normalization.index'), false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_offer_seat_control_explains_offer_to_enrollment_sequence(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();

        $this->actingAs($head)
            ->get(route('admission.offer-rounds.index'))
            ->assertOk()
            ->assertSee('Offer-to-enrollment control sequence')
            ->assertSee('Create/publish offer round')
            ->assertSee('Hold or release seats')
            ->assertSee('Promote waitlist where seats open')
            ->assertSee('Approve deferrals')
            ->assertSee('Clear joining-kit blockers')
            ->assertSee('Publish only after source merit/committee review')
            ->assertSee('Watch expiry, release, and payment deadlines')
            ->assertSee('Approval requires carry-forward notes')
            ->assertSee(route('admission.v039.exports', 'offer-seat-control'), false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Whoops', false)
            ->assertDontSee('SERVICE ERROR', false)
            ->assertDontSee('Laravel\\', false);
    }

    public function test_offer_seat_control_empty_states_explain_source_workflow_and_next_actions(): void
    {
        $head = User::where('email', 'head@college.com')->firstOrFail();
        DB::table('admission_joining_kit_tasks')->delete();
        DB::table('admission_deferrals')->delete();
        DB::table('admission_seat_holds')->delete();
        DB::table('admission_waitlist_entries')->delete();
        DB::table('admission_offer_rounds')->delete();

        $this->actingAs($head)
            ->get(route('admission.offer-rounds.index'))
            ->assertOk()
            ->assertSeeText('No offer rounds are ready yet')
            ->assertSeeText('Create an offer round after merit list or committee decisions are reviewed')
            ->assertSeeText('No waitlist entries are active')
            ->assertSeeText('review selected applicants and the seat matrix first')
            ->assertSeeText('No seat holds are active')
            ->assertSeeText('check offer letters and payment deadline status')
            ->assertSeeText('No joining-kit blockers are prepared yet')
            ->assertSeeText('final document, fee, orientation, hostel, transport, or Academics handoff checks')
            ->assertSeeText('No deferral requests need review')
            ->assertSeeText('payment, documents, and target batch readiness')
            ->assertSee(route('admission.selection-committee.index'), false)
            ->assertSee(route('admission.applicants.index', ['status' => 'selected']), false)
            ->assertSee('/admission/seat-matrices/', false)
            ->assertSee(route('admission.payments.queue'), false)
            ->assertSee(route('admission.enrollment.index'), false)
            ->assertDontSeeText('No offer rounds created yet.')
            ->assertDontSeeText('No waitlist entries.')
            ->assertDontSeeText('No active seat holds.')
            ->assertDontSeeText('No joining-kit tasks prepared yet.')
            ->assertDontSeeText('No deferral requests.');
    }
}
