<?php

namespace App\Services;

use App\Models\AdmissionPayment;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\Lead;
use App\Models\OfferLetter;
use App\Models\SelectionSession;
use App\Models\User;
use Illuminate\Support\Collection;

class AdmissionAttentionService
{
    public function __construct(
        private AdmissionAccessPolicyService $accessPolicy,
        private AdmissionApplicantReadinessService $readiness,
    ) {}

    public function queuesFor(User $user, array $filters = []): array
    {
        $leads = Lead::query();
        $this->accessPolicy->applyLeadVisibility($leads, $user);
        $this->applyFilters($leads, $filters);

        $applicants = Applicant::query();
        $this->accessPolicy->applyApplicantVisibility($applicants, $user);
        $this->applyFilters($applicants, $filters);

        $leadRows = (clone $leads)->with(['program', 'assignedTo'])->latest()->limit(150)->get();
        $applicantRows = (clone $applicants)->with(['user', 'program', 'batch'])->latest()->limit(150)->get();

        return [
            'unassigned_hot_leads' => $this->items($leadRows->whereNull('assigned_to')->whereIn('priority', ['urgent', 'high']), 'Assign hot lead', 'danger'),
            'sla_breaches' => $this->items($leadRows->whereNotNull('sla_due_at')->filter(fn (Lead $lead) => $lead->sla_due_at?->isPast()), 'SLA breached', 'danger'),
            'stale_leads' => $this->items($leadRows->filter(fn (Lead $lead) => !$lead->last_activity_at || $lead->last_activity_at->lt(now()->subDays(3))), 'No recent activity', 'warning'),
            'pending_manager_delegation' => $this->items($leadRows->whereNull('current_handler_user_id')->whereNotNull('owner_user_id'), 'Delegate to handler', 'warning'),
            'duplicates' => $this->duplicateItems($leadRows),
            'pending_documents' => $this->documentItems($applicantRows),
            'pending_payments' => $this->paymentItems($applicantRows),
            'sessions_today' => $this->sessionItems($filters),
            'offer_expiry_risk' => $this->offerItems($applicantRows),
            'enrollment_ready' => $this->items($applicantRows->filter(fn (Applicant $applicant) => $this->readiness->isEnrollmentReady($applicant) && !$applicant->isEnrolled()), 'Ready for enrollment', 'success'),
        ];
    }

    public function flatItemsFor(User $user, array $filters = []): Collection
    {
        return collect($this->queuesFor($user, $filters))->flatten(1)->values();
    }

    private function items(Collection $records, string $reason, string $severity): Collection
    {
        return $records->take(25)->map(fn ($record) => [
            'subject_type' => get_class($record),
            'subject_id' => $record->id,
            'title' => $record instanceof Lead ? $record->name : ($record->user?->name ?? $record->application_number),
            'reason' => $reason,
            'severity' => $severity,
            'due_at' => $record->sla_due_at ?? null,
            'owner' => $record->assignedTo?->name ?? $record->assignedCounsellor?->name ?? 'Unassigned',
            'recommended_action' => $record instanceof Lead ? 'Open lead and assign or follow up.' : 'Open applicant and clear blockers.',
            'route' => $record instanceof Lead ? route('admission.leads.show', $record) : route('admission.applicants.show', $record),
        ])->values();
    }

    private function duplicateItems(Collection $leads): Collection
    {
        return $leads
            ->filter(fn (Lead $lead) => $lead->email || $lead->phone)
            ->groupBy(fn (Lead $lead) => strtolower((string) ($lead->email ?: $lead->phone)))
            ->filter(fn ($group) => $group->count() > 1)
            ->map(fn ($group) => [
                'subject_type' => Lead::class,
                'subject_id' => $group->first()->id,
                'title' => $group->first()->email ?: $group->first()->phone,
                'reason' => 'Possible duplicate leads',
                'severity' => 'warning',
                'due_at' => null,
                'owner' => 'Multiple',
                'recommended_action' => 'Review and merge duplicate leads.',
                'route' => route('admission.leads.show', $group->first()),
            ])->values();
    }

