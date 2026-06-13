<?php

namespace App\Services;

use App\Models\AdmissionCallLog;
use App\Models\AdmissionCommunicationLog;
use App\Models\AdmissionReminderSchedule;
use App\Models\Applicant;
use App\Models\ApplicantScore;
use App\Models\Lead;
use App\Models\SessionApplicant;
use Illuminate\Support\Collection;

class AdmissionNextActionService
{
    public function forLead(Lead $lead): array
    {
        $lead->loadMissing(['assignedTo', 'program', 'convertedApplicant']);

        $openReminderCount = $lead->reminders()->whereIn('status', ['scheduled', 'queued', 'paused', 'escalated'])->count();
        $overdueFollowUpCount = $lead->followUps()->whereNull('completed_at')->where('scheduled_at', '<', now())->count();
        $qualityFlagCount = $lead->dataQualityFlags()->where('status', 'open')->count();

        $blockers = collect();
        if (!$lead->assigned_to) {
            $blockers->push($this->blocker('Unassigned lead', 'Assign an owner before operational follow-up.', 'high'));
        }
        if (!$lead->program_id) {
            $blockers->push($this->blocker('Program missing', 'Capture program interest before conversion.', 'normal'));
        }
        if (!$lead->last_contacted_at && !$lead->isConverted()) {
            $blockers->push($this->blocker('No contact logged', 'Call or message this lead and record the outcome.', 'high'));
        }
        if ($overdueFollowUpCount > 0) {
            $blockers->push($this->blocker('Overdue follow-up', "{$overdueFollowUpCount} follow-up item(s) are overdue.", 'urgent'));
        }
        if ($qualityFlagCount > 0) {
            $blockers->push($this->blocker('Data quality review', "{$qualityFlagCount} open data quality flag(s).", 'normal'));
        }

        $primaryAction = match (true) {
            $lead->isConverted() => $this->linkAction('Open applicant', route('admission.applicants.show', $lead->convertedApplicant), 'success', 'person-check'),
            !$lead->assigned_to => $this->linkAction('Assign owner', '#leadAssignmentCard', 'primary', 'person-plus'),
            $lead->status === 'new' => $this->postAction('Mark contacted', route('admission.leads.contact', $lead), 'secondary', 'telephone'),
            $lead->status === 'contacted' => $this->postAction('Mark interested', route('admission.leads.interested', $lead), 'warning', 'star'),
            $lead->status === 'interested' => $this->modalAction('Convert to applicant', '#convertModal', 'success', 'person-plus'),
            default => $this->postAction('Schedule reminder', route('admission.reminders.store'), 'primary', 'bell', [
                'subject_type' => 'lead',
                'subject_id' => $lead->id,
                'reason' => 'no_response_follow_up',
                'channel' => 'email',
                'priority' => $lead->priority ?? 'normal',
                'due_at' => now()->addDay()->format('Y-m-d\TH:i'),
                'notes' => 'Follow up from lead action center.',
            ]),
        };

        return [
            'title' => 'Lead Action Center',
            'primary' => $primaryAction,
            'blockers' => $blockers,
            'quick_actions' => collect([
                $lead->phone ? $this->linkAction('Tap to call', 'tel:' . $lead->phone, 'outline-primary', 'telephone') : null,
                $this->postAction('Schedule tomorrow reminder', route('admission.reminders.store'), 'outline-primary', 'bell', [
                    'subject_type' => 'lead',
                    'subject_id' => $lead->id,
                    'reason' => 'no_response_follow_up',
                    'channel' => 'email',
                    'priority' => $lead->priority ?? 'normal',
                    'due_at' => now()->addDay()->format('Y-m-d\TH:i'),
                    'notes' => 'Follow up from lead detail.',
                ]),
                $this->postAction('Log connected call', route('admission.call-queue.log'), 'outline-success', 'telephone-outbound', [
                    'subject_type' => 'lead',
                    'subject_id' => $lead->id,
                    'phone' => $lead->phone,
                    'disposition' => 'connected',
                    'duration_seconds' => 180,
                    'notes' => 'Connected call from lead action center.',
                ]),
                !$lead->isConverted() ? $this->modalAction('Convert', '#convertModal', 'outline-success', 'person-plus') : null,
            ])->filter()->values(),
            'metrics' => [
                'open_reminders' => $openReminderCount,
                'overdue_followups' => $overdueFollowUpCount,
                'communications' => $lead->communicationLogs()->count(),
                'calls' => $lead->callLogs()->count(),
            ],
            'activity' => $this->activityFor($lead),
        ];
    }

