<?php
namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\EnrollmentConfirmation;
use App\Models\Lead;
use App\Models\Program;
use Illuminate\Support\Facades\DB;

class ReportingController extends Controller
{
    public function index()
    {
        // ── Conversion Funnel ──────────────────────────────────────────────
        $totalLeads    = Lead::count();
        $convertedLeads = Lead::where('status', 'converted')->count();
        $totalApplicants = Applicant::count();
        $submitted     = Applicant::where('status', 'submitted')->count();
        $underReview   = Applicant::where('status', 'under_review')->count();
        $shortlisted   = Applicant::where('status', 'shortlisted')->count();
        $selected      = Applicant::where('status', 'selected')->count();
        $enrolled      = EnrollmentConfirmation::where('status', 'completed')->count();

        $funnel = [
            ['label' => 'Total Leads',        'count' => $totalLeads,      'color' => '#6366f1'],
            ['label' => 'Converted to App',   'count' => $convertedLeads,  'color' => '#8b5cf6'],
            ['label' => 'Applications',       'count' => $totalApplicants, 'color' => '#3b82f6'],
            ['label' => 'Submitted',          'count' => $submitted,       'color' => '#06b6d4'],
            ['label' => 'Under Review',       'count' => $underReview,     'color' => '#f59e0b'],
            ['label' => 'Shortlisted',        'count' => $shortlisted,     'color' => '#10b981'],
            ['label' => 'Selected',           'count' => $selected,        'color' => '#22c55e'],
            ['label' => 'Enrolled',           'count' => $enrolled,        'color' => '#15803d'],
        ];
        $funnelMax = max(array_column($funnel, 'count') ?: [1]);

        // ── Monthly trend: applications submitted (last 6 months) ─────────
        $monthlyTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthlyTrend[] = [
                'label' => $month->format('M Y'),
                'count' => Applicant::whereYear('applied_at', $month->year)
                                    ->whereMonth('applied_at', $month->month)
                                    ->count(),
            ];
        }
        $trendMax = max(array_column($monthlyTrend, 'count') ?: [1]);

        // ── Program-wise breakdown ────────────────────────────────────────
        $programs = Program::where('is_active', true)->orderBy('name')->get();
        $programStats = $programs->map(function ($p) {
            return [
                'name'        => $p->name,
                'code'        => $p->code,
                'total'       => Applicant::where('program_id', $p->id)->count(),
                'submitted'   => Applicant::where('program_id', $p->id)->where('status', 'submitted')->count(),
                'shortlisted' => Applicant::where('program_id', $p->id)->where('status', 'shortlisted')->count(),
                'selected'    => Applicant::where('program_id', $p->id)->where('status', 'selected')->count(),
                'rejected'    => Applicant::where('program_id', $p->id)->where('status', 'rejected')->count(),
            ];
        });

        // ── Lead source breakdown ─────────────────────────────────────────
        $sourceStats = Lead::select('source', DB::raw('count(*) as total'),
                                    DB::raw("sum(case when status='converted' then 1 else 0 end) as converted"))
                           ->groupBy('source')
                           ->orderByDesc('total')
                           ->get()
                           ->map(fn($r) => [
                               'source'         => ucwords(str_replace('_', ' ', $r->source)),
                               'total'          => $r->total,
                               'converted'      => $r->converted,
                               'conversion_pct' => $r->total > 0 ? round(($r->converted / $r->total) * 100, 1) : 0,
                           ]);

        // ── Category breakdown (applicants) ──────────────────────────────
        $categoryStats = Applicant::select('category', DB::raw('count(*) as total'))
                                  ->whereNotNull('category')
                                  ->groupBy('category')
                                  ->orderByDesc('total')
                                  ->get();

        return view('admission.reports.index', compact(
            'funnel', 'funnelMax',
            'monthlyTrend', 'trendMax',
            'programStats',
            'sourceStats',
            'categoryStats',
            'totalLeads', 'totalApplicants', 'selected', 'enrolled'
        ));
    }
}
