<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\CounsellingLog;
use App\Models\SelectionSession;
use App\Services\AdmissionKpiDrilldownService;

class DashboardController extends Controller
{
    public function index(AdmissionKpiDrilldownService $drilldowns)
    {
        ['kpis' => $kpis, 'pipeline' => $pipeline, 'pipelineMax' => $pipelineMax, 'funnelData' => $funnelData]
            = $drilldowns->dashboard(request()->user());

        // Upcoming follow-ups (next 7 days)
        $followups = CounsellingLog::with(['applicant.user'])
            ->whereHas('applicant', fn ($query) => $drilldowns->applyApplicantVisibility($query, request()->user()))
            ->whereBetween('next_followup_date', [today(), today()->addDays(7)])
            ->orderBy('next_followup_date')
            ->limit(10)
            ->get();

        // Recent interactions
        $recentLogs = CounsellingLog::with(['applicant.user', 'loggedBy'])
            ->whereHas('applicant', fn ($query) => $drilldowns->applyApplicantVisibility($query, request()->user()))
            ->latest()
            ->limit(8)
            ->get();

        // Upcoming sessions (next 3)
        $upcomingSessions = SelectionSession::with(['step', 'program', 'sessionApplicants'])
            ->upcoming()
            ->limit(3)
            ->get();

        return view('admission.dashboard', compact('kpis', 'pipeline', 'pipelineMax', 'followups', 'recentLogs', 'upcomingSessions', 'funnelData'));
    }
}