    private function documentItems(Collection $applicants): Collection
    {
        return ApplicantDocument::with(['applicant.user', 'requiredDocument'])
            ->whereIn('applicant_id', $applicants->pluck('id'))
            ->whereIn('status', ['pending', 'rejected'])
            ->latest()
            ->limit(25)
            ->get()
            ->map(fn (ApplicantDocument $document) => [
                'subject_type' => ApplicantDocument::class,
                'subject_id' => $document->id,
                'title' => $document->applicant?->user?->name ?? 'Applicant document',
                'reason' => $document->status === 'rejected' ? 'Rejected document' : 'Pending document verification',
                'severity' => $document->status === 'rejected' ? 'danger' : 'warning',
                'due_at' => null,
                'owner' => $document->applicant?->assignedCounsellor?->name ?? 'Unassigned',
                'recommended_action' => 'Verify document or request correction.',
                'route' => route('admission.applicants.show', $document->applicant),
            ]);
    }

    private function paymentItems(Collection $applicants): Collection
    {
        return AdmissionPayment::with(['applicant.user', 'installment'])
            ->whereIn('applicant_id', $applicants->pluck('id'))
            ->whereIn('status', ['pending', 'rejected'])
            ->latest()
            ->limit(25)
            ->get()
            ->map(fn (AdmissionPayment $payment) => [
                'subject_type' => AdmissionPayment::class,
                'subject_id' => $payment->id,
                'title' => $payment->applicant?->user?->name ?? 'Admission payment',
                'reason' => $payment->status === 'rejected' ? 'Rejected payment proof' : 'Pending payment verification',
                'severity' => $payment->status === 'rejected' ? 'danger' : 'warning',
                'due_at' => null,
                'owner' => $payment->applicant?->assignedCounsellor?->name ?? 'Unassigned',
                'recommended_action' => 'Verify payment or request correction.',
                'route' => route('admission.applicants.payments', $payment->applicant),
            ]);
    }

    private function sessionItems(array $filters): Collection
    {
        return SelectionSession::with(['program', 'batch'])
            ->whereDate('scheduled_date', today())
            ->when($filters['program_id'] ?? null, fn ($q, $programId) => $q->where('program_id', $programId))
            ->orderBy('start_time')
            ->get()
            ->map(fn (SelectionSession $session) => [
                'subject_type' => SelectionSession::class,
                'subject_id' => $session->id,
                'title' => $session->session_name,
                'reason' => 'Selection session today',
                'severity' => 'info',
                'due_at' => $session->scheduled_date,
                'owner' => $session->program?->name ?? 'Program',
                'recommended_action' => 'Mark attendance and score candidates.',
                'route' => route('admission.sessions.show', $session),
            ]);
    }

    private function offerItems(Collection $applicants): Collection
    {
        return OfferLetter::with(['applicant.user'])
            ->whereIn('applicant_id', $applicants->pluck('id'))
            ->where('status', 'issued')
            ->whereBetween('acceptance_deadline', [today(), today()->addDays(3)])
            ->orderBy('acceptance_deadline')
            ->limit(25)
            ->get()
            ->map(fn (OfferLetter $offer) => [
                'subject_type' => OfferLetter::class,
                'subject_id' => $offer->id,
                'title' => $offer->applicant?->user?->name ?? 'Offer letter',
                'reason' => 'Offer expiry risk',
                'severity' => 'warning',
                'due_at' => $offer->acceptance_deadline,
                'owner' => $offer->applicant?->assignedCounsellor?->name ?? 'Unassigned',
                'recommended_action' => 'Follow up before offer deadline.',
                'route' => route('admission.offer-letters.show', $offer),
            ]);
    }

    private function applyFilters($query, array $filters): void
    {
        $query
            ->when($filters['program_id'] ?? null, fn ($q, $programId) => $q->where('program_id', $programId))
            ->when($filters['priority'] ?? null, fn ($q, $priority) => $q->where('priority', $priority))
            ->when($filters['counsellor_id'] ?? null, fn ($q, $userId) => $q->where('assigned_to', $userId));
    }
}
