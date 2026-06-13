<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Lead;
use App\Services\AdmissionAttentionService;
use App\Services\AdmissionCallService;
use App\Services\AdmissionKpiService;
use App\Services\AdmissionReminderService;
use App\Services\DepartmentHierarchyService;
use Illuminate\Http\Request;

class CounsellorWorkspaceController extends Controller
{
    public function __invoke(
        Request $request,
        DepartmentHierarchyService $hierarchy,
        AdmissionAttentionService $attention,
        AdmissionKpiService $kpis,
        AdmissionCallService $calls,
        AdmissionReminderService $reminders,
    ) {
        $user = $request->user();

        $leadQuery = Lead::with(['program', 'assignedTo'])->latest();
        $hierarchy->applyLeadVisibility($leadQuery, $user, 'ADM');
        $applicantQuery = Applicant::with(['user', 'program', 'assignedCounsellor'])->latest();
        $hierarchy->applyApplicantVisibility($applicantQuery, $user, 'ADM');

        return view('admission.v0031.counsellor-workspace', [
            'assignedLeads' => (clone $leadQuery)->where('assigned_to', $user->id)->limit(10)->get(),
            'assignedApplicants' => (clone $applicantQuery)->where('assigned_to', $user->id)->limit(10)->get(),
            'hotLeads' => (clone $leadQuery)->whereIn('priority', ['urgent', 'high'])->limit(10)->get(),
            'blockedApplicants' => (clone $applicantQuery)->whereIn('status', ['submitted', 'under_review', 'shortlisted'])->limit(10)->get(),
            'attentionQueues' => $attention->queuesFor($user),
            'reminders' => $reminders->dueFor($user, ['limit' => 12]),
            'kpi' => $kpis->summaryFor($user),
            'callProductivity' => $calls->productivityFor($user),
        ]);
    }
}