    public function forApplicant(Applicant $applicant): array
    {
        $applicant->loadMissing(['user', 'program', 'batch']);

        $pendingDocs = $applicant->documents()->where('status', 'pending')->count();
        $rejectedDocs = $applicant->documents()->where('status', 'rejected')->count();
        $pendingPayments = $applicant->payments()->where('status', 'pending')->count();
        $openReminders = $applicant->reminders()->whereIn('status', ['scheduled', 'queued', 'paused', 'escalated'])->count();
        $pendingScores = ApplicantScore::where('applicant_id', $applicant->id)
            ->whereNotIn('score_status', ['finalized', 'overridden'])
            ->count();
        $upcomingSessions = SessionApplicant::where('applicant_id', $applicant->id)
            ->whereHas('session', fn ($query) => $query->whereDate('scheduled_date', '>=', today()))
            ->count();

        $blockers = collect();
        if (!$applicant->assigned_to) {
            $blockers->push($this->blocker('No handler assigned', 'Assign a counsellor or manager before next follow-up.', 'high'));
        }
        if ($applicant->status === 'draft') {
            $blockers->push($this->blocker('Application still draft', 'Applicant must submit before review can start.', 'normal'));
        }
        if (!$applicant->registration_fee_paid_at) {
            $blockers->push($this->blocker('Registration fee pending', 'Applicant has not completed registration fee payment.', 'high'));
        }
        if ($pendingDocs > 0 || $rejectedDocs > 0) {
            $blockers->push($this->blocker('Document action needed', "{$pendingDocs} pending and {$rejectedDocs} rejected document(s).", $rejectedDocs > 0 ? 'urgent' : 'high'));
        }
        if ($pendingPayments > 0) {
            $blockers->push($this->blocker('Payment verification pending', "{$pendingPayments} payment proof(s) need verification.", 'high'));
        }
        if ($pendingScores > 0) {
            $blockers->push($this->blocker('Assessment score pending', "{$pendingScores} score record(s) need finalization.", 'normal'));
        }
        if ($applicant->status === 'selected' && !$applicant->isEnrolled()) {
            $blockers->push($this->blocker('Enrollment not completed', 'Selected applicant is ready for enrollment checks.', 'urgent'));
        }

        $primaryAction = match (true) {
            $applicant->status === 'selected' && !$applicant->isEnrolled() => $this->linkAction('Proceed to enrollment', route('admission.enrollment.create', $applicant), 'success', 'person-plus'),
            $pendingDocs > 0 || $rejectedDocs > 0 => $this->linkAction('Review documents', route('admission.documents.queue', ['search' => $applicant->application_number]), 'warning', 'folder-check'),
            $pendingPayments > 0 => $this->linkAction('Verify payment', route('admission.applicants.payments', $applicant), 'warning', 'credit-card'),
            $upcomingSessions > 0 => $this->linkAction('Open scorecard', route('admission.applicants.scorecard', $applicant), 'primary', 'clipboard-check'),
            !$applicant->registration_fee_paid_at => $this->linkAction('Open registration fee', route('admission.applicants.registration-fee.show', $applicant), 'primary', 'cash-coin'),
            default => $this->postAction('Schedule applicant reminder', route('admission.reminders.store'), 'primary', 'bell', [
                'subject_type' => 'applicant',
                'subject_id' => $applicant->id,
                'reason' => 'incomplete_application',
                'channel' => 'email',
                'priority' => $applicant->priority ?? 'normal',
                'due_at' => now()->addDay()->format('Y-m-d\TH:i'),
                'notes' => 'Follow up from applicant action center.',
            ]),
        };

        return [
            'title' => 'Applicant Action Center',
            'primary' => $primaryAction,
            'blockers' => $blockers,
            'quick_actions' => collect([
                $this->postAction('Schedule reminder', route('admission.reminders.store'), 'outline-primary', 'bell', [
                    'subject_type' => 'applicant',
                    'subject_id' => $applicant->id,
                    'reason' => $pendingDocs || $rejectedDocs ? 'document_blocker' : 'no_response_follow_up',
                    'channel' => 'email',
                    'priority' => $applicant->priority ?? 'normal',
                    'due_at' => now()->addDay()->format('Y-m-d\TH:i'),
                    'notes' => 'Follow up from applicant detail.',
                ]),
                $this->postAction('Log connected call', route('admission.call-queue.log'), 'outline-success', 'telephone-outbound', [
                    'subject_type' => 'applicant',
                    'subject_id' => $applicant->id,
                    'phone' => $applicant->user?->phone,
                    'disposition' => 'connected',
                    'duration_seconds' => 180,
                    'notes' => 'Connected call from applicant action center.',
                ]),
                $this->linkAction('Journey preview', route('admission.journeys.applicants.preview', $applicant), 'outline-secondary', 'map'),
                $this->linkAction('Payment view', route('admission.applicants.payments', $applicant), 'outline-secondary', 'credit-card'),
            ])->values(),
            'metrics' => [
                'open_reminders' => $openReminders,
                'pending_documents' => $pendingDocs,
                'pending_payments' => $pendingPayments,
                'pending_scores' => $pendingScores,
            ],
            'activity' => $this->activityFor($applicant),
        ];
    }

