<?php

namespace App\Services;

use App\Models\AdmissionPayment;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\EnrollmentConfirmation;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AdmissionKpiDrilldownService
{
    public function __construct(private AdmissionAccessPolicyService $accessPolicy) {}

    public function applicantQuery(User $user): Builder
    {
        $query = Applicant::query();
        $this->applyApplicantVisibility($query, $user);

        return $query;
    }

    public function applyApplicantVisibility($query, User $user): void
    {
        $this->accessPolicy->applyApplicantVisibility($query, $user);
    }

    public function leadQuery(User $user): Builder
    {
        $query = Lead::query();
        $this->applyLeadVisibility($query, $user);

        return $query;
    }

    public function applyLeadVisibility($query, User $user): void
    {
        $this->accessPolicy->applyLeadVisibility($query, $user);
    }

    public function pendingDocumentQuery(User $user): Builder
    {
        return ApplicantDocument::query()
            ->where('status', 'pending')
            ->whereHas('applicant', function ($query) use ($user) {
                $this->applyApplicantVisibility($query, $user);
            });
    }

    public function pendingPaymentQuery(User $user): Builder
    {
        return AdmissionPayment::query()
            ->where('admission_payments.status', 'pending')
            ->whereHas('applicant', function ($query) use ($user) {
                $this->applyApplicantVisibility($query, $user);
            });
    }

    public function completedEnrollmentQuery(User $user): Builder
    {
        return EnrollmentConfirmation::query()
            ->where('status', 'completed')
            ->whereHas('applicant', function ($query) use ($user) {
                $this->applyApplicantVisibility($query, $user);
            });
    }

    public function dashboard(User $user): array
    {
        $applicants = $this->applicantQuery($user);
        $leads = $this->leadQuery($user);

        $kpis = [
            'total' => (clone $applicants)->count(),
            'submitted' => (clone $applicants)->where('status', 'submitted')->count(),
            'under_review' => (clone $applicants)->where('status', 'under_review')->count(),
            'shortlisted' => (clone $applicants)->where('status', 'shortlisted')->count(),
            'selected' => (clone $applicants)->where('status', 'selected')->count(),
            'rejected' => (clone $applicants)->where('status', 'rejected')->count(),
            'submitted_today' => (clone $applicants)->whereDate('applied_at', today())->count(),
            'docs_pending' => $this->pendingDocumentQuery($user)->count(),
            'payments_pending' => $this->pendingPaymentQuery($user)->count(),
        ];

        $pipeline = [
            'submitted' => $kpis['submitted'],
            'under_review' => $kpis['under_review'],
            'shortlisted' => $kpis['shortlisted'],
            'selected' => $kpis['selected'],
        ];

        $funnelData = [
            'leads' => (clone $leads)->count(),
            'applied' => $kpis['total'],
            'shortlisted' => $kpis['shortlisted'],
            'selected' => $kpis['selected'],
            'enrolled' => $this->completedEnrollmentQuery($user)->count(),
        ];

        return [
            'kpis' => $kpis,
            'pipeline' => $pipeline,
            'pipelineMax' => max(array_values($pipeline) ?: [1]),
            'funnelData' => $funnelData,
        ];
    }
}
