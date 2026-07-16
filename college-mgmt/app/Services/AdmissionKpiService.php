<?php

namespace App\Services;

use App\Models\AdmissionPayment;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\CounsellingLog;
use App\Models\EnrollmentConfirmation;
use App\Models\Lead;
use App\Models\OfferLetter;
use App\Models\User;
use Illuminate\Support\Collection;

class AdmissionKpiService
{
    public function __construct(private AdmissionAccessPolicyService $accessPolicy) {}

    public function summaryFor(User $user, array $filters = []): array
    {
        $leadQuery = Lead::query();
        $this->accessPolicy->applyLeadVisibility($leadQuery, $user);
        $applicantQuery = Applicant::query();
        $this->accessPolicy->applyApplicantVisibility($applicantQuery, $user);

        foreach ([$leadQuery, $applicantQuery] as $query) {
            $query
                ->when($filters['program_id'] ?? null, fn ($q, $programId) => $q->where('program_id', $programId))
                ->when($filters['priority'] ?? null, fn ($q, $priority) => $q->where('priority', $priority))
                ->when($filters['counsellor_id'] ?? null, fn ($q, $userId) => $q->where('assigned_to', $userId));
        }

        $leadIds = (clone $leadQuery)->pluck('id');
        $applicantIds = (clone $applicantQuery)->pluck('id');
        $totalLeads = $leadIds->count();
        $convertedLeads = Lead::whereIn('id', $leadIds)->where('status', 'converted')->count();
        $assignedLeadIds = Lead::whereIn('id', $leadIds)->whereNotNull('assigned_to')->pluck('id');

        return [
            'workload' => Lead::whereIn('id', $leadIds)->whereNotNull('assigned_to')->count()
                + Applicant::whereIn('id', $applicantIds)->whereNotNull('assigned_to')->count(),
            'new_leads' => Lead::whereIn('id', $leadIds)->where('status', 'new')->count(),
            'contacted_leads' => Lead::whereIn('id', $leadIds)->where('status', 'contacted')->count(),
            'interested_leads' => Lead::whereIn('id', $leadIds)->where('status', 'interested')->count(),
            'converted_leads' => $convertedLeads,
            'application_conversion_pct' => $totalLeads > 0 ? round($convertedLeads / $totalLeads * 100, 1) : 0,
            'avg_first_response_hours' => $this->averageFirstResponseHours($assignedLeadIds),
            'followup_compliance_pct' => $this->followupCompliance($assignedLeadIds),
            'sla_breaches' => Lead::whereIn('id', $leadIds)->whereNotNull('sla_due_at')->where('sla_due_at', '<', now())->count()
                + Applicant::whereIn('id', $applicantIds)->whereNotNull('sla_due_at')->where('sla_due_at', '<', now())->count(),
            'stale_leads' => Lead::whereIn('id', $leadIds)->where(fn ($q) => $q->whereNull('last_activity_at')->orWhere('last_activity_at', '<', now()->subDays(3)))->count(),
            'document_bottlenecks' => ApplicantDocument::whereIn('applicant_id', $applicantIds)->whereIn('status', ['pending', 'rejected'])->count(),
            'payment_bottlenecks' => AdmissionPayment::whereIn('applicant_id', $applicantIds)->whereIn('status', ['pending', 'rejected'])->count(),
            'offer_acceptance_pct' => $this->offerAcceptance($applicantIds),
            'enrollment_yield_pct' => $this->enrollmentYield($applicantIds),
        ];
    }

    public function rollupByUser(User $viewer, array $filters = []): Collection
    {
        $visibleIds = $this->accessPolicy->canSeeAll($viewer)
            ? User::whereHas('roles', fn ($q) => $q->whereIn('name', $this->accessPolicy->admissionRoleNames()))->pluck('id')
            : $this->accessPolicy->visibleUserIds($viewer);

        return User::whereIn('id', $visibleIds)->orderBy('name')->get()
            ->map(fn (User $user) => array_merge(['user_id' => $user->id, 'name' => $user->name], $this->summaryFor($viewer, $filters + ['counsellor_id' => $user->id])));
    }

    private function averageFirstResponseHours(Collection $leadIds): float
    {
        $leads = Lead::whereIn('id', $leadIds)->whereNotNull('last_contacted_at')->get(['created_at', 'last_contacted_at']);
        if ($leads->isEmpty()) {
            return 0;
        }

        return round($leads->avg(fn (Lead $lead) => $lead->created_at->diffInMinutes($lead->last_contacted_at) / 60), 1);
    }

    private function followupCompliance(Collection $leadIds): float
    {
        $total = CounsellingLog::whereHas('applicant', fn ($q) => $q->whereNotNull('assigned_to'))->whereNotNull('next_followup_date')->count();
        if ($total === 0) {
            return 100;
        }

        $overdue = CounsellingLog::whereNotNull('next_followup_date')->whereDate('next_followup_date', '<', today())->count();

        return round(max(0, ($total - $overdue) / $total * 100), 1);
    }

    private function offerAcceptance(Collection $applicantIds): float
    {
        $offers = OfferLetter::whereIn('applicant_id', $applicantIds)->count();
        if ($offers === 0) {
            return 0;
        }

        return round(OfferLetter::whereIn('applicant_id', $applicantIds)->where('status', 'accepted')->count() / $offers * 100, 1);
    }

    private function enrollmentYield(Collection $applicantIds): float
    {
        $selected = Applicant::whereIn('id', $applicantIds)->where('status', 'selected')->count();
        if ($selected === 0) {
            return 0;
        }

        return round(EnrollmentConfirmation::whereIn('applicant_id', $applicantIds)->where('status', 'completed')->count() / $selected * 100, 1);
    }
}
