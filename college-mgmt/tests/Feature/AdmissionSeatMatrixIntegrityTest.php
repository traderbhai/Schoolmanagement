<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Batch;
use App\Models\EnrollmentConfirmation;
use App\Models\MeritListEntry;
use App\Models\OfferLetter;
use App\Models\Program;
use App\Models\SeatMatrix;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdmissionSeatMatrixIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    private function matrix(): SeatMatrix
    {
        $program = Program::factory()->create(['is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'status' => 'active']);

        return SeatMatrix::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'total_seats' => 10,
            'general_seats' => 5,
            'obc_seats' => 2,
            'sc_seats' => 1,
            'st_seats' => 1,
            'ews_seats' => 1,
            'management_quota' => 0,
            'nri_quota' => 0,
            'defence_quota' => 0,
        ]);
    }

    public function test_seat_matrix_with_selection_offer_or_enrollment_history_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $matrix = $this->matrix();
        $selected = Applicant::factory()->create([
            'program_id' => $matrix->program_id,
            'batch_id' => $matrix->batch_id,
            'category' => 'general',
            'status' => 'selected',
        ]);
        $offered = Applicant::factory()->create([
            'program_id' => $matrix->program_id,
            'batch_id' => $matrix->batch_id,
            'category' => 'obc',
            'status' => 'selected',
        ]);
        $enrolled = Applicant::factory()->create([
            'program_id' => $matrix->program_id,
            'batch_id' => $matrix->batch_id,
            'category' => 'sc',
            'status' => 'enrolled',
        ]);

        MeritListEntry::create([
            'program_id' => $matrix->program_id,
            'batch_id' => $matrix->batch_id,
            'applicant_id' => $selected->id,
            'rank' => 1,
            'total_weighted_score' => 90,
            'composite_score' => 90,
            'merit_list_version' => 1,
            'decision' => 'selected',
            'category' => 'general',
        ]);
        OfferLetter::create([
            'applicant_id' => $offered->id,
            'program_id' => $matrix->program_id,
            'batch_id' => $matrix->batch_id,
            'status' => 'issued',
            'acceptance_deadline' => now()->addDays(7)->toDateString(),
            'issued_at' => now(),
            'issued_by' => $admin->id,
        ]);
        EnrollmentConfirmation::create([
            'applicant_id' => $enrolled->id,
            'confirmed_by' => $admin->id,
            'confirmed_at' => now(),
            'enrollment_number' => 'ENR-SEAT-001',
            'roll_number' => 'ROLL-SEAT-001',
            'batch_id' => $matrix->batch_id,
            'status' => 'completed',
        ]);

        $this->actingAs($admin)
            ->delete(route('admission.seat-matrices.destroy', $matrix))
            ->assertRedirect(route('admission.seat-matrices.index', $matrix->program))
            ->assertSessionHas('error', 'This seat matrix has selections, offers, waitlist, or enrollment history and cannot be deleted.');

        $this->assertDatabaseHas('seat_matrices', ['id' => $matrix->id]);
        $this->assertDatabaseHas('merit_list_entries', ['applicant_id' => $selected->id]);
        $this->assertDatabaseHas('offer_letters', ['applicant_id' => $offered->id]);
        $this->assertDatabaseHas('enrollment_confirmations', ['applicant_id' => $enrolled->id]);
    }

    public function test_seat_matrix_index_explains_offer_waitlist_and_enrollment_impact(): void
    {
        $admin = $this->admin();
        $program = Program::factory()->create(['is_active' => true, 'name' => 'Seat UX Program']);

        $this->actingAs($admin)
            ->get(route('admission.seat-matrices.index', $program))
            ->assertOk()
            ->assertSeeText('Seat-control setup sequence')
            ->assertSeeText('Publish offers and waitlist')
            ->assertSeeText('Monitor enrollment usage')
            ->assertSeeText('No seat matrix is configured for this program yet')
            ->assertSeeText('Configure total, reservation, and quota seats before offer rounds, waitlist promotions, or manual seat holds are published for this program.')
            ->assertSee(route('admission.seat-matrices.create', $program), false)
            ->assertDontSee('N/A', false)
            ->assertDontSee('Ã', false)
            ->assertDontSee('â', false)
            ->assertDontSee('—', false);

        $matrixProgram = Program::factory()->create([
            'is_active' => true,
            'name' => 'Seat UX Configured Program',
        ]);
        $matrix = SeatMatrix::create([
            'program_id' => $matrixProgram->id,
            'batch_id' => null,
            'total_seats' => 30,
            'general_seats' => 15,
            'obc_seats' => 6,
            'sc_seats' => 3,
            'st_seats' => 2,
            'ews_seats' => 2,
            'management_quota' => 1,
            'nri_quota' => 1,
            'defence_quota' => 0,
        ]);

        $this->actingAs($admin)
            ->get(route('admission.seat-matrices.index', $matrixProgram))
            ->assertOk()
            ->assertSeeText('Seat matrices with selections, offers, waitlist movement, or enrollment history are protected from deletion')
            ->assertSeeText('Batch Scope')
            ->assertSeeText('All Batches')
            ->assertSeeText((string) $matrix->total_seats)
            ->assertDontSee('N/A', false)
            ->assertDontSee('Ã', false)
            ->assertDontSee('â', false)
            ->assertDontSee('—', false);
    }

    public function test_seat_matrix_capacity_cannot_be_reduced_below_committed_applicants(): void
    {
        $admin = $this->admin();
        $matrix = $this->matrix();
        $general = Applicant::factory()->create([
            'program_id' => $matrix->program_id,
            'batch_id' => $matrix->batch_id,
            'category' => 'general',
            'status' => 'selected',
        ]);
        $obc = Applicant::factory()->create([
            'program_id' => $matrix->program_id,
            'batch_id' => $matrix->batch_id,
            'category' => 'obc',
            'status' => 'selected',
        ]);

        MeritListEntry::create([
            'program_id' => $matrix->program_id,
            'batch_id' => $matrix->batch_id,
            'applicant_id' => $general->id,
            'rank' => 1,
            'total_weighted_score' => 95,
            'composite_score' => 95,
            'merit_list_version' => 1,
            'decision' => 'selected',
            'category' => 'general',
        ]);
        OfferLetter::create([
            'applicant_id' => $obc->id,
            'program_id' => $matrix->program_id,
            'batch_id' => $matrix->batch_id,
            'status' => 'accepted',
            'acceptance_deadline' => now()->addDays(5)->toDateString(),
            'issued_at' => now(),
            'issued_by' => $admin->id,
            'accepted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->put(route('admission.seat-matrices.update', $matrix), [
                'total_seats' => 10,
                'general_seats' => 0,
                'obc_seats' => 2,
                'sc_seats' => 1,
                'st_seats' => 1,
                'ews_seats' => 1,
                'management_quota' => 0,
                'nri_quota' => 0,
                'defence_quota' => 0,
            ])
            ->assertSessionHasErrors('general_seats');

        $this->assertSame(5, $matrix->fresh()->general_seats);

        $this->actingAs($admin)
            ->put(route('admission.seat-matrices.update', $matrix), [
                'total_seats' => 1,
                'general_seats' => 5,
                'obc_seats' => 2,
                'sc_seats' => 1,
                'st_seats' => 1,
                'ews_seats' => 1,
                'management_quota' => 0,
                'nri_quota' => 0,
                'defence_quota' => 0,
            ])
            ->assertSessionHasErrors('total_seats');

        $this->assertSame(10, $matrix->fresh()->total_seats);
    }

    public function test_seat_matrix_can_be_increased_after_commitments_and_unused_matrix_can_be_deleted(): void
    {
        $admin = $this->admin();
        $matrix = $this->matrix();
        $applicant = Applicant::factory()->create([
            'program_id' => $matrix->program_id,
            'batch_id' => $matrix->batch_id,
            'category' => 'general',
        ]);
        MeritListEntry::create([
            'program_id' => $matrix->program_id,
            'batch_id' => $matrix->batch_id,
            'applicant_id' => $applicant->id,
            'rank' => 1,
            'total_weighted_score' => 88,
            'composite_score' => 88,
            'merit_list_version' => 1,
            'decision' => 'selected',
            'category' => 'general',
        ]);

        $this->actingAs($admin)
            ->put(route('admission.seat-matrices.update', $matrix), [
                'total_seats' => 12,
                'general_seats' => 6,
                'obc_seats' => 2,
                'sc_seats' => 1,
                'st_seats' => 1,
                'ews_seats' => 1,
                'management_quota' => 1,
                'nri_quota' => 0,
                'defence_quota' => 0,
            ])
            ->assertRedirect(route('admission.seat-matrices.index', $matrix->program));

        $this->assertSame(12, $matrix->fresh()->total_seats);
        $this->assertSame(6, $matrix->fresh()->general_seats);

        $unused = $this->matrix();
        $this->actingAs($admin)
            ->delete(route('admission.seat-matrices.destroy', $unused))
            ->assertRedirect(route('admission.seat-matrices.index', $unused->program))
            ->assertSessionHas('success', 'Seat matrix deleted.');

        $this->assertDatabaseMissing('seat_matrices', ['id' => $unused->id]);
    }

    public function test_seat_matrix_batch_must_belong_to_selected_active_program(): void
    {
        $admin = $this->admin();
        $program = Program::factory()->create(['is_active' => true]);
        $otherProgram = Program::factory()->create(['is_active' => true]);
        $otherBatch = Batch::factory()->create([
            'program_id' => $otherProgram->id,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('admission.seat-matrices.store', $program), [
                'batch_id' => $otherBatch->id,
                'total_seats' => 10,
                'general_seats' => 5,
                'obc_seats' => 2,
                'sc_seats' => 1,
                'st_seats' => 1,
                'ews_seats' => 1,
                'management_quota' => 0,
                'nri_quota' => 0,
                'defence_quota' => 0,
            ])
            ->assertSessionHasErrors('batch_id');

        $this->assertDatabaseMissing('seat_matrices', [
            'program_id' => $program->id,
            'batch_id' => $otherBatch->id,
        ]);
    }

    public function test_seat_matrix_total_cannot_be_less_than_category_and_quota_total(): void
    {
        $admin = $this->admin();
        $matrix = $this->matrix();

        $this->actingAs($admin)
            ->put(route('admission.seat-matrices.update', $matrix), [
                'total_seats' => 3,
                'general_seats' => 5,
                'obc_seats' => 2,
                'sc_seats' => 1,
                'st_seats' => 1,
                'ews_seats' => 1,
                'management_quota' => 0,
                'nri_quota' => 0,
                'defence_quota' => 0,
            ])
            ->assertSessionHasErrors('total_seats');

        $this->assertSame(10, $matrix->fresh()->total_seats);
    }
}
