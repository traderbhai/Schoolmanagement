<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Batch;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicStatusTrackerTest extends TestCase
{
    use RefreshDatabase;

    private function makeApplicant(string $status): Applicant
    {
        $program = Program::factory()->create(['name' => 'Tracked MBA']);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'name' => 'Tracked 2026 Batch']);
        $user = User::factory()->create([
            'name' => 'Tracked Applicant',
            'email' => "tracked-{$status}@example.com",
        ]);

        return Applicant::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'application_number' => "APP-TRACK-{$status}",
            'status' => $status,
            'applied_at' => now(),
        ]);
    }

    public function test_tracker_guides_draft_applicant_to_complete_application(): void
    {
        $applicant = $this->makeApplicant('draft');

        $this->post(route('public.status-tracker.track'), [
            'application_number' => $applicant->application_number,
            'email' => $applicant->user->email,
        ])
            ->assertStatus(200)
            ->assertSee('Application Draft')
            ->assertSee('Complete your application')
            ->assertSee('Continue application')
            ->assertSee('Tracked 2026 Batch');
    }

    public function test_tracker_guides_selected_applicant_to_offer_tasks(): void
    {
        $applicant = $this->makeApplicant('selected');

        $this->post(route('public.status-tracker.track'), [
            'application_number' => $applicant->application_number,
            'email' => $applicant->user->email,
        ])
            ->assertStatus(200)
            ->assertSee('Selected - Congratulations!')
            ->assertSee('Complete your admission offer tasks')
            ->assertSee('Review offer tasks')
            ->assertSee('submit required payments');
    }

    public function test_tracker_not_found_state_has_recovery_actions(): void
    {
        $this->post(route('public.status-tracker.track'), [
            'application_number' => 'APP-NOT-FOUND',
            'email' => 'missing@example.com',
        ])
            ->assertStatus(200)
            ->assertSee('No application found')
            ->assertSee('Apply for an open intake')
            ->assertSee('Applicant login');
    }
}
