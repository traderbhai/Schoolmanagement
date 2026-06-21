<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Batch;
use App\Models\MeritListEntry;
use App\Models\OfferLetter;
use App\Models\Program;
use App\Models\ProgramSeatMatrix;
use App\Models\SeatMatrix;
use App\Models\User;
use App\Services\AdmissionWaitlistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdmissionWaitlistPromotionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function admissionUser(): User
    {
        Role::firstOrCreate(['name' => 'admission_head', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('admission_head');

        return $user;
    }

    private function waitlistedEntry(?SeatMatrix $matrix = null, string $category = 'general'): MeritListEntry
    {
        $program = $matrix?->program ?? Program::factory()->create(['is_active' => true]);
        $batch = $matrix?->batch ?? Batch::factory()->create(['program_id' => $program->id]);
        $applicant = Applicant::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'category' => $category,
            'status' => 'shortlisted',
        ]);

        return MeritListEntry::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'applicant_id' => $applicant->id,
            'rank' => 2,
            'total_weighted_score' => 80,
            'composite_score' => 80,
            'merit_list_version' => 1,
            'decision' => 'waitlisted',
            'category' => $category,
        ]);
    }

    private function matrix(array $overrides = []): SeatMatrix
    {
        $program = Program::factory()->create(['is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id]);

        return SeatMatrix::create(array_merge([
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
        ], $overrides));
    }

    public function test_waitlist_promotion_requires_configured_seat_matrix(): void
    {
        $staff = $this->admissionUser();
        $entry = $this->waitlistedEntry();

        $this->actingAs($staff)
            ->post(route('admission.waitlist.promote', $entry))
            ->assertRedirect()
            ->assertSessionHas('error', 'No seat matrix is configured for this program/batch.');

        $this->assertSame('waitlisted', $entry->fresh()->decision);
        $this->assertSame('shortlisted', $entry->applicant->fresh()->status);
    }

    public function test_waitlist_promotion_respects_category_capacity(): void
    {
        $staff = $this->admissionUser();
        $matrix = $this->matrix();
        $selectedApplicant = Applicant::factory()->create([
            'program_id' => $matrix->program_id,
            'batch_id' => $matrix->batch_id,
            'category' => 'general',
            'status' => 'selected',
        ]);
        MeritListEntry::create([
            'program_id' => $matrix->program_id,
            'batch_id' => $matrix->batch_id,
            'applicant_id' => $selectedApplicant->id,
            'rank' => 1,
            'total_weighted_score' => 90,
            'composite_score' => 90,
            'merit_list_version' => 1,
            'decision' => 'selected',
            'category' => 'general',
        ]);
        $entry = $this->waitlistedEntry($matrix, 'general');

        $this->actingAs($staff)
            ->post(route('admission.waitlist.promote', $entry))
            ->assertRedirect()
            ->assertSessionHas('error', 'No available seats in this applicant category.');

        $this->assertSame('waitlisted', $entry->fresh()->decision);
    }

    public function test_waitlist_promotion_blocks_final_state_applicant(): void
    {
        $staff = $this->admissionUser();
        $matrix = $this->matrix();
        $entry = $this->waitlistedEntry($matrix, 'general');
        $entry->applicant->update(['status' => 'enrolled']);

        $this->actingAs($staff)
            ->post(route('admission.waitlist.promote', $entry))
            ->assertRedirect()
            ->assertSessionHas('error', 'This applicant is not eligible for waitlist promotion.');

        $this->assertSame('waitlisted', $entry->fresh()->decision);
        $this->assertSame('enrolled', $entry->applicant->fresh()->status);
    }

    public function test_waitlist_promotion_blocks_applicant_with_active_offer(): void
    {
        $staff = $this->admissionUser();
        $matrix = $this->matrix();
        $entry = $this->waitlistedEntry($matrix, 'general');

        OfferLetter::create([
            'applicant_id' => $entry->applicant_id,
            'program_id' => $entry->program_id,
            'batch_id' => $entry->batch_id,
            'status' => 'issued',
            'acceptance_deadline' => now()->addDays(7),
            'issued_by' => $staff->id,
        ]);

        $this->actingAs($staff)
            ->post(route('admission.waitlist.promote', $entry))
            ->assertRedirect()
            ->assertSessionHas('error', 'This applicant already has an active offer.');

        $this->assertSame('waitlisted', $entry->fresh()->decision);
    }

    public function test_waitlist_promotion_selects_candidate_when_total_and_category_seats_are_available(): void
    {
        $staff = $this->admissionUser();
        $matrix = $this->matrix();
        $entry = $this->waitlistedEntry($matrix, 'obc');

        $this->actingAs($staff)
            ->post(route('admission.waitlist.promote', $entry))
            ->assertRedirect()
            ->assertSessionHas('success');

        $entry->refresh();
        $this->assertSame('selected', $entry->decision);
        $this->assertSame($staff->id, $entry->decided_by);
        $this->assertSame('selected', $entry->applicant->fresh()->status);
    }

    public function test_offer_seat_waitlist_service_skips_final_state_rows_when_promoting_next(): void
    {
        $staff = $this->admissionUser();
        $program = Program::factory()->create(['is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        ProgramSeatMatrix::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'general_seats' => 2,
            'obc_seats' => 0,
            'obc_nc_seats' => 0,
            'sc_seats' => 0,
            'st_seats' => 0,
            'ews_seats' => 0,
            'pwd_seats' => 0,
            'nri_seats' => 0,
            'management_quota_seats' => 0,
            'state_quota_percentage' => 0,
            'is_active' => true,
        ]);

        $finalApplicant = Applicant::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'withdrawn',
        ]);
        $eligibleApplicant = Applicant::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'shortlisted',
        ]);

        $finalEntryId = DB::table('admission_waitlist_entries')->insertGetId([
            'applicant_id' => $finalApplicant->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'rank' => 1,
            'category' => 'general',
            'status' => 'waiting',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $eligibleEntryId = DB::table('admission_waitlist_entries')->insertGetId([
            'applicant_id' => $eligibleApplicant->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'rank' => 2,
            'category' => 'general',
            'status' => 'waiting',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $promoted = app(AdmissionWaitlistService::class)->promoteNext($program->id, $batch->id, $staff, 'Feature test release');

        $this->assertNotNull($promoted);
        $this->assertSame($eligibleEntryId, $promoted->id);
        $this->assertSame('waiting', DB::table('admission_waitlist_entries')->where('id', $finalEntryId)->value('status'));
        $this->assertSame('promoted', DB::table('admission_waitlist_entries')->where('id', $eligibleEntryId)->value('status'));
        $this->assertDatabaseHas('admission_seat_holds', [
            'applicant_id' => $eligibleApplicant->id,
            'status' => 'held',
        ]);
        $this->assertDatabaseMissing('admission_seat_holds', [
            'applicant_id' => $finalApplicant->id,
        ]);
    }

    public function test_waitlist_empty_state_explains_filters_source_and_seat_setup(): void
    {
        $staff = $this->admissionUser();
        $program = Program::factory()->create(['is_active' => true, 'name' => 'Waitlist Program']);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'name' => 'Waitlist Batch']);

        $this->actingAs($staff)
            ->get(route('admission.waitlist.index', [$program, 'batch_id' => $batch->id]))
            ->assertOk()
            ->assertSee('Waitlist Management')
            ->assertSee('Program: Waitlist Program | Batch: Waitlist Batch')
            ->assertSee('Not configured')
            ->assertSee('Configure a seat matrix before promotions can proceed.')
            ->assertSee('No waitlisted candidates are visible')
            ->assertSee('check merit-list decisions, selected batch filters, and seat matrix setup')
            ->assertSee('Clear Batch Filter')
            ->assertSee('Open Merit List')
            ->assertSee('Review Seat Matrix')
            ->assertDontSee('No waitlisted candidates for this program.')
            ->assertDontSee('N/A', false)
            ->assertDontSee('â', false)
            ->assertDontSee('₹', false);
    }

    public function test_waitlist_rows_use_readable_candidate_labels_and_capacity_context(): void
    {
        $staff = $this->admissionUser();
        $matrix = $this->matrix([
            'total_seats' => 3,
            'general_seats' => 3,
            'obc_seats' => 0,
        ]);
        $entry = $this->waitlistedEntry($matrix, 'general');
        $entry->applicant->user->update([
            'name' => 'Visible Waitlist Applicant',
            'email' => 'waitlist-applicant@example.test',
        ]);
        $entry->applicant->update(['application_number' => 'APP-WAIT-001']);

        $this->actingAs($staff)
            ->get(route('admission.waitlist.index', [$matrix->program, 'batch_id' => $matrix->batch_id]))
            ->assertOk()
            ->assertSee('Visible Waitlist Applicant')
            ->assertSee('waitlist-applicant@example.test')
            ->assertSee('APP-WAIT-001')
            ->assertSee('Program: '.$matrix->program->name.' | Batch: '.$matrix->batch->name)
            ->assertSee('Available Seats')
            ->assertSee('Promote')
            ->assertSee('Candidates are listed in merit order')
            ->assertDontSee('N/A', false)
            ->assertDontSee('â', false)
            ->assertDontSee('₹', false);
    }
}
