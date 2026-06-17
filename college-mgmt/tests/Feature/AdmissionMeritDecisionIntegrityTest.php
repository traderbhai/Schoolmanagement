<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Batch;
use App\Models\MeritListEntry;
use App\Models\OfferLetter;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdmissionMeritDecisionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function admissionHead(): User
    {
        Role::firstOrCreate(['name' => 'admission_head', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admission_head');

        return $user;
    }

    private function meritEntry(string $decision = 'selected'): MeritListEntry
    {
        $program = Program::factory()->create(['is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $applicant = Applicant::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'selected',
            'academic_data' => ['cgpa' => 8.5],
        ]);

        return MeritListEntry::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'applicant_id' => $applicant->id,
            'rank' => 1,
            'total_weighted_score' => 85,
            'academic_score' => 85,
            'composite_score' => 85,
            'merit_list_version' => 1,
            'decision' => $decision,
            'decided_by' => $this->admissionHead()->id,
            'decided_at' => now(),
        ]);
    }

    private function activeOffer(MeritListEntry $entry, string $status = 'issued'): OfferLetter
    {
        return OfferLetter::create([
            'applicant_id' => $entry->applicant_id,
            'program_id' => $entry->program_id,
            'batch_id' => $entry->batch_id,
            'status' => $status,
            'acceptance_deadline' => now()->addDays(10)->toDateString(),
            'issued_by' => $this->admissionHead()->id,
        ]);
    }

    public function test_merit_decision_linked_to_active_offer_cannot_be_changed(): void
    {
        $head = $this->admissionHead();
        $entry = $this->meritEntry('selected');
        $this->activeOffer($entry, 'issued');

        $this->actingAs($head)
            ->post(route('admission.merit-list.decide', $entry), [
                'decision' => 'rejected',
                'notes' => 'Trying to reverse selected offer.',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Merit decisions linked to active offer letters are locked.');

        $entry->refresh();
        $this->assertSame('selected', $entry->decision);
        $this->assertNull($entry->notes);
    }

    public function test_final_merit_decision_without_offer_cannot_be_changed_to_another_outcome(): void
    {
        $head = $this->admissionHead();
        $entry = $this->meritEntry('rejected');

        $this->actingAs($head)
            ->post(route('admission.merit-list.decide', $entry), [
                'decision' => 'selected',
                'notes' => 'Trying to change final rejection.',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Final merit decisions are locked. Create an audited correction workflow instead of changing selection history.');

        $this->assertSame('rejected', $entry->fresh()->decision);
    }

    public function test_regenerate_merit_list_is_blocked_after_active_offer_exists(): void
    {
        $head = $this->admissionHead();
        $entry = $this->meritEntry('selected');
        $this->activeOffer($entry, 'accepted');

        $this->actingAs($head)
            ->post(route('admission.merit-list.generate', $entry->program), [
                'batch_id' => $entry->batch_id,
                'academic_weight' => 20,
                'entrance_exam_weight' => 30,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, MeritListEntry::where('applicant_id', $entry->applicant_id)->count());
        $this->assertSame('selected', $entry->fresh()->decision);
    }
}
