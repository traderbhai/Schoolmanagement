<?php

namespace Tests\Feature;

use App\Models\AcademicPmcActionDependency;
use App\Models\AcademicPmcActionEvidence;
use App\Models\AcademicPmcActionReminder;
use App\Models\AcademicPmcReviewGovernanceRecord;
use App\Models\AcademicPmcWorkItem;
use App\Models\Batch;
use App\Models\Department;
use App\Models\Program;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcV060Test extends TestCase
{
    use RefreshDatabase;

    private function seedPmcFixture(): User
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'PGDM-26', 'name' => 'PGDM 2026', 'status' => 'active']);
        Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'Term 1', 'is_current' => true]);
        Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT401', 'name' => 'Management Analytics', 'credits' => 3, 'is_active' => true]);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        return User::where('email', 'chair@college.com')->firstOrFail();
    }

    public function test_pmc_minutes_approval_creates_followup_action_and_reminder(): void
    {
        $chair = $this->seedPmcFixture();
        $minutes = AcademicPmcReviewGovernanceRecord::where('record_type', 'minutes')->where('status', 'draft')->firstOrFail();

        $this->actingAs($chair)
            ->patch(route('academics.pmc.meeting-minutes.approve', $minutes), [
                'approval_note' => 'Approved with follow-up action tracking.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('academic_pmc_review_governance_records', [
            'id' => $minutes->id,
            'status' => 'approved',
        ]);

        $action = AcademicPmcWorkItem::where('source_type', 'academic_pmc_review_minutes')
            ->where('source_key', (string) $minutes->id)
            ->firstOrFail();

        $this->assertSame('review_action', $action->work_type);
        $this->assertTrue(AcademicPmcActionReminder::where('work_item_id', $action->id)->where('reminder_type', 'minutes_followup')->exists());
    }

    public function test_pmc_action_dependencies_evidence_and_verified_closure_workflow(): void
    {
        $chair = $this->seedPmcFixture();

        $prerequisite = AcademicPmcWorkItem::create([
            'work_type' => 'review_action',
            'title' => 'Confirm curriculum evidence',
            'description' => 'Prerequisite action for PMC review closure.',
            'owner_user_id' => $chair->id,
            'assigned_by' => $chair->id,
            'priority' => 'high',
            'severity' => 'high',
            'status' => 'open',
            'due_at' => now()->addDay(),
        ]);

        $action = AcademicPmcWorkItem::create([
            'work_type' => 'review_action',
            'title' => 'Close PMC weekly review',
            'description' => 'Closure requires prerequisite and evidence.',
            'owner_user_id' => $chair->id,
            'assigned_by' => $chair->id,
            'priority' => 'high',
            'severity' => 'high',
            'status' => 'open',
            'due_at' => now()->addDays(2),
        ]);

        $this->actingAs($chair)
            ->post(route('academics.pmc.action-governance.dependencies.store', $action), [
                'depends_on_work_item_id' => $prerequisite->id,
                'dependency_type' => 'blocked_by',
                'reason' => 'Evidence must be confirmed first.',
            ])
            ->assertRedirect();

        $dependency = AcademicPmcActionDependency::where('work_item_id', $action->id)->firstOrFail();
        $this->assertSame('active', $dependency->status);
        $this->assertDatabaseHas('academic_pmc_work_items', ['id' => $action->id, 'status' => 'blocked']);

        $this->actingAs($chair)
            ->post(route('academics.pmc.action-governance.evidence.store', $action), [
                'title' => 'PMC evidence note',
                'evidence_type' => 'note',
                'evidence_note' => 'Curriculum evidence and timetable readiness checked.',
            ])
            ->assertRedirect();

        $this->assertTrue(AcademicPmcActionEvidence::where('work_item_id', $action->id)->where('verification_status', 'submitted')->exists());

        $this->actingAs($chair)
            ->patch(route('academics.pmc.action-governance.actions.verify', $action), [
                'status' => 'verified',
                'verification_note' => 'Attempt before dependency resolution.',
            ])
            ->assertStatus(422);

        $dependency->update(['status' => 'resolved', 'resolved_at' => now()]);

        AcademicPmcActionReminder::create([
            'work_item_id' => $action->id,
            'owner_user_id' => $chair->id,
            'reminder_type' => 'closure_due',
            'status' => 'scheduled',
            'due_at' => now()->addDay(),
            'message' => 'Closure verification due.',
        ]);

        $this->actingAs($chair)
            ->patch(route('academics.pmc.action-governance.actions.verify', $action), [
                'status' => 'verified',
                'verification_note' => 'Verified after evidence and dependency resolution.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('academic_pmc_work_items', ['id' => $action->id, 'status' => 'verified']);
        $this->assertTrue(AcademicPmcActionEvidence::where('work_item_id', $action->id)->where('verification_status', 'verified')->exists());
        $this->assertFalse(AcademicPmcActionReminder::where('work_item_id', $action->id)->whereNotIn('status', ['completed', 'cancelled'])->exists());
    }

    public function test_pmc_review_governance_page_renders_v060_registers(): void
    {
        $chair = $this->seedPmcFixture();

        $this->actingAs($chair)
            ->get(route('academics.pmc.review-templates.index'))
            ->assertOk()
            ->assertSee('Minutes Approval')
            ->assertSee('Action Governance And Closure Verification')
            ->assertSee('Dependencies')
            ->assertSee('Reminders And Escalations')
            ->assertSee('Evidence Register');
    }
}
