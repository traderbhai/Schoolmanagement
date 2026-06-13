<?php

namespace Database\Seeders;

use App\Models\Applicant;
use App\Models\Batch;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdmissionOperatingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $program = Program::where('code', 'PGDM')->first() ?: Program::first();
        $batch = Batch::where('code', 'PGDM-24')->first() ?: Batch::first();
        $admHead = User::where('email', 'head@college.com')->first();
        $officer = User::where('email', 'officer@college.com')->first();

        if (!$program || !$batch || !$admHead || !$officer) {
            $this->command?->warn('Admission operating demo seeder skipped: core demo users/program/batch missing.');
            return;
        }

        foreach (['admission_manager', 'admission_counsellor', 'admission_telecaller'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        $manager = $this->user('admission.manager@college.com', 'Neha Bansal', 'admission_manager');
        $counsellor = $this->user('counsellor@college.com', 'Amit Counsellor', 'admission_counsellor');
        $telecaller = $this->user('telecaller@college.com', 'Kavya Telecaller', 'admission_telecaller');
        $partnerContact = $this->user('partner.citychannel@demo.edu', 'City Channel Partner', null);

        $partner = \App\Models\AdmissionPartner::updateOrCreate(
            ['name' => 'City Admissions Channel'],
            [
                'type' => 'agency',
                'contact_user_id' => $partnerContact->id,
                'contact_name' => 'Imran Qureshi',
                'contact_email' => 'partner.citychannel@demo.edu',
                'contact_phone' => '9000011111',
                'allowed_program_ids' => [$program->id],
                'status' => 'approved',
                'commission_status' => 'pending_policy',
                'approved_by' => $admHead->id,
                'approved_at' => now()->subDays(12),
            ]
        );

        $leads = collect([
            $this->lead('Nisha Kapoor', 'nisha.kapoor@lead.demo', '9000001001', 'web_form', 'new', 'urgent', $telecaller->id, $program, $admHead, null, 'Call within 2 hours'),
            $this->lead('Vikram Malhotra', 'vikram.malhotra@lead.demo', '9000001002', 'referral', 'interested', 'high', $counsellor->id, $program, $admHead, null, 'Send scholarship details'),
            $this->lead('Farah Khan', 'farah.khan@lead.demo', '9000001003', 'agent', 'contacted', 'normal', $manager->id, $program, $admHead, $partner, 'Manager to delegate'),
            $this->lead('Duplicate Nisha', 'duplicate.nisha@lead.demo', '9000001001', 'social_media', 'new', 'normal', null, $program, $admHead, null, 'Review duplicate'),
        ]);

        $emailTemplate = \App\Models\AdmissionCommunicationTemplate::updateOrCreate(
            ['name' => 'Application Completion Reminder'],
            [
                'channel' => 'email',
                'purpose' => 'incomplete_application',
                'subject' => 'Complete your {{ program }} application',
                'body' => 'Hello {{ name }}, your {{ program }} application needs attention. Next step: {{ next_action }}.',
                'variables' => ['name', 'program', 'next_action'],
                'is_active' => true,
                'created_by' => $admHead->id,
            ]
        );

        $whatsAppTemplate = \App\Models\AdmissionCommunicationTemplate::updateOrCreate(
            ['name' => 'Offer Deadline WhatsApp'],
            [
                'channel' => 'whatsapp',
                'purpose' => 'offer_deadline',
                'body' => 'Hi {{ name }}, please complete your admission step before {{ deadline }}.',
                'variables' => ['name', 'deadline'],
                'is_active' => true,
                'created_by' => $admHead->id,
            ]
        );

        foreach ($leads->take(3) as $lead) {
            $this->communicationFor($lead, $emailTemplate, $officer, $lead->email, 'mail');
            $this->callFor($lead, $telecaller, $lead->phone, $lead->status === 'interested' ? 'interested' : 'connected');
            $this->scoreFor($lead, $admHead);
        }

        $journey = \App\Models\AdmissionJourney::updateOrCreate(
            ['name' => 'PGDM Guided Admission Journey', 'program_id' => $program->id],
            ['batch_id' => $batch->id, 'is_active' => true, 'created_by' => $admHead->id]
        );
        $journeyVersion = \App\Models\AdmissionJourneyVersion::updateOrCreate(
            ['journey_id' => $journey->id, 'version' => 1],
            [
                'stages' => ['draft', 'submitted', 'under_review', 'shortlisted', 'selected', 'enrolled'],
                'documents' => ['photo', 'graduation_marksheet', 'entrance_scorecard', 'id_proof'],
                'fee_milestones' => ['registration_fee', 'first_installment'],
                'session_rules' => ['wat_required' => true, 'gd_pi_required' => true],
                'offer_rules' => ['validity_days' => 7],
                'enrollment_blockers' => ['registration_fee', 'verified_documents'],
                'applicant_instructions' => 'Complete profile, upload mandatory documents, pay registration fee, attend selection rounds, and accept offer before deadline.',
                'is_published' => true,
                'published_by' => $admHead->id,
                'published_at' => now()->subDays(10),
            ]
        );

        $demoApplicants = Applicant::where('program_id', $program->id)->with('user')->get();

        $demoApplicants->each(function (Applicant $applicant, int $index) use ($batch, $journeyVersion, $manager, $counsellor, $officer, $admHead, $whatsAppTemplate) {
            $handlerId = $index % 2 === 0 ? $counsellor->id : $officer->id;
            $applicant->update([
                'batch_id' => $applicant->batch_id ?: $batch->id,
                'journey_version_id' => $journeyVersion->id,
                'assigned_to' => $applicant->assigned_to ?: $handlerId,
                'owner_user_id' => $applicant->owner_user_id ?: $manager->id,
                'current_handler_user_id' => $applicant->current_handler_user_id ?: $handlerId,
                'assigned_by' => $applicant->assigned_by ?: $admHead->id,
                'assignment_reason' => $applicant->assignment_reason ?: 'Demo applicant counselling workload',
                'assignment_mode' => $applicant->assignment_mode ?: 'seeded_demo',
                'assigned_at' => $applicant->assigned_at ?: now()->subDays(3),
                'last_activity_at' => now()->subDays($index + 1),
                'sla_due_at' => now()->addDays(max(1, 5 - $index)),
                'next_action' => $applicant->next_action ?: $this->nextActionFor($applicant),
            ]);

            \App\Models\AdmissionAssignmentEvent::firstOrCreate(
                ['subject_type' => Applicant::class, 'subject_id' => $applicant->id, 'to_user_id' => $handlerId, 'mode' => 'seeded_demo'],
                ['assigned_by' => $admHead->id, 'reason' => 'Demo applicant assignment', 'metadata' => ['stage' => $applicant->status]]
            );

            $this->communicationFor($applicant, $whatsAppTemplate, $officer, $applicant->personal_data['phone'] ?? null, 'mock_whatsapp', $index % 3 === 0 ? 'queued' : 'sent');
            $this->callFor($applicant, User::find($handlerId), $applicant->personal_data['phone'] ?? null, 'connected');
        });

        $cadenceRule = \App\Models\AdmissionCadenceRule::updateOrCreate(
            ['name' => 'No-response follow-up cadence'],
            [
                'target_type' => 'lead',
                'reason' => 'no_response_follow_up',
                'channel' => 'whatsapp',
                'template_id' => $whatsAppTemplate->id,
                'repeat_rule' => ['initial_delay_hours' => 4, 'interval_hours' => 24, 'message' => 'Repeat until candidate responds or lead is marked lost.'],
                'max_attempts' => 4,
                'escalate_after_attempts' => 2,
                'is_active' => true,
                'created_by' => $admHead->id,
            ]
        );

        foreach ($leads->take(3) as $index => $lead) {
            \App\Models\AdmissionReminderSchedule::updateOrCreate(
                ['subject_type' => \App\Models\Lead::class, 'subject_id' => $lead->id, 'reason' => $index === 0 ? 'no_response_follow_up' : 'application_completion'],
                [
                    'cadence_rule_id' => $cadenceRule->id,
                    'template_id' => $index === 0 ? $whatsAppTemplate->id : $emailTemplate->id,
                    'owner_user_id' => $lead->owner_user_id,
                    'assigned_to' => $lead->assigned_to,
                    'target' => 'lead',
                    'channel' => $index === 0 ? 'whatsapp' : 'email',
                    'status' => $index === 2 ? 'escalated' : 'scheduled',
                    'priority' => $lead->priority,
                    'due_at' => now()->addHours($index + 2),
                    'escalated_to' => $index === 2 ? $manager->id : null,
                    'escalated_at' => $index === 2 ? now()->subHour() : null,
                    'repeat_rule' => ['interval_hours' => 24, 'until' => 'blocker_cleared'],
                    'notes' => $lead->next_action,
                    'metadata' => ['demo' => true],
                ]
            );
        }

        foreach ($demoApplicants->take(3) as $index => $applicant) {
            \App\Models\AdmissionReminderSchedule::updateOrCreate(
                ['subject_type' => Applicant::class, 'subject_id' => $applicant->id, 'reason' => $index === 0 ? 'document_blocker' : 'offer_deadline'],
                [
                    'template_id' => $whatsAppTemplate->id,
                    'owner_user_id' => $applicant->owner_user_id,
                    'assigned_to' => $applicant->assigned_to,
                    'target' => 'applicant',
                    'channel' => 'whatsapp',
                    'status' => 'scheduled',
                    'priority' => $applicant->priority ?? 'normal',
                    'due_at' => now()->addDays($index + 1),
                    'repeat_rule' => ['interval_hours' => 24, 'until' => 'blocker_cleared'],
                    'notes' => $applicant->next_action,
                    'metadata' => ['demo' => true],
                ]
            );
        }

        $caseStep = \App\Models\SelectionProcessStep::updateOrCreate(
            ['program_id' => $program->id, 'name' => 'Case Analysis'],
            [
                'name' => 'Case Analysis',
                'type' => 'pi',
                'step_order' => 20,
                'max_score' => 50,
                'weightage' => 25,
                'instructions' => 'Evaluate analytical clarity, structure, communication, and recommendation quality.',
                'is_active' => true,
            ]
        );

        foreach ([
            ['Analytical Structure', 20, 1],
            ['Communication Clarity', 15, 2],
            ['Recommendation Quality', 15, 3],
        ] as [$name, $score, $order]) {
            \App\Models\ScoringParameter::updateOrCreate(
                ['selection_process_step_id' => $caseStep->id, 'name' => $name],
                ['max_score' => $score, 'description' => 'Demo v0.031 assessment scoring parameter.', 'sort_order' => $order]
            );
        }

        $session = \App\Models\SelectionSession::updateOrCreate(
            ['selection_process_step_id' => $caseStep->id, 'session_name' => 'PGDM Case Analysis Panel A'],
            [
                'program_id' => $program->id,
                'batch_id' => $batch->id,
                'scheduled_date' => today()->addDays(2),
                'start_time' => '10:00',
                'end_time' => '12:00',
                'venue' => 'Admissions Assessment Room 1',
                'max_candidates' => 20,
                'instructions' => 'Candidates receive a business case and present recommendations to the panel.',
                'status' => 'scheduled',
                'conducted_by' => $manager->id,
                'created_by' => $admHead->id,
            ]
        );

        $panel = \App\Models\AdmissionAssessmentPanel::updateOrCreate(
            ['name' => 'Case Analysis Panel A', 'selection_session_id' => $session->id],
            [
                'panel_type' => 'case_analysis',
                'program_id' => $program->id,
                'batch_id' => $batch->id,
                'capacity' => 12,
                'venue' => 'Admissions Assessment Room 1',
                'scheduled_at' => now()->addDays(2)->setTime(10, 0),
                'status' => 'scheduled',
                'created_by' => $admHead->id,
                'metadata' => ['demo' => true],
            ]
        );

        foreach ([[$manager, true], [$counsellor, false], [$officer, false]] as [$evaluator, $isChair]) {
            \App\Models\AdmissionAssessmentPanelMember::updateOrCreate(
                ['panel_id' => $panel->id, 'user_id' => $evaluator->id],
                ['role' => $isChair ? 'chair' : 'evaluator', 'is_chair' => $isChair]
            );
        }

        foreach ($demoApplicants->take(4)->values() as $index => $applicant) {
            \App\Models\SessionApplicant::updateOrCreate(
                ['selection_session_id' => $session->id, 'applicant_id' => $applicant->id],
                ['assigned_at' => now()->subDay(), 'attendance_status' => $index === 0 ? 'present' : 'pending', 'panel_number' => 1]
            );

            \App\Models\AdmissionAssessmentPanelAssignment::updateOrCreate(
                ['panel_id' => $panel->id, 'applicant_id' => $applicant->id],
                [
                    'selection_session_id' => $session->id,
                    'evaluator_user_id' => $index % 2 === 0 ? $counsellor->id : $officer->id,
                    'attendance_status' => $index === 0 ? 'present' : 'pending',
                    'score_status' => $index === 0 ? 'finalized' : 'pending',
                    'recommendation' => $index === 0 ? 'recommended' : null,
                    'score_locked_at' => $index === 0 ? now()->subHours(6) : null,
                    'finalized_at' => $index === 0 ? now()->subHours(6) : null,
                    'metadata' => ['demo' => true],
                ]
            );

            if ($index === 0) {
                \App\Models\ApplicantScore::updateOrCreate(
                    ['applicant_id' => $applicant->id, 'selection_session_id' => $session->id],
                    [
                        'selection_process_step_id' => $caseStep->id,
                        'scored_by' => $counsellor->id,
                        'parameter_scores' => ['Analytical Structure' => 17, 'Communication Clarity' => 13, 'Recommendation Quality' => 12],
                        'total_score' => 42,
                        'max_possible_score' => 50,
                        'percentage' => 84,
                        'remarks' => 'Clear case framing and strong recommendation.',
                        'is_final' => true,
                        'score_status' => 'finalized',
                        'locked_at' => now()->subHours(6),
                        'locked_by' => $manager->id,
                        'recommendation' => 'recommended',
                    ]
                );
            }
        }

        foreach ([
            ['Rahul Sharma', '9000040001', 'rahul.walkin@demo.local', $counsellor, 'converted', now()->subDays(1), now()->addDay()],
            ['Meera Iyer', '9000040002', 'meera.walkin@demo.local', $officer, 'open', now()->subHours(5), now()->addDays(2)],
        ] as [$name, $phone, $email, $handler, $status, $visitedAt, $followupAt]) {
            \App\Models\AdmissionWalkIn::updateOrCreate(
                ['visitor_email' => $email],
                [
                    'visitor_name' => $name,
                    'visitor_phone' => $phone,
                    'guardian_name' => str_contains($name, 'Rahul') ? 'Suresh Sharma' : 'Lakshmi Iyer',
                    'guardian_phone' => '9000049999',
                    'program_id' => $program->id,
                    'batch_id' => $batch->id,
                    'purpose' => 'admission_enquiry',
                    'assigned_counsellor_id' => $handler->id,
                    'status' => $status,
                    'outcome' => $status === 'converted' ? 'converted_to_lead' : 'follow_up_required',
                    'visited_at' => $visitedAt,
                    'next_followup_at' => $followupAt,
                    'notes' => 'Demo walk-in visit with guardian discussion and program counselling.',
                    'created_by' => $admHead->id,
                ]
            );
        }

        $reviewTargets = [
            [$leads->get(1), 'call_log_audit', 'Check call outcome quality and next action clarity.', 'Confirm the counsellor captured parent objections.'],
            [$leads->last(), 'duplicate_review', 'Duplicate phone detected across digital and social sources.', 'Merge or mark duplicate after verification.'],
            [$demoApplicants->first(), 'assessment_score_override_review', 'Score finalization is awaiting manager quality check.', 'Review panel scoring consistency.'],
        ];

        foreach ($reviewTargets as [$target, $type, $finding, $action]) {
            if (!$target) {
                continue;
            }
            \App\Models\AdmissionManagerReview::updateOrCreate(
                ['reviewable_type' => get_class($target), 'reviewable_id' => $target->id, 'review_type' => $type],
                [
                    'status' => 'pending',
                    'severity' => $type === 'duplicate_review' ? 'high' : 'normal',
                    'assigned_manager_id' => $manager->id,
                    'finding' => $finding,
                    'action_required' => $action,
                    'due_at' => now()->addDays(2),
                    'metadata' => ['demo' => true],
                ]
            );
        }

        $this->seedV033OperationalVolume($program, $batch, $admHead, $manager, $counsellor, $officer, $emailTemplate, $panel, $session, $demoApplicants, $leads);

        \App\Models\AdmissionPipelineBoard::updateOrCreate(
            ['object_type' => 'lead', 'is_default' => true],
            ['name' => 'Lead Pipeline', 'columns' => ['new', 'contacted', 'interested', 'not_interested', 'converted'], 'filters' => ['program_id' => $program->id], 'created_by' => $admHead->id]
        );
        \App\Models\AdmissionPipelineBoard::updateOrCreate(
            ['object_type' => 'applicant', 'is_default' => true],
            ['name' => 'Applicant Pipeline', 'columns' => ['draft', 'submitted', 'under_review', 'shortlisted', 'selected', 'rejected', 'withdrawn'], 'filters' => ['program_id' => $program->id], 'created_by' => $admHead->id]
        );

        foreach (['Admission Head Command Center', 'Telecaller Today Queue', 'Counsellor Applicant Follow-up'] as $viewName) {
            \App\Models\AdmissionSavedView::updateOrCreate(
                ['name' => $viewName, 'surface' => 'command_center'],
                ['role_name' => str_contains($viewName, 'Telecaller') ? 'admission_telecaller' : null, 'filters' => ['program_id' => $program->id], 'layout' => ['density' => 'operational'], 'is_default' => str_contains($viewName, 'Head')]
            );
        }

        \App\Models\AdmissionAutomation::updateOrCreate(
            ['name' => 'Auto-score urgent web leads'],
            ['trigger' => 'lead_created', 'priority' => 10, 'is_active' => true, 'conditions' => ['source' => 'web_form'], 'actions' => [['type' => 'score_lead'], ['type' => 'next_action', 'value' => 'Call within SLA']], 'created_by' => $admHead->id]
        );

        \App\Models\AdmissionForecastSnapshot::updateOrCreate(
            ['program_id' => $program->id, 'batch_id' => $batch->id, 'source' => 'all'],
            [
                'target_seats' => 60,
                'lead_count' => \App\Models\Lead::where('program_id', $program->id)->count(),
                'application_count' => Applicant::where('program_id', $program->id)->count(),
                'selection_count' => Applicant::where('program_id', $program->id)->where('status', 'selected')->count(),
                'offer_count' => \App\Models\OfferLetter::whereIn('applicant_id', Applicant::where('program_id', $program->id)->pluck('id'))->count(),
                'enrollment_count' => \App\Models\EnrollmentConfirmation::whereIn('applicant_id', Applicant::where('program_id', $program->id)->pluck('id'))->count(),
                'expected_conversion_rate' => 42.5,
                'projected_enrollments' => 48,
                'projected_gap' => -12,
                'metadata' => ['demo' => true],
                'created_by' => $admHead->id,
            ]
        );

        if ($duplicateLead = $leads->last()) {
            \App\Models\AdmissionDataQualityFlag::updateOrCreate(
                ['subject_type' => \App\Models\Lead::class, 'subject_id' => $duplicateLead->id, 'flag_type' => 'possible_duplicate', 'status' => 'open'],
                ['severity' => 'warning', 'message' => 'Possible duplicate lead found by phone match.', 'confidence' => 92, 'metadata' => ['demo' => true]]
            );
        }

        if ($selectedApplicant = Applicant::where('program_id', $program->id)->where('status', 'selected')->first()) {
            \App\Models\AdmissionApproval::updateOrCreate(
                ['approvable_type' => Applicant::class, 'approvable_id' => $selectedApplicant->id, 'action' => 'offer_withdrawal_review'],
                ['status' => 'pending', 'before' => ['status' => $selectedApplicant->status], 'after' => ['status' => 'withdrawn'], 'reason' => 'Demo sensitive action requiring head approval.', 'requested_by' => $officer->id, 'metadata' => ['demo' => true]]
            );
        }

        $this->command?->info('  Admission OS v0.03/v0.031/v0.033 demo operating data seeded.');
    }

    private function user(string $email, string $name, ?string $role): User
    {
        $user = User::firstOrCreate(['email' => $email], [
            'name' => $name,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        if ($role) {
            $user->syncRoles([$role]);
        }

        return $user;
    }

    private function lead(string $name, string $email, string $phone, string $source, string $status, string $priority, ?int $handlerId, Program $program, User $admHead, ?\App\Models\AdmissionPartner $partner, string $nextAction): \App\Models\Lead
    {
        $lead = \App\Models\Lead::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'phone' => $phone,
                'program_id' => $program->id,
                'source' => $source,
                'admission_partner_id' => $partner?->id,
                'partner_reference' => $partner ? 'CITY-2026-014' : null,
                'status' => $status,
                'priority' => $priority,
                'assigned_to' => $handlerId,
                'owner_user_id' => $handlerId ?: $admHead->id,
                'current_handler_user_id' => $handlerId,
                'assigned_by' => $admHead->id,
                'assignment_reason' => 'Demo admission workload',
                'assignment_mode' => $handlerId ? 'seeded_demo' : null,
                'assigned_at' => $handlerId ? now()->subDays(2) : null,
                'last_activity_at' => now()->subDays($status === 'new' ? 4 : 1),
                'sla_due_at' => $priority === 'urgent' ? now()->addHours(2) : now()->addDays(2),
                'next_action' => $nextAction,
                'region' => $partner ? 'South' : 'North',
                'team' => $partner ? 'Partner' : 'Digital',
                'notes' => 'Seeded demo lead showing v0.03 admission operating workflow.',
            ]
        );

        if ($handlerId) {
            \App\Models\AdmissionAssignmentEvent::firstOrCreate(
                ['subject_type' => \App\Models\Lead::class, 'subject_id' => $lead->id, 'to_user_id' => $handlerId, 'mode' => 'seeded_demo'],
                ['assigned_by' => $admHead->id, 'reason' => 'Demo hierarchy assignment', 'metadata' => ['source' => $source]]
            );
        }

        return $lead;
    }

    private function communicationFor($subject, \App\Models\AdmissionCommunicationTemplate $template, User $sender, ?string $recipient, string $provider, string $status = 'sent'): void
    {
        \App\Models\AdmissionCommunicationLog::firstOrCreate(
            ['subject_type' => get_class($subject), 'subject_id' => $subject->id, 'template_id' => $template->id],
            [
                'sent_by' => $sender->id,
                'channel' => $template->channel,
                'provider' => $provider,
                'recipient' => $recipient,
                'subject_line' => $template->subject,
                'body' => 'Seeded admission communication for ' . ($subject->name ?? $subject->user?->name ?? 'applicant') . '.',
                'status' => $status,
                'queued_at' => now()->subHours(8),
                'sent_at' => $status === 'sent' ? now()->subHours(7) : null,
                'metadata' => ['demo' => true],
            ]
        );
    }

    private function callFor($subject, ?User $caller, ?string $phone, string $disposition): void
    {
        \App\Models\AdmissionCallLog::firstOrCreate(
            ['subject_type' => get_class($subject), 'subject_id' => $subject->id, 'disposition' => $disposition],
            [
                'caller_user_id' => $caller?->id,
                'phone' => $phone,
                'outcome_reason' => 'Demo follow-up',
                'duration_seconds' => 300,
                'called_at' => now()->subDay(),
                'next_followup_at' => now()->addDays(2),
                'notes' => 'Reviewed admission status and next action.',
                'metadata' => ['demo' => true],
            ]
        );
    }

    private function scoreFor(\App\Models\Lead $lead, User $admHead): void
    {
        $band = $lead->priority === 'urgent' ? 'hot' : 'warm';
        \App\Models\AdmissionLeadScore::firstOrCreate(
            ['lead_id' => $lead->id, 'band' => $band],
            ['score' => $band === 'hot' ? 86 : 64, 'explanation' => ['source_quality' => 20, 'priority' => 25, 'engagement' => 15, 'response_speed' => 10], 'scored_by' => $admHead->id, 'scored_at' => now()->subHours(4)]
        );
        $lead->update(['score_band' => $band]);
    }

    private function nextActionFor(Applicant $applicant): string
    {
        return match ($applicant->status) {
            'draft' => 'Guide applicant to complete profile',
            'submitted' => 'Verify documents and registration fee',
            'shortlisted' => 'Confirm selection session attendance',
            'selected' => 'Track offer acceptance and fee payment',
            default => 'Review applicant status',
        };
    }

    private function seedV033OperationalVolume(Program $program, Batch $batch, User $admHead, User $manager, User $counsellor, User $officer, \App\Models\AdmissionCommunicationTemplate $template, \App\Models\AdmissionAssessmentPanel $basePanel, \App\Models\SelectionSession $session, $applicants, $leads): void
    {
        $subjects = $leads->concat($applicants)->values();
        foreach (range(1, 32) as $i) {
            $subject = $subjects->get(($i - 1) % max(1, $subjects->count()));
            if (!$subject) {
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
