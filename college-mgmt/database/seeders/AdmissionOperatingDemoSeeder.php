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

        Applicant::where('program_id', $program->id)->with('user')->get()->each(function (Applicant $applicant, int $index) use ($batch, $journeyVersion, $manager, $counsellor, $officer, $admHead, $whatsAppTemplate) {
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

        $this->command?->info('  Admission OS v0.03 demo operating data seeded.');
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
}
