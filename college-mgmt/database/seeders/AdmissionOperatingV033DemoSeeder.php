<?php

namespace Database\Seeders;

use App\Models\Applicant;
use App\Models\Batch;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdmissionOperatingV033DemoSeeder extends Seeder
{
    public function seedOperationalVolume(Program $program, Batch $batch, User $admHead, User $manager, User $counsellor, User $officer, \App\Models\AdmissionCommunicationTemplate $template, \App\Models\AdmissionAssessmentPanel $basePanel, \App\Models\SelectionSession $session, $applicants, $leads): void
    {
        $subjects = $leads->concat($applicants)->values();
        foreach (range(1, 32) as $i) {
            $subject = $subjects->get(($i - 1) % max(1, $subjects->count()));
            if (! $subject) {
                continue;
            }

            \App\Models\AdmissionReminderSchedule::updateOrCreate(
                ['subject_type' => get_class($subject), 'subject_id' => $subject->id, 'reason' => 'demo_follow_up_' . str_pad((string) $i, 2, '0', STR_PAD_LEFT)],
                [
                    'template_id' => $template->id,
                    'owner_user_id' => $subject->owner_user_id ?? $manager->id,
                    'assigned_to' => $subject->assigned_to ?? $counsellor->id,
                    'target' => $subject instanceof Applicant ? 'applicant' : 'lead',
                    'channel' => $i % 3 === 0 ? 'sms' : 'email',
                    'status' => ['scheduled', 'queued', 'paused', 'escalated'][$i % 4],
                    'priority' => $i % 5 === 0 ? 'high' : 'normal',
                    'due_at' => now()->addHours($i),
                    'repeat_rule' => ['interval_hours' => 24, 'until' => 'blocker_cleared'],
                    'notes' => 'Demo v0.033 follow-up reminder #' . $i,
                    'metadata' => ['demo' => true, 'v' => '0.033'],
                ]
            );

            \App\Models\AdmissionWalkIn::updateOrCreate(
                ['visitor_email' => 'walkin.v033.' . $i . '@demo.local'],
                [
                    'visitor_name' => 'Walk-in Demo ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                    'visitor_phone' => '900005' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                    'guardian_name' => 'Guardian ' . $i,
                    'guardian_phone' => '900006' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                    'program_id' => $program->id,
                    'batch_id' => $batch->id,
                    'purpose' => $i % 2 === 0 ? 'campus_visit' : 'admission_enquiry',
                    'assigned_counsellor_id' => $i % 2 === 0 ? $counsellor->id : $officer->id,
                    'status' => $i % 4 === 0 ? 'converted' : 'open',
                    'outcome' => $i % 4 === 0 ? 'converted_to_lead' : 'follow_up_required',
                    'visited_at' => now()->subDays($i % 12)->setTime(10 + ($i % 6), 0),
                    'next_followup_at' => now()->addDays(($i % 7) + 1),
                    'notes' => 'Seeded v0.033 walk-in volume for pagination and filtering.',
                    'created_by' => $admHead->id,
                ]
            );

            $target = $subjects->get($i % max(1, $subjects->count()));
            if ($target) {
                \App\Models\AdmissionManagerReview::updateOrCreate(
                    ['reviewable_type' => get_class($target), 'reviewable_id' => $target->id, 'review_type' => 'v033_quality_audit_' . str_pad((string) $i, 2, '0', STR_PAD_LEFT)],
                    [
                        'status' => $i % 5 === 0 ? 'resolved' : 'pending',
                        'severity' => $i % 4 === 0 ? 'high' : 'normal',
                        'assigned_manager_id' => $manager->id,
                        'finding' => 'Demo v0.033 operational review #' . $i,
                        'action_required' => 'Validate follow-up quality and next action.',
                        'due_at' => now()->addDays($i % 10),
                        'metadata' => ['demo' => true, 'v' => '0.033'],
                    ]
                );
            }

            \App\Models\AdmissionAssessmentPanel::updateOrCreate(
                ['name' => 'v0.033 Interview Panel ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT)],
                [
                    'panel_type' => ['personal_interview', 'group_discussion', 'case_analysis', 'screening_call'][$i % 4],
                    'program_id' => $program->id,
                    'batch_id' => $batch->id,
                    'selection_session_id' => $i <= 10 ? $session->id : null,
                    'capacity' => 8 + ($i % 8),
                    'venue' => 'Assessment Room ' . (($i % 6) + 1),
                    'scheduled_at' => now()->addDays($i % 20)->setTime(9 + ($i % 6), 30),
                    'status' => $i % 6 === 0 ? 'completed' : 'scheduled',
                    'created_by' => $admHead->id,
                    'metadata' => ['demo' => true, 'v' => '0.033', 'base_panel_id' => $basePanel->id],
                ]
            );
        }
    }
}
