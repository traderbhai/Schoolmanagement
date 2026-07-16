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

        foreach (['admission_manager', 'admission_counsellor', 'admission_telecaller', 'admission_partner', 'evaluator'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        $manager = $this->user('admission.manager@college.com', 'Neha Bansal', 'admission_manager');
        $counsellor = $this->user('counsellor@college.com', 'Amit Counsellor', 'admission_counsellor');
        $telecaller = $this->user('telecaller@college.com', 'Kavya Telecaller', 'admission_telecaller');
        $partnerContact = $this->user('partner.citychannel@demo.edu', 'City Channel Partner', 'admission_partner');

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

        app(AdmissionOperatingV033DemoSeeder::class)->seedOperationalVolume($program, $batch, $admHead, $manager, $counsellor, $officer, $emailTemplate, $panel, $session, $demoApplicants, $leads);
        $this->seedV036AssessmentAndCounsellorOps($program, $batch, $admHead, $manager, $counsellor, $officer, $emailTemplate, $panel, $session, $demoApplicants, $leads);
        $this->seedV037Hardening($program, $batch, $admHead, $manager, $counsellor, $officer, $telecaller, $panel, $demoApplicants);
        $this->seedV038RealTeamOps($program, $batch, $admHead, $manager, $counsellor, $officer, $telecaller, $panel, $session, $demoApplicants, $leads);
        $this->seedV039FinalClosure($program, $batch, $admHead, $manager, $counsellor, $officer, $telecaller, $panel, $session, $demoApplicants, $leads);

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

        $this->command?->info('  Admission OS v0.03-v0.039 demo operating data seeded.');
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

    private function seedV036AssessmentAndCounsellorOps(Program $program, Batch $batch, User $admHead, User $manager, User $counsellor, User $officer, \App\Models\AdmissionCommunicationTemplate $template, \App\Models\AdmissionAssessmentPanel $basePanel, \App\Models\SelectionSession $session, $applicants, $leads): void
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

    private function seedV037Hardening(Program $program, Batch $batch, User $admHead, User $manager, User $counsellor, User $officer, User $telecaller, \App\Models\AdmissionAssessmentPanel $basePanel, $applicants): void
    {
        foreach ([$counsellor, $officer, $manager] as $index => $evaluator) {
            \App\Models\AdmissionEvaluatorAvailability::updateOrCreate(
                ['user_id' => $evaluator->id, 'available_from' => now()->startOfDay()->addHours(9 + $index)],
                [
                    'available_until' => now()->startOfDay()->addHours(18),
                    'availability_type' => 'available',
                    'location_mode' => $index === 1 ? 'online' : 'campus',
                    'notes' => 'Seeded v0.037 evaluator availability.',
                    'is_active' => true,
                    'metadata' => ['demo' => true, 'v' => '0.037'],
                ]
            );
        }

        $conflictPanel = \App\Models\AdmissionAssessmentPanel::updateOrCreate(
            ['name' => 'PI Backup Panel - Conflict Demo', 'selection_session_id' => $basePanel->selection_session_id],
            [
                'panel_type' => 'personal_interview',
                'program_id' => $program->id,
                'batch_id' => $batch->id,
                'capacity' => 2,
                'venue' => null,
                'online_link' => null,
                'scheduled_at' => $basePanel->scheduled_at ?: now()->addDay()->setTime(10, 0),
                'status' => 'scheduled',
                'readiness_status' => 'needs_setup',
                'created_by' => $admHead->id,
                'metadata' => ['demo' => true, 'v' => '0.037', 'duration_minutes' => 90],
            ]
        );

        \App\Models\AdmissionAssessmentPanelMember::updateOrCreate(
            ['panel_id' => $conflictPanel->id, 'user_id' => $counsellor->id],
            ['role' => 'evaluator', 'is_chair' => false]
        );

        $applicants->take(3)->each(function (Applicant $applicant) use ($conflictPanel, $counsellor) {
            \App\Models\AdmissionAssessmentPanelAssignment::updateOrCreate(
                ['panel_id' => $conflictPanel->id, 'applicant_id' => $applicant->id],
                [
                    'selection_session_id' => $conflictPanel->selection_session_id,
                    'evaluator_user_id' => $counsellor->id,
                    'attendance_status' => 'pending',
                    'lifecycle_status' => 'invited',
                    'score_status' => 'pending',
                    'metadata' => ['demo' => true, 'v' => '0.037'],
                ]
            );
        });

        app(\App\Services\AdmissionAssessmentSchedulingService::class)->detectConflictsForPanel($conflictPanel);

        foreach ([$counsellor, $telecaller, $officer] as $index => $user) {
            \App\Models\AdmissionCounsellorTarget::updateOrCreate(
                ['user_id' => $user->id, 'period_type' => 'daily', 'period_start' => now()->toDateString()],
                [
                    'period_end' => now()->toDateString(),
                    'target_calls' => [18, 35, 12][$index],
                    'target_followups' => [10, 18, 8][$index],
                    'target_applications' => [3, 2, 2][$index],
                    'target_enrollments' => [1, 0, 1][$index],
                    'created_by' => $manager->id,
                    'metadata' => ['demo' => true, 'v' => '0.037'],
                ]
            );

            \App\Models\AdmissionCounsellorCoachingNote::updateOrCreate(
                ['counsellor_user_id' => $user->id, 'reviewed_for_date' => now()->toDateString(), 'review_type' => 'daily_review'],
                [
                    'reviewer_user_id' => $manager->id,
                    'score_band' => $index === 1 ? 'excellent' : 'on_track',
                    'strengths' => 'Consistent follow-up discipline and clear next-step commitments.',
                    'improvement_areas' => 'Improve documentation of parent objections and assessment readiness.',
                    'action_plan' => 'Review pending blockers, close overdue callbacks, and update timeline notes before EOD.',
                    'next_review_at' => now()->addWeek()->toDateString(),
                    'status' => 'open',
                    'metadata' => ['demo' => true, 'v' => '0.037'],
                ]
            );
        }

        foreach (['email', 'sms', 'whatsapp', 'dialer', 'video', 'signature'] as $channel) {
            \App\Models\AdmissionIntegrationProvider::updateOrCreate(
                ['channel' => $channel, 'provider_name' => 'sandbox_' . $channel],
                [
                    'base_url' => 'https://sandbox.local/' . $channel,
                    'credential_keys' => ['api_key' => 'ADMISSION_' . strtoupper($channel) . '_KEY'],
                    'webhook_secret' => 'sandbox-secret-' . $channel,
                    'is_active' => true,
                    'sandbox_mode' => true,
                    'timeout_seconds' => 10,
                    'retry_policy' => ['max_attempts' => 3, 'backoff_seconds' => 30],
                    'metadata' => ['demo' => true, 'v' => '0.037-complete'],
                ]
            );
        }

        $log = \App\Models\AdmissionCommunicationLog::latest()->first();
        if ($log) {
            $log->update([
                'provider_message_id' => $log->provider_message_id ?: 'msg_demo_v037',
                'provider_request_id' => $log->provider_request_id ?: 'req_demo_v037',
                'delivery_state' => $log->delivery_state ?: 'delivered',
                'retry_count' => 0,
                'last_synced_at' => now(),
            ]);
            \App\Models\AdmissionIntegrationWebhookEvent::updateOrCreate(
                ['provider_name' => $log->provider, 'external_id' => $log->provider_message_id, 'event_type' => 'delivery_update'],
                [
                    'subject_type' => $log->subject_type,
                    'subject_id' => $log->subject_id,
                    'communication_log_id' => $log->id,
                    'status' => 'processed',
                    'payload' => ['message_id' => $log->provider_message_id, 'delivery_state' => 'delivered'],
                    'processed_at' => now(),
                ]
            );
            \App\Models\AdmissionProviderDeliveryAttempt::updateOrCreate(
                ['communication_log_id' => $log->id, 'attempt_number' => 1],
                [
                    'provider_name' => $log->provider,
                    'channel' => $log->channel,
                    'status' => 'sent',
                    'request_payload' => ['recipient' => $log->recipient],
                    'response_payload' => ['accepted' => true, 'sandbox' => true],
                    'attempted_at' => now(),
                ]
            );
        }

        $conflictPanel->update(['metadata' => ($conflictPanel->metadata ?? []) + ['blind_scoring' => true]]);
        foreach ($conflictPanel->assignments as $assignment) {
            \App\Models\AdmissionBlindScoringAlias::updateOrCreate(
                ['panel_id' => $conflictPanel->id, 'applicant_id' => $assignment->applicant_id],
                ['alias_code' => 'CAND-' . str_pad((string) $assignment->applicant_id, 4, '0', STR_PAD_LEFT), 'is_active' => true, 'metadata' => ['demo' => true]]
            );
            $assignment->update(['aggregate_score' => $assignment->aggregate_score ?: 62 + ($assignment->id % 25)]);
        }
        app(\App\Services\AdmissionAssessmentNormalizationService::class)->normalizePanel($conflictPanel);

        $script = \App\Models\AdmissionScriptTemplate::updateOrCreate(
            ['name' => 'Admission Counsellor Discovery Script', 'stage' => 'interested'],
            [
                'program_id' => $program->id,
                'steps' => ['Confirm program interest', 'Ask budget sensitivity', 'Confirm parent decision maker', 'Explain assessment process', 'Commit next follow-up'],
                'is_active' => true,
                'created_by' => $admHead->id,
            ]
        );
        $call = \App\Models\AdmissionCallLog::where('caller_user_id', $counsellor->id)->first() ?: \App\Models\AdmissionCallLog::first();
        if ($call) {
            app(\App\Services\AdmissionScriptComplianceService::class)->log($call, $script, ['covered', 'covered', 'missed', 'covered', 'covered'], $counsellor);
        }

        foreach ([
            ['Fee concern', 'fee', 'Offer installment plan and scholarship checklist.'],
            ['Location concern', 'location', 'Explain hostel, transport, and city connectivity.'],
            ['Placement concern', 'placement', 'Share placement report and alumni outcomes.'],
            ['Parent approval pending', 'parent', 'Schedule parent/guardian discussion.'],
        ] as [$name, $category, $response]) {
            $type = \App\Models\AdmissionObjectionType::updateOrCreate(
                ['name' => $name],
                ['category' => $category, 'recommended_response' => $response, 'is_active' => true]
            );
            if ($lead = \App\Models\Lead::first()) {
                \App\Models\AdmissionObjectionEvent::updateOrCreate(
                    ['objection_type_id' => $type->id, 'subject_type' => get_class($lead), 'subject_id' => $lead->id],
                    ['counsellor_user_id' => $counsellor->id, 'stage' => $lead->status, 'status' => 'open', 'notes' => 'Seeded v0.037 objection event.']
                );
            }
        }

        foreach ($applicants->take(5) as $applicant) {
            app(\App\Services\AdmissionParentJourneyService::class)->ensure($applicant, $counsellor);
        }

        $automation = \App\Models\AdmissionAutomation::updateOrCreate(
            ['name' => 'v0.037 Parent Follow-up And Quality Review'],
            [
                'trigger' => 'lead_updated',
                'priority' => 20,
                'is_active' => true,
                'conditions' => [],
                'actions' => [
                    ['type' => 'parent_followup'],
                    ['type' => 'create_reminder', 'reason' => 'automation_followup', 'due_hours' => 24],
                    ['type' => 'data_quality_flag', 'flag_type' => 'automation_review', 'message' => 'Review lead follow-up quality.'],
                ],
                'created_by' => $admHead->id,
            ]
        );
        \App\Models\AdmissionAutomationSchedule::updateOrCreate(
            ['automation_id' => $automation->id],
            ['trigger_window' => 'daily', 'next_run_at' => now()->subMinute(), 'is_active' => true, 'metadata' => ['demo' => true]]
        );
        app(\App\Services\AdmissionAutomationSimulationService::class)->simulate($automation, $admHead);

        foreach ([
            ['assessment_control_room', 'Pending scores and no-shows', ['score_status' => 'pending', 'lifecycle_status' => 'no_show']],
            ['counsellor_desk', 'Hot leads and parent follow-ups', ['priority' => 'high', 'reason' => 'parent_guardian_followup']],
            ['automation_logs', 'Automation conflicts', ['status' => 'open']],
        ] as [$surface, $name, $filters]) {
            app(\App\Services\AdmissionSavedViewService::class)->save($surface, $name, $filters, $admHead);
        }

        \App\Models\AdmissionExportLog::updateOrCreate(
            ['export_type' => 'normalization', 'surface' => 'admission-v037'],
            ['filters' => ['demo' => true], 'row_count' => \App\Models\AdmissionAssessmentNormalizedScore::count(), 'created_by' => $admHead->id]
        );

        app(\App\Services\AdmissionRouteAccessAuditService::class)->refresh($admHead);
    }

    private function seedV038RealTeamOps(Program $program, Batch $batch, User $admHead, User $manager, User $counsellor, User $officer, User $telecaller, \App\Models\AdmissionAssessmentPanel $panel, \App\Models\SelectionSession $session, $applicants, $leads): void
    {
        $script = \App\Models\AdmissionScriptTemplate::where('is_active', true)->latest()->first();
        foreach ($leads->take(3) as $index => $lead) {
            $lead->update([
                'assigned_to' => $lead->assigned_to ?: $telecaller->id,
                'current_handler_user_id' => $lead->current_handler_user_id ?: $telecaller->id,
                'priority' => $index === 0 ? 'urgent' : 'high',
                'sla_due_at' => now()->subHours($index + 1),
                'next_action' => $index === 0 ? 'Parent callback due before lunch' : 'Retry after no-answer cadence',
            ]);

            app(\App\Services\AdmissionCallAttemptService::class)->record($lead, $telecaller, [
                'disposition' => $index === 0 ? 'no_answer' : 'connected',
                'outcome' => $index === 0 ? 'callback' : 'interested',
                'retry_due_at' => now()->addHours($index + 2),
                'duration_seconds' => 120 + ($index * 40),
                'script_template_id' => $script?->id,
                'script_results' => ['covered', 'covered', $index === 0 ? 'missed' : 'covered', 'covered', 'covered'],
                'notes' => 'Seeded v0.038 calling desk attempt.',
            ]);
        }

        if ($firstLead = $leads->first()) {
            \Illuminate\Support\Facades\DB::table('admission_call_queue_skips')->updateOrInsert(
                ['subject_type' => get_class($firstLead), 'subject_id' => $firstLead->id, 'user_id' => $telecaller->id],
                ['reason' => 'Candidate requested callback after class.', 'skipped_until' => now()->addMinutes(45), 'metadata' => json_encode(['demo' => true]), 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $resourceId = \Illuminate\Support\Facades\DB::table('admission_assessment_resources')->updateOrInsert(
            ['name' => 'Assessment Room A'],
            ['resource_type' => 'room', 'capacity' => 18, 'location' => 'Admission Block', 'online_link' => null, 'is_active' => true, 'metadata' => json_encode(['demo' => true, 'v' => '0.038']), 'created_at' => now(), 'updated_at' => now()]
        );
        $resource = \Illuminate\Support\Facades\DB::table('admission_assessment_resources')->where('name', 'Assessment Room A')->first();

        $slotId = \Illuminate\Support\Facades\DB::table('admission_assessment_slots')->updateOrInsert(
            ['panel_id' => $panel->id, 'slot_code' => 'PGDM-GD-01'],
            ['selection_session_id' => $session->id, 'resource_id' => $resource?->id, 'starts_at' => now()->addDays(2)->setTime(10, 0), 'ends_at' => now()->addDays(2)->setTime(11, 0), 'capacity' => 6, 'venue' => 'Assessment Room A', 'status' => 'open', 'metadata' => json_encode(['demo' => true]), 'created_at' => now(), 'updated_at' => now()]
        );
        $slot = \Illuminate\Support\Facades\DB::table('admission_assessment_slots')->where('panel_id', $panel->id)->where('slot_code', 'PGDM-GD-01')->first();

        if ($slot && $resource) {
            \Illuminate\Support\Facades\DB::table('admission_assessment_resource_bookings')->updateOrInsert(
                ['resource_id' => $resource->id, 'slot_id' => $slot->id],
                ['panel_id' => $panel->id, 'starts_at' => $slot->starts_at, 'ends_at' => $slot->ends_at, 'status' => 'booked', 'metadata' => json_encode(['demo' => true]), 'created_at' => now(), 'updated_at' => now()]
            );
            \Illuminate\Support\Facades\DB::table('admission_assessment_resource_bookings')->updateOrInsert(
                ['resource_id' => $resource->id, 'panel_id' => $panel->id, 'slot_id' => null],
                ['starts_at' => now()->addDays(2)->setTime(10, 30), 'ends_at' => now()->addDays(2)->setTime(11, 30), 'status' => 'booked', 'metadata' => json_encode(['demo' => true, 'conflict' => true]), 'created_at' => now(), 'updated_at' => now()]
            );

            foreach ($applicants->take(4) as $applicant) {
                app(\App\Services\AdmissionAssessmentSlotService::class)->assignApplicant($slot->id, $applicant, $manager);
            }

            app(\App\Services\AdmissionGdGroupService::class)->build($panel->id, $slot->id, 4, $manager->id);
        }

        app(\App\Services\AdmissionAssessmentSlotService::class)->inviteEvaluators($panel);
        $invitation = \Illuminate\Support\Facades\DB::table('admission_evaluator_invitations')->where('panel_id', $panel->id)->where('user_id', $counsellor->id)->first();
        if ($invitation) {
            app(\App\Services\AdmissionAssessmentSlotService::class)->evaluatorResponse($invitation->id, 'accepted', 'Available for v0.038 demo slot.');
        }

        foreach ($applicants->take(3) as $index => $applicant) {
            app(\App\Services\AdmissionAssessmentSubmissionService::class)->markReceived($applicant, [
                'panel_id' => $panel->id,
                'slot_id' => $slot?->id,
                'submission_type' => ['case_analysis', 'wat', 'presentation'][$index],
                'artifact_url' => 'https://demo.local/submissions/' . $applicant->application_number,
                'status' => $index === 2 ? 'late' : 'received',
                'originality_flag' => $index === 2,
            ], $officer);
        }

        if ($candidate = $applicants->first()) {
            app(\App\Services\AdmissionSelectionCommitteeService::class)->decide($candidate, 'selected', 'Strong normalized score, documents ready, and fee readiness confirmed.', $admHead, ['panel_id' => $panel->id, 'normalized_score' => 82]);
        }

        $roundId = app(\App\Services\AdmissionOfferRoundService::class)->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'round_number' => 1,
            'name' => 'PGDM Round 1 - v0.038 Demo',
            'offer_valid_until' => now()->addDays(7),
            'status' => 'draft',
        ]);
        app(\App\Services\AdmissionOfferRoundService::class)->publish($roundId, $admHead);

        foreach ($applicants->skip(1)->take(3) as $rank => $applicant) {
            app(\App\Services\AdmissionWaitlistService::class)->add($applicant, ['offer_round_id' => $roundId, 'program_id' => $program->id, 'batch_id' => $batch->id, 'rank' => $rank + 1]);
        }

        if ($hold = \Illuminate\Support\Facades\DB::table('admission_seat_holds')->where('status', 'held')->first()) {
            \Illuminate\Support\Facades\DB::table('admission_seat_holds')->where('id', $hold->id)->update(['expires_at' => now()->addDays(3), 'updated_at' => now()]);
        }

        if ($deferApplicant = $applicants->skip(2)->first()) {
            $deferralId = app(\App\Services\AdmissionDeferralService::class)->request($deferApplicant, $batch->id, 'Family requested future joining cycle.');
            app(\App\Services\AdmissionDeferralService::class)->approve($deferralId, $admHead, 'Carry forward registration fee and verified documents.');
            app(\App\Services\AdmissionJoiningKitService::class)->ensure($deferApplicant, $counsellor);
        }

        $emailTemplate = \App\Models\AdmissionCommunicationTemplate::where('channel', 'email')->first();
        $whatsappTemplate = \App\Models\AdmissionCommunicationTemplate::where('channel', 'whatsapp')->first();
        foreach ($leads->take(2) as $index => $lead) {
            app(\App\Services\AdmissionConsentService::class)->set($lead, 'whatsapp', $index === 0 ? 'opt_out' : 'opt_in', $officer, $index === 0 ? 'Candidate opted out from WhatsApp reminders.' : 'Seeded consent from web enquiry.', 'seeded_demo');
            app(\App\Services\AdmissionConsentService::class)->set($lead, 'sms', 'opt_in', $officer, 'Seeded SMS consent.', 'seeded_demo');
        }

        \Illuminate\Support\Facades\DB::table('admission_quiet_hour_rules')->updateOrInsert(
            ['channel' => 'whatsapp'],
            ['starts_at_time' => '22:00:00', 'ends_at_time' => '07:00:00', 'timezone' => 'Asia/Kolkata', 'is_active' => true, 'emergency_override_allowed' => false, 'metadata' => json_encode(['demo' => true]), 'created_at' => now(), 'updated_at' => now()]
        );

        foreach ([$emailTemplate, $whatsappTemplate] as $template) {
            if ($template) {
                $approvalId = app(\App\Services\AdmissionTemplateApprovalService::class)->request($template, $manager);
                app(\App\Services\AdmissionTemplateApprovalService::class)->approve($approvalId, $admHead);
                app(\App\Services\AdmissionCommunicationSafetyService::class)->preview($template, $leads->take(3), $admHead, ['source' => 'v0.038_demo']);
            }
        }

        app(\App\Services\AdmissionVendorAdapterRegistry::class)->ensureDefaults();
        app(\App\Services\AdmissionIntegrationHealthService::class)->checkAll();
        $failedLog = \App\Models\AdmissionCommunicationLog::where('status', 'failed')->first() ?: \App\Models\AdmissionCommunicationLog::first();
        if ($failedLog) {
            \Illuminate\Support\Facades\DB::table('admission_integration_retry_queue')->updateOrInsert(
                ['communication_log_id' => $failedLog->id],
                ['provider_name' => $failedLog->provider ?: 'sandbox_whatsapp', 'channel' => $failedLog->channel ?: 'whatsapp', 'failure_type' => 'temporary_provider_failure', 'retryable' => true, 'attempts' => 1, 'max_attempts' => 3, 'next_retry_at' => now()->addMinutes(15), 'status' => 'queued', 'last_error' => 'Seeded retryable sandbox failure.', 'created_at' => now(), 'updated_at' => now()]
            );
        }

        foreach ([
            ['calling_desk', 'Urgent callbacks', ['priority' => 'urgent', 'due' => 'today']],
            ['assessment_scheduling', 'Today slots and conflicts', ['slot_status' => 'open']],
            ['offer_seat_control', 'Seat holds expiring', ['status' => 'held']],
            ['communication_safety', 'Blocked recipients', ['blocked' => true]],
        ] as [$surface, $name, $filters]) {
            app(\App\Services\AdmissionSavedViewService::class)->save($surface, $name, $filters, $admHead);
        }

        app(\App\Services\AdmissionQuickSearchService::class)->search('PGDM', $admHead);
    }

    private function seedV039FinalClosure(Program $program, Batch $batch, User $admHead, User $manager, User $counsellor, User $officer, User $telecaller, \App\Models\AdmissionAssessmentPanel $panel, \App\Models\SelectionSession $session, $applicants, $leads): void
    {
        $slot = \Illuminate\Support\Facades\DB::table('admission_assessment_slots')->where('panel_id', $panel->id)->first();
        if ($slot) {
            $existingAssignments = \Illuminate\Support\Facades\DB::table('admission_assessment_slot_assignments')->where('slot_id', $slot->id)->limit(5)->get();
            foreach ($existingAssignments as $index => $assignment) {
                if ($assignment) {
                    app(\App\Services\AdmissionAssessmentSlotService::class)->checkIn($assignment->id, ['confirmed', 'checked_in', 'waiting', 'in_progress', 'completed'][$index] ?? 'confirmed', $officer);
                }
            }

            $firstAssignment = \Illuminate\Support\Facades\DB::table('admission_assessment_slot_assignments')->where('slot_id', $slot->id)->first();
            if ($firstAssignment) {
                app(\App\Services\AdmissionAssessmentSlotService::class)->requestReschedule($firstAssignment->id, Applicant::find($firstAssignment->applicant_id), 'Applicant has university exam on the same day.', null);
                $reschedule = \Illuminate\Support\Facades\DB::table('admission_assessment_reschedule_requests')->where('slot_assignment_id', $firstAssignment->id)->latest()->first();
                if ($reschedule) {
                    app(\App\Services\AdmissionAssessmentSlotService::class)->reviewReschedule($reschedule->id, 'approved', $manager);
                }
            }
        }

        $declinedInvite = \Illuminate\Support\Facades\DB::table('admission_evaluator_invitations')->where('panel_id', $panel->id)->first();
        if ($declinedInvite) {
            app(\App\Services\AdmissionAssessmentSlotService::class)->evaluatorResponse($declinedInvite->id, 'declined', 'Schedule conflict during PI panel.');
            app(\App\Services\AdmissionAssessmentSlotService::class)->replaceEvaluator($declinedInvite->id, $manager->id, $admHead);
        }

        $template = \App\Models\AdmissionCommunicationTemplate::where('channel', 'whatsapp')->first() ?: \App\Models\AdmissionCommunicationTemplate::where('channel', 'email')->first();
        if ($template && $lead = $leads->first()) {
            app(\App\Services\AdmissionConsentService::class)->set($lead, $template->channel, 'opt_out', $officer, 'v0.039 seeded opt-out safety scenario.', 'seeded_demo');
            app(\App\Services\AdmissionSafeCommunicationService::class)->queue($lead, $template, $officer, ['source' => 'v0.039_seed']);
        }

        foreach ($applicants->take(4) as $index => $applicant) {
            app(\App\Services\AdmissionJoiningKitService::class)->ensure($applicant, $counsellor);
            if ($index < 2) {
                $taskIds = \Illuminate\Support\Facades\DB::table('admission_joining_kit_tasks')
                    ->where('applicant_id', $applicant->id)
                    ->limit(2)
                    ->pluck('id');
                \Illuminate\Support\Facades\DB::table('admission_joining_kit_tasks')
                    ->whereIn('id', $taskIds)
                    ->update(['status' => 'completed', 'completed_at' => now(), 'updated_at' => now()]);
            }
            app(\App\Services\AdmissionHandoffService::class)->ensure($applicant, $applicant->enrollmentConfirmation, $admHead);
        }

        if ($hold = \Illuminate\Support\Facades\DB::table('admission_seat_holds')->where('status', 'held')->latest()->first()) {
            \Illuminate\Support\Facades\DB::table('admission_seat_holds')->where('id', $hold->id)->update(['expires_at' => now()->subHour(), 'updated_at' => now()]);
            app(\App\Services\AdmissionOfferSeatSchedulerService::class)->run($admHead);
        }

        foreach (['payment_override', 'enrollment_override', 'offer_withdrawal', 'bulk_communication', 'document_rejection', 'lead_applicant_merge'] as $action) {
            app(\App\Services\AdmissionSensitiveAuditService::class)->record($action, $applicants->first(), $admHead, 'Seeded v0.039 sensitive audit scenario.', [], ['demo' => true]);
        }

        foreach ([['handoff', 'admission_handoff'], ['communication-safety', 'admission_communication_safety'], ['route-policy', 'admission_route_policy']] as [$type, $surface]) {
            app(\App\Services\AdmissionFinalExportService::class)->log($type, $surface, ['demo' => true], 5, $admHead);
        }

        \Illuminate\Support\Facades\DB::table('admission_high_volume_seed_runs')->updateOrInsert(
            ['name' => 'v0.039 high-volume ready profile'],
            [
                'lead_count' => 10000,
                'applicant_count' => 5000,
                'communication_count' => 100000,
                'status' => 'profile_available',
                'started_at' => now(),
                'completed_at' => now(),
                'metadata' => json_encode(['mode' => 'deferred_high_volume_seed', 'note' => 'Counts document the supported stress profile; demo seeder keeps local runtime fast.']),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        app(\App\Services\AdmissionSavedViewService::class)->save('handoff_queue', 'Ready and blocked handoffs', ['status' => ['ready_for_academics', 'blocked']], $admHead);
        app(\App\Services\AdmissionSavedViewService::class)->save('assessment_day', 'Check-in desk active candidates', ['status' => ['confirmed', 'checked_in', 'waiting']], $manager);
    }
}
