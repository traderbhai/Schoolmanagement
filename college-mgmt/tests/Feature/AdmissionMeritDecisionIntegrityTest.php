<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Batch;
use App\Models\Department;
use App\Models\DepartmentMember;
use App\Models\DepartmentRole;
use App\Models\MeritListEntry;
use App\Models\OfferLetter;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
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

    public function test_merit_official_outputs_use_readable_missing_data_labels(): void
    {
        $this->actingAs($this->admissionHead());
        View::share('errors', new ViewErrorBag);
        $entry = $this->meritEntry('pending');
        $entry->update([
            'step_scores' => [],
            'academic_score' => null,
        ]);
        $entry->load(['program', 'applicant.user']);
        $entry->applicant->setRelation('user', null);
        $entry->applicant->application_number = null;

        $pdfHtml = view('admission.merit-list.pdf', [
            'program' => $entry->program,
            'batch' => null,
            'entries' => collect([$entry]),
            'steps' => collect([(object) ['id' => 1, 'name' => 'Personal Interview', 'typeLabel' => 'PI']]),
        ])->render();

        $this->assertStringContainsString('Merit List -', $pdfHtml);
        $this->assertStringContainsString('Applicant name not recorded', $pdfHtml);
        $this->assertStringContainsString('Application number pending', $pdfHtml);
        $this->assertStringContainsString('Step score not recorded', $pdfHtml);
        $this->assertStringContainsString('Academic score not recorded', $pdfHtml);
        $this->assertStringNotContainsString('N/A', $pdfHtml);
        $this->assertStringNotContainsString('â', $pdfHtml);
        $this->assertStringNotContainsString('Ã', $pdfHtml);
        $this->assertStringNotContainsString('Â', $pdfHtml);
        $this->assertStringNotContainsString('&mdash;', $pdfHtml);
        $this->assertStringNotContainsString('&bull;', $pdfHtml);

        $categoryHtml = view('admission.merit-list.category-report', [
            'program' => $entry->program,
            'batches' => collect(),
            'batchId' => null,
            'seatMatrix' => null,
            'report' => [
                'general' => [
                    'label' => 'General',
                    'seats' => 0,
                    'applied' => 1,
                    'scored' => 0,
                    'selected' => 0,
                    'vacant' => 0,
                    'fill_pct' => 0,
                ],
            ],
        ])->render();

        $this->assertStringContainsString('Category-Wise Report -', $categoryHtml);
        $this->assertStringContainsString('Seat matrix not configured', $categoryHtml);
        $this->assertStringContainsString('Fill rate unavailable until seats are configured', $categoryHtml);
        $this->assertStringNotContainsString('N/A', $categoryHtml);
        $this->assertStringNotContainsString('â', $categoryHtml);
        $this->assertStringNotContainsString('Ã', $categoryHtml);
        $this->assertStringNotContainsString('Â', $categoryHtml);
        $this->assertStringNotContainsString('&mdash;', $categoryHtml);
        $this->assertStringNotContainsString('&bull;', $categoryHtml);
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

    public function test_pending_merit_decision_for_final_state_applicant_is_locked(): void
    {
        $head = $this->admissionHead();
        $entry = $this->meritEntry('pending');
        $entry->applicant->update(['status' => 'enrolled']);

        $this->actingAs($head)
            ->post(route('admission.merit-list.decide', $entry), [
                'decision' => 'selected',
                'notes' => 'Trying to select an enrolled applicant.',
                'update_applicant_status' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Merit decisions are locked for applicants in a final admission state.');

        $entry->refresh();
        $this->assertSame('pending', $entry->decision);
        $this->assertNull($entry->notes);
        $this->assertSame('enrolled', $entry->applicant->fresh()->status);
    }

    public function test_bulk_merit_decision_skips_final_state_and_active_offer_entries(): void
    {
        $head = $this->admissionHead();
        $program = Program::factory()->create(['is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id]);

        $finalApplicant = Applicant::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'withdrawn',
        ]);
        $offeredApplicant = Applicant::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'shortlisted',
        ]);
        $eligibleApplicant = Applicant::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'shortlisted',
        ]);

        $finalEntry = MeritListEntry::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'applicant_id' => $finalApplicant->id,
            'rank' => 1,
            'total_weighted_score' => 90,
            'composite_score' => 90,
            'merit_list_version' => 1,
            'decision' => 'pending',
        ]);
        $offeredEntry = MeritListEntry::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'applicant_id' => $offeredApplicant->id,
            'rank' => 2,
            'total_weighted_score' => 85,
            'composite_score' => 85,
            'merit_list_version' => 1,
            'decision' => 'pending',
        ]);
        $eligibleEntry = MeritListEntry::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'applicant_id' => $eligibleApplicant->id,
            'rank' => 3,
            'total_weighted_score' => 80,
            'composite_score' => 80,
            'merit_list_version' => 1,
            'decision' => 'pending',
        ]);
        $this->activeOffer($offeredEntry, 'issued');

        $this->actingAs($head)
            ->post(route('admission.merit-list.bulk-decide', $program), [
                'accept_top' => 2,
                'waitlist_next' => 0,
                'batch_id' => $batch->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Bulk decision applied: 1 selected, 0 waitlisted. Skipped 2 locked applicant(s).');

        $this->assertSame('pending', $finalEntry->fresh()->decision);
        $this->assertSame('pending', $offeredEntry->fresh()->decision);
        $this->assertSame('selected', $eligibleEntry->fresh()->decision);
        $this->assertSame($head->id, $eligibleEntry->fresh()->decided_by);
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

    public function test_merit_list_pages_explain_generation_decisions_and_filtered_empty_states(): void
    {
        $head = $this->admissionHead();
        $program = Program::factory()->create([
            'is_active' => true,
            'name' => 'Merit UX Program',
            'code' => 'MUX',
        ]);
        $batch = Batch::factory()->create([
            'program_id' => $program->id,
            'name' => 'Merit UX Batch',
        ]);

        $this->actingAs($head)
            ->get(route('admission.merit-list.index', $program))
            ->assertOk()
            ->assertSeeText('Merit-list control sequence')
            ->assertSeeText('Confirm seat matrix')
            ->assertSeeText('No merit list is generated for this program yet')
            ->assertSeeText('selected, waitlisted, rejected, offer-letter, and seat-control workflows')
            ->assertDontSee('N/A', false)
            ->assertDontSee('Ã', false)
            ->assertDontSee('â', false)
            ->assertDontSee('—', false);

        $selectedUser = User::factory()->create(['name' => 'Selected Merit Candidate']);
        $selectedApplicant = Applicant::factory()->create([
            'user_id' => $selectedUser->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'selected',
        ]);
        $pendingUser = User::factory()->create(['name' => 'Pending Merit Candidate']);
        $pendingApplicant = Applicant::factory()->create([
            'user_id' => $pendingUser->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'shortlisted',
        ]);

        MeritListEntry::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'applicant_id' => $selectedApplicant->id,
            'rank' => 1,
            'total_weighted_score' => 91,
            'academic_score' => 91,
            'composite_score' => 91,
            'merit_list_version' => 1,
            'decision' => 'selected',
        ]);
        MeritListEntry::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'applicant_id' => $pendingApplicant->id,
            'rank' => 2,
            'total_weighted_score' => 82,
            'academic_score' => null,
            'composite_score' => 82,
            'merit_list_version' => 1,
            'decision' => 'pending',
        ]);

        $selectedKpiUrl = route('admission.merit-list.show', [
            'program' => $program->id,
            'decision' => 'selected',
        ]);
        $selectedFilteredUrl = route('admission.merit-list.show', [
            'program' => $program->id,
            'batch_id' => $batch->id,
            'decision' => 'selected',
        ]);

        $this->actingAs($head)
            ->get(route('admission.merit-list.index', $program))
            ->assertOk()
            ->assertSeeText('Selected')
            ->assertSee($selectedKpiUrl, false)
            ->assertSeeText('Regeneration is blocked once active offer letters exist')
            ->assertDontSee('N/A', false)
            ->assertDontSee('Ã', false)
            ->assertDontSee('â', false)
            ->assertDontSee('—', false);

        $this->actingAs($head)
            ->get($selectedFilteredUrl)
            ->assertOk()
            ->assertSeeText('Decision workflow')
            ->assertSeeText('Current view:')
            ->assertSeeText('Batch: Merit UX Batch')
            ->assertSeeText('Decision: Selected')
            ->assertSeeText('Rows: 1')
            ->assertSeeText('Selected Merit Candidate')
            ->assertDontSeeText('Pending Merit Candidate')
            ->assertDontSee('N/A', false)
            ->assertDontSee('Ã', false)
            ->assertDontSee('â', false)
            ->assertDontSee('—', false);

        $this->actingAs($head)
            ->get(route('admission.merit-list.show', [
                'program' => $program->id,
                'batch_id' => $batch->id,
                'decision' => 'rejected',
            ]))
            ->assertOk()
            ->assertSeeText('No merit-list entries match this view')
            ->assertSeeText('Check the selected batch and decision filters')
            ->assertSeeText('Clear Filters')
            ->assertSeeText('Review Merit Setup')
            ->assertDontSee('N/A', false)
            ->assertDontSee('Ã', false)
            ->assertDontSee('â', false)
            ->assertDontSee('—', false);
    }

    public function test_merit_list_visibility_and_decision_routes_respect_assignment_scope_and_authority(): void
    {
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

        $program = Program::factory()->create(['is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id]);

        $hiddenUser = User::factory()->create(['name' => 'Hidden Merit Applicant']);
        $hiddenApplicant = Applicant::factory()->create([
            'user_id' => $hiddenUser->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'shortlisted',
            'assigned_to' => $assignedCounsellor->id,
        ]);
        $visibleUser = User::factory()->create(['name' => 'Visible Merit Applicant']);
        $visibleApplicant = Applicant::factory()->create([
            'user_id' => $visibleUser->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'shortlisted',
            'assigned_to' => $peerCounsellor->id,
        ]);
        $unassignedUser = User::factory()->create(['name' => 'Unassigned Merit Applicant']);
        $unassignedApplicant = Applicant::factory()->create([
            'user_id' => $unassignedUser->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'shortlisted',
            'assigned_to' => null,
        ]);

        $hiddenEntry = MeritListEntry::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'applicant_id' => $hiddenApplicant->id,
            'rank' => 1,
            'total_weighted_score' => 90,
            'composite_score' => 90,
            'merit_list_version' => 1,
            'decision' => 'pending',
        ]);
        $visibleEntry = MeritListEntry::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'applicant_id' => $visibleApplicant->id,
            'rank' => 2,
            'total_weighted_score' => 85,
            'composite_score' => 85,
            'merit_list_version' => 1,
            'decision' => 'pending',
        ]);
        MeritListEntry::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'applicant_id' => $unassignedApplicant->id,
            'rank' => 3,
            'total_weighted_score' => 80,
            'composite_score' => 80,
            'merit_list_version' => 1,
            'decision' => 'pending',
        ]);

        $this->actingAs($peerCounsellor)
            ->get(route('admission.merit-list.show', $program))
            ->assertOk()
            ->assertSee('Visible Merit Applicant')
            ->assertSee('Unassigned Merit Applicant')
            ->assertDontSee('Hidden Merit Applicant');

        $this->actingAs($peerCounsellor)
            ->post(route('admission.merit-list.decide', $visibleEntry), [
                'decision' => 'selected',
            ])
            ->assertForbidden();

        $this->actingAs($peerCounsellor)
            ->post(route('admission.merit-list.decide', $hiddenEntry), [
                'decision' => 'selected',
            ])
            ->assertForbidden();

        $this->actingAs($peerCounsellor)
            ->post(route('admission.merit-list.generate', $program), [
                'batch_id' => $batch->id,
                'academic_weight' => 20,
                'entrance_exam_weight' => 30,
            ])
            ->assertForbidden();

        $head = $this->admissionHead();
        $this->actingAs($head)
            ->post(route('admission.merit-list.decide', $hiddenEntry), [
                'decision' => 'selected',
            ])
            ->assertRedirect();

        $this->assertSame('selected', $hiddenEntry->fresh()->decision);
        $this->assertSame('pending', $visibleEntry->fresh()->decision);
    }
}
