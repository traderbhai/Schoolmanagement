<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionCallLog;
use App\Models\AdmissionReminderSchedule;
use App\Models\Lead;
use App\Services\AdmissionAccessPolicyService;
use App\Services\AdmissionAttentionService;
use App\Services\AdmissionKpiService;
use App\Services\AdmissionManagerReviewService;
use Illuminate\Http\Request;

class ManagerWorkspaceController extends Controller
{
    public function __invoke(
        Request $request,
        AdmissionAccessPolicyService $accessPolicy,
        AdmissionAttentionService $attention,
        AdmissionKpiService $kpis,
        AdmissionManagerReviewService $reviews,
    ) {
        $user = $request->user();
        $leadQuery = Lead::with(['program', 'assignedTo'])->latest();
        $accessPolicy->applyLeadVisibility($leadQuery, $user);

        return view('admission.v0031.manager-workspace', [
            'teamKpis' => $kpis->rollupByUser($user),
            'attentionQueues' => $attention->queuesFor($user),
            'unassignedLeads' => (clone $leadQuery)->whereNull('assigned_to')->limit(12)->get(),
            'staleLeads' => (clone $leadQuery)->where(fn ($q) => $q->whereNull('last_activity_at')->orWhere('last_activity_at', '<', now()->subDays(3)))->limit(12)->get(),
            'pendingReviews' => $reviews->queueFor($user, ['status' => 'pending']),
            'reminderStats' => AdmissionReminderSchedule::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'callStats' => AdmissionCallLog::selectRaw('disposition, count(*) as total')->groupBy('disposition')->pluck('total', 'disposition'),
        ]);
    }
}
