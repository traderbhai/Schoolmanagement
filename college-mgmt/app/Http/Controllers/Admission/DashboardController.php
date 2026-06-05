<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\CounsellingLog;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // KPI counts
        $kpis = [
            'total'         => Applicant::count(),
            'submitted'     => Applicant::where('status', 'submitted')->count(),
            'under_review'  => Applicant::where('status', 'under_review')->count(),
            'shortlisted'   => Applicant::where('status', 'shortlisted')->count(),
            'selected'      => Applicant::where('status', 'selected')->count(),
            'rejected'      => Applicant::where('status', 'rejected')->count(),
            'submitted_today' => Applicant::whereDate('applied_at', today())->count(),
        ];

        // Pipeline funnel data (status order)
        $pipeline = [];
        $statuses = ['submitted', 'under_review', 'shortlisted', 'selected'];
        foreach ($statuses as $s) {
            $pipeline[$s] = Applicant::where('status', $s)->count();
        }
        $pipelineMax = max(array_values($pipeline) ?: [1]);

        // Upcoming follow-ups (next 7 days)
        $followups = CounsellingLog::with(['applicant.user'])
            ->whereBetween('next_followup_date', [today(), today()->addDays(7)])
            ->orderBy('next_followup_date')
            ->limit(10)
            ->get();

        // Recent interactions
        $recentLogs = CounsellingLog::with(['applicant.user', 'loggedBy'])
            ->latest()
            ->limit(8)
            ->get();

        return view('admission.dashboard', compact('kpis', 'pipeline', 'pipelineMax', 'followups', 'recentLogs'));
    }
}
