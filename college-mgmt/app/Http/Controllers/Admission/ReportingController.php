<?php
namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\EnrollmentConfirmation;
use App\Models\Lead;
use App\Models\Program;
use App\Models\SeatMatrix;
use App\Models\User;
use App\Services\AdmissionKpiService;
use App\Services\DepartmentHierarchyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportingController extends Controller
{
    public function __construct(
        private AdmissionKpiService $kpis,
        private DepartmentHierarchyService $hierarchy,
    ) {}

    public function index(Request $request)
    {
        $filters = $request->only(['program_id', 'priority', 'counsellor_id']);
        $kpiSummary = $this->kpis->summaryFor($request->user(), $filters);
        $kpiRollup = $this->kpis->rollupByUser($request->user(), $filters);
        $filterDescription = collect($filters)->filter()->map(fn ($value, $key) => str_replace('_', ' ', $key) . ': ' . $value)->implode(', ') ?: 'All visible admission records';
        $scopedLeadQuery = Lead::query();
        $this->hierarchy->applyLeadVisibility($scopedLeadQuery, $request->user(), 'ADM');
        $scopedApplicantQuery = Applicant::query();
        $this->hierarchy->applyApplicantVisibility($scopedApplicantQuery, $request->user(), 'ADM');
        // ── Conversion Funnel ──────────────────────────────────────────────
        $totalLeads    = (clone $scopedLeadQuery)->count();
        $convertedLeads = (clone $scopedLeadQuery)->where('status', 'converted')->count();
        $totalApplicants = (clone $scopedApplicantQuery)->count();
        $submitted     = (clone $scopedApplicantQuery)->where('status', 'submitted')->count();
        $underReview   = (clone $scopedApplicantQuery)->where('status', 'under_review')->count();
        $shortlisted   = (clone $scopedApplicantQuery)->where('status', 'shortlisted')->count();
        $selected      = (clone $scopedApplicantQuery)->where('status', 'selected')->count();
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

        // ── YoY Applications (last 3 years) ─────────────────────────────────
        $yoyData = [];
        for ($y = 2; $y >= 0; $y--) {
            $year = now()->subYears($y)->year;
            $yoyData[] = [
                'year'       => $year,
                'applicants' => Applicant::whereYear('applied_at', $year)->count(),
                'enrolled'   => EnrollmentConfirmation::where('status', 'completed')
                                    ->whereYear('confirmed_at', $year)->count(),
            ];
        }

        // ── Category Compliance (AICTE mandates) ─────────────────────────────
        // AICTE mandates: SC 15%, ST 7.5%, OBC 27%, EWS 10% of total intake
        $aicteNorms = ['SC' => 15, 'ST' => 7.5, 'OBC' => 27, 'EWS' => 10, 'General' => 40.5];
        $totalIntake = SeatMatrix::sum('total_seats') ?: 1;
        $categoryCompliance = [];
        foreach ($aicteNorms as $cat => $mandatePct) {
            $filled = Applicant::where('category', $cat)
                        ->whereIn('status', ['selected', 'offer_accepted', 'enrolled'])
                        ->count();
            $mandateSeats = round($totalIntake * $mandatePct / 100);
            $categoryCompliance[] = [
                'category'     => $cat,
                'mandate_pct'  => $mandatePct,
                'mandate_seats'=> $mandateSeats,
                'filled'       => $filled,
                'fill_pct'     => $mandateSeats > 0 ? round($filled / $mandateSeats * 100, 1) : 0,
                'compliant'    => $filled >= $mandateSeats,
            ];
        }

        // ── Counsellor Performance ─────────────────────────────────────────
        $counsellorStats = DB::table('leads')
            ->join('users', 'leads.assigned_to', '=', 'users.id')
            ->whereNotNull('leads.assigned_to')
            ->selectRaw('users.id, users.name, COUNT(*) as total_leads,
                SUM(CASE WHEN leads.status = ? THEN 1 ELSE 0 END) as converted', ['converted'])
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_leads')
            ->limit(10)
            ->get()
            ->map(fn($r) => [
                'name'           => $r->name,
                'total_leads'    => $r->total_leads,
                'converted'      => $r->converted,
                'conversion_pct' => $r->total_leads > 0 ? round($r->converted / $r->total_leads * 100, 1) : 0,
            ]);

        // ── Geographic Distribution (from personal_data JSON) ─────────────
        $geoStats = Applicant::whereNotNull('personal_data')->get()
            ->map(fn($a) => data_get($a->personal_data, 'state')
                         ?? data_get($a->personal_data, 'city')
                         ?? 'Not Specified')
            ->countBy()
            ->sortDesc()
            ->take(10);

        return view('admission.reports.index', compact(
            'funnel', 'funnelMax',
            'monthlyTrend', 'trendMax',
            'programStats',
            'sourceStats',
            'categoryStats',
            'totalLeads', 'totalApplicants', 'selected', 'enrolled',
            'yoyData',
            'categoryCompliance',
            'counsellorStats',
            'geoStats',
            'kpiSummary',
            'kpiRollup',
            'filterDescription'
        ));
    }

    public function exportPdf()
    {
        // Re-run all the same computations for the PDF
        $totalLeads    = Lead::count();
        $totalApplicants = Applicant::count();
        $selected      = Applicant::whereIn('status', ['selected', 'offer_accepted'])->count();
        $enrolled      = EnrollmentConfirmation::where('status', 'completed')->count();

        $programStats = Program::where('is_active', true)->orderBy('name')->get()->map(fn($p) => [
            'name'        => $p->name,
            'code'        => $p->code,
            'total'       => Applicant::where('program_id', $p->id)->count(),
            'shortlisted' => Applicant::where('program_id', $p->id)->where('status', 'shortlisted')->count(),
            'selected'    => Applicant::where('program_id', $p->id)->where('status', 'selected')->count(),
            'rejected'    => Applicant::where('program_id', $p->id)->where('status', 'rejected')->count(),
        ]);

        $sourceStats = Lead::select('source', DB::raw('count(*) as total'),
                                    DB::raw("sum(case when status='converted' then 1 else 0 end) as converted"))
                           ->groupBy('source')->orderByDesc('total')->get()
                           ->map(fn($r) => [
                               'source'         => ucwords(str_replace('_', ' ', $r->source)),
                               'total'          => $r->total,
                               'converted'      => $r->converted,
                               'conversion_pct' => $r->total > 0 ? round($r->converted / $r->total * 100, 1) : 0,
                           ]);

        $aicteNorms = ['SC' => 15, 'ST' => 7.5, 'OBC' => 27, 'EWS' => 10, 'General' => 40.5];
        $totalIntake = SeatMatrix::sum('total_seats') ?: 1;
        $categoryCompliance = [];
        foreach ($aicteNorms as $cat => $mandatePct) {
            $filled = Applicant::where('category', $cat)
                        ->whereIn('status', ['selected', 'offer_accepted', 'enrolled'])->count();
            $mandateSeats = round($totalIntake * $mandatePct / 100);
            $categoryCompliance[] = [
                'category'      => $cat,
                'mandate_pct'   => $mandatePct,
                'mandate_seats' => $mandateSeats,
                'filled'        => $filled,
                'fill_pct'      => $mandateSeats > 0 ? round($filled / $mandateSeats * 100, 1) : 0,
                'compliant'     => $filled >= $mandateSeats,
            ];
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admission.reports.pdf', compact(
            'totalLeads', 'totalApplicants', 'selected', 'enrolled',
            'programStats', 'sourceStats', 'categoryCompliance'
        ))->setPaper('a4', 'portrait');

        return $pdf->stream('admission-report-' . now()->format('Y-m') . '.pdf');
    }
}
