<?php

namespace Tests\Feature;

use App\Models\AdmissionAssessmentPanelAssignment;
use App\Models\AdmissionAssessmentRubric;
use App\Models\AdmissionCallLog;
use App\Models\AdmissionCommunicationLog;
use App\Models\AdmissionReminderSchedule;
use App\Models\Applicant;
use App\Models\Lead;
use App\Models\User;
use App\Services\AdmissionConversationTimelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionOsV036Test extends TestCase
{
    use RefreshDatabase;

    public function test_v036_seeded_pages_render_assessment_and_counsellor_operations(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);
        $head = User::where('email', 'head@college.com')->firstOrFail();
        $counsellor = User::where('email', 'counsellor@college.com')->firstOrFail();

        $this->actingAs($head)
            ->get(route('admission.assessment-control-room.index'))
            ->assertOk()
            ->assertSee('Assessment Control Room')
            ->assertSee('Panel Readiness')
            ->assertSee('Candidate Lifecycle')
            ->assertSee('Pending Score Queue')
            ->assertDontSee('QueryException');

        $this->actingAs($head)
            ->get(route('admission.assessment-rubrics.index'))
            ->assertOk()
            ->assertSee('Assessment Rubrics')
            ->assertSee('Group Discussion Rubric')
            ->assertSee('Case Analysis Rubric');

        $this->actingAs($counsellor)
            ->get(route('admission.evaluator-scoring.index'))
            ->assertOk()
            ->assertSee('Evaluator Scoring Workspace')
            ->assertDontSee('QueryException');

        $this->actingAs($counsellor)
            ->get(route('admission.counsellor-desk.index'))
            ->assertOk()
            ->assertSee('Counsellor Operating Desk')
            ->assertSee('Next Best Calls')
            ->assertSee('Conversation Timeline')
            ->assertSee('Playbooks');

        $this->actingAs($head)
            ->get(route('admission.counsellor-playbooks.index'))
            ->assertOk()
            ->assertSee('Counsellor Playbooks')
            ->assertSee('Assessment Preparation');
    }

    public function test_v036_evaluator_can_save_finalize_and_lifecycle_assignment(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);
        $counsellor = User::where('email', 'counsellor@college.com')->firstOrFail();
        $assignment = AdmissionAssessmentPanelAssignment::with('panel.rubric.criteria')
            ->where('evaluator_user_id', $counsellor->id)
            ->whereHas('panel.rubric.criteria')
            ->whereNotIn('score_status', ['finalized', 'overridden'])
            ->firstOrFail();
        $criteria = $assignment->panel->rubric->criteria->mapWithKeys(fn ($criterion) => [
            $criterion->id => ['score' => min(20, $criterion->max_score), 'comment' => 'Strong evidence for ' . $criterion->name],
        ])->all();

        $this->actingAs($counsellor)
            ->post(route('admission.evaluator-scoring.save', $assignment), [
                'criteria' => $criteria,
                'recommendation' => 'recommended',
                'finalize' => 0,
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('applicant_scores', [
            'applicant_id' => $assignment->applicant_id,
            'scored_by' => $counsellor->id,
            'score_status' => 'draft',
        ]);

        $this->actingAs($counsellor)
            ->post(route('admission.evaluator-scoring.save', $assignment), [
                'criteria' => $criteria,
                'recommendation' => 'recommended',
                'finalize' => 1,
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('admission_assessment_panel_assignments', [
            'id' => $assignment->id,
            'score_status' => 'finalized',
            'recommendation' => 'recommended',
        ]);

        $this->actingAs($counsellor)
            ->post(route('admission.evaluator-scoring.lifecycle', $assignment), [
                'lifecycle_status' => 'completed',
                'reason' => 'Candidate completed PI',
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('admission_assessment_lifecycle_events', [
            'assignment_id' => $assignment->id,
            'to_status' => 'completed',
        ]);
    }

    public function test_v036_required_rubric_comments_are_enforced(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);
        $counsellor = User::where('email', 'counsellor@college.com')->firstOrFail();
        $assignment = AdmissionAssessmentPanelAssignment::with('panel.rubric.criteria')
            ->where('evaluator_user_id', $counsellor->id)
            ->whereHas('panel.rubric.criteria')
            ->whereNotIn('score_status', ['finalized', 'overridden'])
            ->firstOrFail();
        $criteria = $assignment->panel->rubric->criteria->mapWithKeys(fn ($criterion) => [
            $criterion->id => ['score' => 10, 'comment' => ''],
        ])->all();

        $this->actingAs($counsellor)
            ->from(route('admission.evaluator-scoring.index'))
            ->post(route('admission.evaluator-scoring.save', $assignment), [
                'criteria' => $criteria,
                'recommendation' => 'recommended',
                'finalize' => 1,
            ])
            ->assertSessionHasErrors();
    }

    public function test_v036_conversation_timeline_aggregates_operational_events(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);
        $head = User::where('email', 'head@college.com')->firstOrFail();
        $applicant = Applicant::whereHas('communicationLogs')->whereHas('callLogs')->firstOrFail();

        $events = app(AdmissionConversationTimelineService::class)->forSubject($applicant, 50);
        $this->assertTrue($events->where('type', 'communication')->isNotEmpty());
        $this->assertTrue($events->where('type', 'call')->isNotEmpty());
        $this->assertTrue($events->whereIn('type', ['assessment', 'parent_call', 'reminder'])->isNotEmpty());

        $this->actingAs($head)
            ->get(route('admission.conversation-timeline.show', ['applicant', $applicant->id]))
            ->assertOk()
            ->assertSee('Conversation Timeline')
            ->assertDontSee('QueryException');
    }

    public function test_v036_counsellor_quick_actions_still_create_real_records(): void
    {
        $this->seed(\Database\Seeders\MasterDemoSeeder::class);
        $counsellor = User::where('email', 'counsellor@college.com')->firstOrFail();
        $lead = Lead::where('assigned_to', $counsellor->id)->firstOrFail();

        $this->actingAs($counsellor)
            ->post(route('admission.call-queue.log'), [
                'subject_type' => 'lead',
                'subject_id' => $lead->id,
                'phone' => $lead->phone,
                'disposition' => 'connected',
                'duration_seconds' => 120,
                'notes' => 'v0.036 counsellor desk call',
            ])
            ->assertRedirect();

        $this->actingAs($counsellor)
            ->post(route('admission.reminders.store'), [
                'subject_type' => 'lead',
                'subject_id' => $lead->id,
                'reason' => 'assessment_preparation',
                'channel' => 'email',
                'priority' => 'high',
                'due_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'notes' => 'v0.036 counsellor desk reminder',
            ])
            ->assertRedirect();

        $this->assertTrue(AdmissionCallLog::where('notes', 'v0.036 counsellor desk call')->exists());
        $this->assertTrue(AdmissionReminderSchedule::where('notes', 'v0.036 counsellor desk reminder')->exists());
        $this->assertTrue(AdmissionCommunicationLog::count() > 0);
        $this->assertTrue(AdmissionAssessmentRubric::where('assessment_type', 'group_discussion')->exists());
    }
}
