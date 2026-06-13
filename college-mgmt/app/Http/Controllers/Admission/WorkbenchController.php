<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionPayment;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\CounsellingLog;
use App\Models\Lead;
use App\Models\OfferLetter;
use App\Models\Program;
use App\Models\SelectionSession;
use App\Models\User;
use App\Services\AdmissionApplicantReadinessService;
use App\Services\AdmissionAttentionService;
use App\Services\AdmissionKpiService;
use App\Services\DepartmentHierarchyService;
use Illuminate\Http\Request;

class WorkbenchController extends Controller
{
    public function __construct(
        private AdmissionApplicantReadinessService $readiness,
        private DepartmentHierarchyService $hierarchy,
        private AdmissionAttentionService $attention,
        private AdmissionKpiService $kpis,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $programs = Program::where('is_active', true)->orderBy('name')->get();
        $counsellors = User::whereHas('roles', function ($query) {
            $query->whereIn('name', DepartmentHierarchyService::ADMISSION_ROLE_NAMES);
        })->orderBy('name')->get();

        $programId = $request->integer('program_id') ?: null;
        $counsellorId = $request->integer('counsellor_id') ?: null;
        $priority = $request->get('priority');
        $filters = array_filter([
            'program_id' => $programId,
            'counsellor_id' => $counsellorId,
            'priority' => $priority,
        ], fn ($value) => $value !== null && $value !== '');

        $leadScope = Lead::query();
        $applicantScope = Applicant::query();
        $visibleUserIds = $this->hierarchy->visibleUserIds($user, 'ADM');
        $canSeeAllByHierarchy = $this->hierarchy->canSeeAll($user, 'ADM');

        if ($canSeeAllByHierarchy || $user->hasRole('admin')) {
            if ($counsellorId) {
                $leadScope->where('assigned_to', $counsellorId);
                $applicantScope->where('assigned_to', $counsellorId);
            }
        } elseif ($visibleUserIds->count() > 1) {
            $leadScope->where(function ($query) use ($visibleUserIds) {
                $query->whereIn('assigned_to', $visibleUserIds)->orWhereNull('assigned_to');
            });
            $applicantScope->whereIn('assigned_to', $visibleUserIds);
        } elseif ($visibleUserIds->count() <= 1) {
            $leadScope->where(function ($query) use ($user) {
                $query->where('assigned_to', $user->id)->orWhereNull('assigned_to');
            });
            $applicantScope->where('assigned_to', $user->id);
        }

        if ($programId) {
            $leadScope->where('program_id', $programId);
            $applicantScope->where('program_id', $programId);
        }

        if ($priority) {
            $leadScope->where('priority', $priority);
            $applicantScope->where('priority', $priority);
        }

        $leads = (clone $leadScope)->with(['program', 'assignedTo'])->latest()->limit(100)->get();
        $applicants = (clone $applicantScope)->with(['user', 'program', 'batch'])->latest()->limit(100)->get();

        $overdueFollowUps = CounsellingLog::with(['applicant.user', 'applicant.program', 'loggedBy'])
            ->whereDate('next_followup_date', '<', today())
            ->whereHas('applicant', fn ($query) => $this->applyApplicantScope($query, $user, $programId, $counsellorId, $priority, $visibleUserIds, $canSeeAllByHierarchy))
            ->latest('next_followup_date')
            ->limit(20)
            ->get();

        $pendingDocuments = ApplicantDocument::with(['applicant.user', 'applicant.program', 'requiredDocument'])
            ->where('status', 'pending')
            ->whereHas('applicant', fn ($query) => $this->applyApplicantScope($query, $user, $programId, $counsellorId, $priority, $visibleUserIds, $canSeeAllByHierarchy))
            ->latest()
            ->limit(20)
            ->get();

        $pendingPayments = AdmissionPayment::with(['applicant.user', 'applicant.program', 'installment'])
            ->where('status', 'pending')
            ->whereHas('applicant', fn ($query) => $this->applyApplicantScope($query, $user, $programId, $counsellorId, $priority, $visibleUserIds, $canSeeAllByHierarchy))
            ->latest()
            ->limit(20)
            ->get();

        $sessionsToday = SelectionSession::with(['program', 'batch'])
            ->whereDate('scheduled_date', today())
            ->when($programId, fn ($query) => $query->where('program_id', $programId))
            ->orderBy('start_time')
            ->get();

        $offerExpiryRisk = OfferLetter::with(['applicant.user', 'program'])
            ->where('status', 'issued')
            ->whereBetween('acceptance_deadline', [today(), today()->addDays(3)])
            ->whereHas('applicant', fn ($query) => $this->applyApplicantScope($query, $user, $programId, $counsellorId, $priority, $visibleUserIds, $canSeeAllByHierarchy))
            ->orderBy('acceptance_deadline')
            ->limit(20)
            ->get();

        $enrollmentReady = $applicants
            ->filter(fn (Applicant $applicant) => $this->readiness->isEnrollmentReady($applicant) && !$applicant->isEnrolled())
            ->take(20);

        $duplicateLeads = $leads
            ->filter(fn (Lead $lead) => $lead->email || $lead->phone)
            ->groupBy(fn (Lead $lead) => strtolower((string) ($lead->email ?: $lead->phone)))
            ->filter(fn ($group) => $group->count() > 1);
        $attentionQueues = $this->attention->queuesFor($user, $filters);
        $kpiSummary = $this->kpis->summaryFor($user, $filters);
        $kpiRollup = $this->kpis->rollupByUser($user, $filters)->take(10);

        return view('admission.workbench', compact(
            'programs', 'counsellors', 'programId', 'counsellorId', 'priority',
            'leads', 'applicants', 'overdueFollowUps', 'pendingDocuments',
            'pendingPayments', 'sessionsToday', 'offerExpiryRisk', 'enrollmentReady',
            'duplicateLeads', 'attentionQueues', 'kpiSummary', 'kpiRollup'
        ));
    }

    private function applyApplicantScope($query, User $user, ?int $programId, ?int $counsellorId, ?string $priority, $visibleUserIds, bool $canSeeAllByHierarchy): void
    {
        if ($canSeeAllByHierarchy || $user->hasRole('admin')) {
            if ($counsellorId) {
                $query->where('assigned_to', $counsellorId);
            }
        } elseif ($visibleUserIds->count() > 1) {
            $query->whereIn('assigned_to', $visibleUserIds);
        } elseif ($visibleUserIds->count() <= 1) {
            $query->where('assigned_to', $user->id);
        }

        $query
            ->when($programId, fn ($q) => $q->where('program_id', $programId))
            ->when($priority, fn ($q) => $q->where('priority', $priority));
    }
}
