<?php

namespace Database\Seeders;

use App\Models\Applicant;
use App\Models\Batch;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdmissionOperatingV036DemoSeeder extends Seeder
{
    public function seedAssessmentAndCounsellorOps(Program $program, Batch $batch, User $admHead, User $manager, User $counsellor, User $officer, \App\Models\AdmissionCommunicationTemplate $template, \App\Models\AdmissionAssessmentPanel $basePanel, \App\Models\SelectionSession $session, $applicants, $leads): void
    {
        $types = [
            'group_discussion' => ['Group Discussion Rubric', ['Content Quality', 'Listening', 'Leadership', 'Team Conduct']],
            'personal_interview' => ['Personal Interview Rubric', ['Communication', 'Motivation', 'Academic Fit', 'Career Clarity']],
            'case_analysis' => ['Case Analysis Rubric', ['Problem Structure', 'Quantitative Reasoning', 'Recommendation', 'Presentation']],
            'written_ability_test' => ['WAT Rubric', ['Structure', 'Argument', 'Language', 'Originality']],
            'aptitude_test' => ['Aptitude Test Rubric', ['Quantitative', 'Logical', 'Verbal', 'Accuracy']],
            'presentation' => ['Presentation Rubric', ['Storyline', 'Evidence', 'Delivery', 'Q&A']],
            'portfolio_review' => ['Portfolio Review Rubric', ['Relevance', 'Depth', 'Reflection', 'Potential']],
            'screening_call' => ['Screening Call Rubric', ['Intent', 'Eligibility', 'Responsiveness', 'Fit']],
        ];

        $rubrics = collect();
        foreach ($types as $type => [$name, $criteria]) {
            $rubric = \App\Models\AdmissionAssessmentRubric::updateOrCreate(
                ['assessment_type' => $type, 'program_id' => $program->id, 'version' => 1],
                [
                    'name' => $name,
                    'batch_id' => $batch->id,
                    'minimum_score' => 55,
                    'recommendation_options' => ['recommended', 'waitlist', 'not_recommended'],
                    'evaluator_instructions' => 'Use the full scale, add evidence-based comments, and submit final only after review.',
                    'is_active' => true,
                    'created_by' => $admHead->id,
                ]
            );
            foreach ($criteria as $index => $criterion) {
                \App\Models\AdmissionAssessmentRubricCriterion::updateOrCreate(
                    ['rubric_id' => $rubric->id, 'name' => $criterion],
                    [
                        'description' => 'Seeded v0.036 assessment criterion for ' . str_replace('_', ' ', $type) . '.',
                        'max_score' => 25,
                        'weight' => $index === 0 ? 1.2 : 1,
                        'requires_comment' => in_array($index, [0, 3], true),
                        'sort_order' => $index + 1,
                    ]
                );
            }
            $rubrics->put($type, $rubric->fresh('criteria'));
        }

        $basePanel->update(['rubric_id' => $rubrics->get('case_analysis')?->id, 'readiness_status' => 'ready']);

        $artifactRows = [
            ['group_discussion', 'AI in Indian Higher Education', 'GD-1', 'Moderator should track listening and leadership balance.'],
            ['case_analysis', 'EduFin Admissions Growth Case', 'CASE-1', 'Candidates get 30 minutes prep and 5 minutes presentation.'],
            ['personal_interview', 'Career Goals PI', 'PI-1', 'Structured interview across motivation, fit, and communication.'],
        ];
        foreach ($artifactRows as [$type, $title, $group, $notes]) {
            \App\Models\AdmissionAssessmentArtifact::updateOrCreate(
                ['selection_session_id' => $session->id, 'panel_id' => $basePanel->id, 'artifact_type' => $type, 'title' => $title],
                [
                    'topic' => $title,
                    'group_number' => $group,
                    'artifact_url' => 'https://demo.local/admission/artifacts/' . $group,
                    'prep_minutes' => $type === 'case_analysis' ? 30 : 10,
                    'submission_due_at' => now()->addDays(2)->setTime(12, 0),
                    'moderator_notes' => $notes,
                    'observer_notes' => 'Seeded v0.036 observer note.',
                    'metadata' => ['demo' => true],
                ]
            );
        }

        $states = ['invited', 'confirmed', 'checked_in', 'waiting', 'in_progress', 'completed', 'no_show', 'rescheduled'];
        $applicants->take(8)->values()->each(function (Applicant $applicant, int $index) use ($states, $session, $basePanel, $counsellor, $officer, $manager, $rubrics) {
            $state = $states[$index % count($states)];
            \App\Models\SessionApplicant::updateOrCreate(
                ['selection_session_id' => $session->id, 'applicant_id' => $applicant->id],
                [
                    'assigned_at' => now()->subDays(2),
                    'attendance_status' => in_array($state, ['checked_in', 'waiting', 'in_progress', 'completed'], true) ? 'present' : ($state === 'no_show' ? 'absent' : 'pending'),
                    'lifecycle_status' => $state,
                    'checked_in_at' => $state === 'checked_in' ? now()->subHour() : null,
                    'completed_at' => $state === 'completed' ? now()->subMinutes(20) : null,
                    'panel_number' => 1,
                ]
            );

            $assignment = \App\Models\AdmissionAssessmentPanelAssignment::updateOrCreate(
                ['panel_id' => $basePanel->id, 'applicant_id' => $applicant->id],
                [
                    'selection_session_id' => $session->id,
                    'evaluator_user_id' => $index % 2 === 0 ? $counsellor->id : $officer->id,
                    'attendance_status' => in_array($state, ['checked_in', 'waiting', 'in_progress', 'completed'], true) ? 'present' : ($state === 'no_show' ? 'absent' : 'pending'),
                    'lifecycle_status' => $state,
                    'score_status' => $state === 'completed' ? 'finalized' : ($index % 3 === 0 ? 'draft' : 'pending'),
                    'recommendation' => $state === 'completed' ? 'recommended' : null,
                    'aggregate_score' => $state === 'completed' ? 78 : null,
                    'variance_score' => $index === 5 ? 24 : 8,
                    'variance_flag' => $index === 5,
                    'score_locked_at' => $state === 'completed' ? now()->subMinutes(25) : null,
                    'finalized_at' => $state === 'completed' ? now()->subMinutes(25) : null,
                    'metadata' => ['demo' => true, 'v' => '0.036'],
                ]
            );

            \App\Models\AdmissionAssessmentLifecycleEvent::updateOrCreate(
                ['assignment_id' => $assignment->id, 'to_status' => $state],
                [
                    'selection_session_id' => $session->id,
                    'panel_id' => $basePanel->id,
                    'applicant_id' => $applicant->id,
                    'from_status' => 'invited',
                    'reason' => 'Seeded v0.036 lifecycle state',
                    'actor_user_id' => $manager->id,
                    'metadata' => ['demo' => true],
                ]
            );

            if (in_array($assignment->score_status, ['draft', 'finalized'], true) && ($rubric = $rubrics->get('case_analysis'))) {
                foreach ($rubric->criteria as $criterion) {
                    \App\Models\AdmissionEvaluatorScore::updateOrCreate(
                        ['assignment_id' => $assignment->id, 'criterion_id' => $criterion->id, 'evaluator_user_id' => $assignment->evaluator_user_id],
                        [
                            'rubric_id' => $rubric->id,
                            'score' => min($criterion->max_score, 16 + $index),
                            'max_score' => $criterion->max_score,
                            'weighted_score' => (16 + $index) * $criterion->weight,
                            'comment' => 'Seeded v0.036 evaluator comment for ' . $criterion->name,
                            'status' => $assignment->score_status,
                            'submitted_at' => $assignment->score_status === 'finalized' ? now()->subMinutes(30) : null,
                            'locked_at' => $assignment->score_status === 'finalized' ? now()->subMinutes(25) : null,
                            'metadata' => ['demo' => true],
                        ]
                    );
                }
            }

            if ($state === 'rescheduled') {
                \App\Models\AdmissionAssessmentReschedule::updateOrCreate(
                    ['assignment_id' => $assignment->id, 'applicant_id' => $applicant->id],
                    [
                        'from_session_id' => $session->id,
                        'to_session_id' => $session->id,
                        'old_scheduled_at' => now()->subDay(),
                        'new_scheduled_at' => now()->addDays(3),
                        'reason' => 'Applicant requested another slot.',
                        'status' => 'approved',
                        'requested_by' => $manager->id,
                        'metadata' => ['demo' => true],
                    ]
                );
            }
        });

        foreach ([
            ['Program Pitch', 'program_pitch', null],
            ['Fee And Scholarship Discussion', 'fee_scholarship', null],
            ['Document Checklist Follow-up', 'document_checklist', 'submitted'],
            ['Assessment Preparation', 'assessment_preparation', 'shortlisted'],
            ['Parent Conversation', 'parent_conversation', null],
            ['Objection Handling', 'objection_handling', null],
        ] as [$name, $type, $stage]) {
            $playbook = \App\Models\AdmissionCounsellorPlaybook::updateOrCreate(
                ['name' => $name, 'playbook_type' => $type, 'program_id' => $program->id],
                ['stage' => $stage, 'is_active' => true, 'created_by' => $admHead->id]
            );
            foreach ([
                ['Clarify need', 'Ask the candidate or parent what they need before deciding.', 'ask_discovery'],
                ['Resolve blocker', 'Address fee, document, assessment, or decision-maker blocker.', 'resolve_blocker'],
                ['Commit next step', 'Send the checklist and schedule the next follow-up.', 'schedule_follow_up'],
            ] as $index => [$title, $body, $action]) {
                \App\Models\AdmissionCounsellorPlaybookStep::updateOrCreate(
                    ['playbook_id' => $playbook->id, 'title' => $title],
                    ['body' => $body, 'suggested_action' => $action, 'sort_order' => $index + 1]
                );
            }
        }

        $subjects = $leads->merge($applicants->take(5));
        foreach ($subjects as $index => $subject) {
            \App\Models\AdmissionCounsellingProfile::updateOrCreate(
                ['subject_type' => get_class($subject), 'subject_id' => $subject->id],
                [
                    'preferred_program_id' => $program->id,
                    'budget_sensitivity' => ['low', 'medium', 'high'][$index % 3],
                    'scholarship_need' => $index % 2 === 0,
                    'hostel_interest' => $index % 3 === 0,
                    'transport_interest' => $index % 4 === 0,
                    'parent_decision_maker' => ['Father', 'Mother', 'Guardian'][$index % 3],
                    'key_objection' => ['fee', 'location', 'placement', 'eligibility'][$index % 4],
                    'lost_reason' => $index % 5 === 0 ? 'competitor_selected' : null,
                    'competitor_considered' => 'Demo Business School',
                    'parent_spoken' => $index % 2 === 0,
                    'last_parent_contacted_at' => now()->subDays($index + 1),
                    'updated_by' => $counsellor->id,
                    'metadata' => ['demo' => true, 'v' => '0.036'],
                ]
            );

            \App\Models\AdmissionConversationEvent::updateOrCreate(
                ['subject_type' => get_class($subject), 'subject_id' => $subject->id, 'event_type' => 'parent_call'],
                [
                    'title' => 'Parent conversation logged',
                    'body' => 'Discussed fees, scholarship need, and assessment preparation.',
                    'occurred_at' => now()->subHours($index + 2),
                    'actor_user_id' => $counsellor->id,
                    'source_type' => 'seeded_demo',
                    'source_id' => $index,
                    'metadata' => ['demo' => true],
                ]
            );
        }

        foreach (['Assessment Invite Email', 'Assessment Reminder WhatsApp', 'Assessment Reschedule Notice', 'Assessment No-show Follow-up', 'Assessment Result Next Step'] as $index => $name) {
            \App\Models\AdmissionCommunicationTemplate::updateOrCreate(
                ['name' => $name],
                [
                    'channel' => $index === 0 ? 'email' : 'whatsapp',
                    'purpose' => 'assessment_' . $index,
                    'subject' => 'Assessment update for {{ program }}',
                    'body' => 'Hello {{ name }}, assessment update: {{ next_action }}. Counsellor: {{ counsellor }}.',
                    'variables' => ['name', 'program', 'next_action', 'counsellor'],
                    'is_active' => true,
                    'created_by' => $admHead->id,
                ]
            );
        }
    }
}