    private function activityFor(Lead|Applicant $subject): Collection
    {
        $communications = AdmissionCommunicationLog::where('subject_type', get_class($subject))
            ->where('subject_id', $subject->getKey())
            ->latest()
            ->limit(5)
            ->get()
            ->toBase()
            ->map(fn ($log) => [
                'type' => 'Communication',
                'label' => strtoupper($log->channel) . ' ' . $log->status,
                'detail' => str($log->body ?? '')->limit(90)->toString(),
                'at' => $log->created_at,
                'icon' => 'envelope',
            ]);

        $calls = AdmissionCallLog::where('subject_type', get_class($subject))
            ->where('subject_id', $subject->getKey())
            ->latest('called_at')
            ->limit(5)
            ->get()
            ->toBase()
            ->map(fn ($call) => [
                'type' => 'Call',
                'label' => ucfirst(str_replace('_', ' ', $call->disposition)),
                'detail' => $call->notes,
                'at' => $call->called_at ?? $call->created_at,
                'icon' => 'telephone',
            ]);

        $reminders = AdmissionReminderSchedule::where('subject_type', get_class($subject))
            ->where('subject_id', $subject->getKey())
            ->latest('due_at')
            ->limit(5)
            ->get()
            ->toBase()
            ->map(fn ($reminder) => [
                'type' => 'Reminder',
                'label' => ucfirst(str_replace('_', ' ', $reminder->reason)) . ' - ' . $reminder->status,
                'detail' => $reminder->notes,
                'at' => $reminder->due_at ?? $reminder->created_at,
                'icon' => 'bell',
            ]);

        return $communications->merge($calls)->merge($reminders)
            ->sortByDesc('at')
            ->take(8)
            ->values();
    }

    private function blocker(string $title, string $detail, string $severity): array
    {
        return compact('title', 'detail', 'severity');
    }

    private function linkAction(string $label, string $href, string $style, string $icon): array
    {
        return compact('label', 'href', 'style', 'icon') + ['type' => 'link'];
    }

    private function postAction(string $label, string $action, string $style, string $icon, array $fields = []): array
    {
        return compact('label', 'action', 'style', 'icon', 'fields') + ['type' => 'post'];
    }

    private function modalAction(string $label, string $target, string $style, string $icon): array
    {
        return compact('label', 'target', 'style', 'icon') + ['type' => 'modal'];
    }
}
