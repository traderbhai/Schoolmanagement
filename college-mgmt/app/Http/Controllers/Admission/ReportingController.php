<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\EnrollmentConfirmation;
use App\Models\Lead;
use App\Models\Program;
use App\Models\SeatMatrix;
use App\Services\AdmissionAccessPolicyService;
use App\Services\AdmissionKpiService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportingController extends Controller
{
    public function __construct(
        private AdmissionKpiService $kpis,
        private AdmissionAccessPolicyService $accessPolicy,
    ) {}

    public function index(Request $request)
    {
        $filters = $request->only(['program_id', 'priority', 'counsellor_id']);
        $kpiSummary = $this->kpis->summaryFor($request->user(), $filters);
        $kpiRollup = $this->kpis->rollupByUser($request->user(), $filters);
        $filterDescription = collect($filters)->filter()
            ->map(fn ($value, $key) => str_replace('_', ' ', $key) . ': ' . $value)
            ->implode(', ') ?: 'All visible admission records';

        return view('admission.reports.index', array_merge(
            $this->buildReportData($request),
            compact('kpiSummary', 'kpiRollup', 'filterDescription')
        ));
    }

    public function exportPdf(Request $request)
    {
        $pdf = Pdf::loadView('admission.reports.pdf', $this->buildReportData($request))
            ->setPaper('a4', 'portrait');

        return $pdf->stream('admission-report-' . now()->format('Y-m') . '.pdf');
    }

    private function buildReportData(Request $request): array
    {
        $scopedLeadQuery = $this->visibleLeadQuery($request);
        $scopedApplicantQuery = $this->visibleApplicantQuery($request);
        $visibleApplicantSubquery = fn () => (clone $scopedApplicantQuery)->select('id');

        $totalLeads = (clone $scopedLeadQuery)->count();
        $convertedLeads = (clone $scopedLeadQuery)->where('status', 'converted')->count();
        $totalApplicants = (clone $scopedApplicantQuery)->count();
        $submitted = (clone $scopedApplicantQuery)->where('status', 'submitted')->count();
        $underReview = (clone $scopedApplicantQuery)->where('status', 'under_review')->count();
        $shortlisted = (clone $scopedApplicantQuery)->where('status', 'shortlisted')->count();
        $selected = (clone $scopedApplicantQuery)->whereIn('status', ['selected', 'offer_accepted'])->count();
        $enrolled = EnrollmentConfirmation::where('status', 'completed')
            ->whereIn('applicant_id', $visibleApplicantSubquery())
            ->count();

        $funnel = [
            ['label' => 'Total Leads', 'count' => $totalLeads, 'color' => '#6366f1'],
            ['label' => 'Converted to App', 'count' => $convertedLeads, 'color' => '#8b5cf6'],
            ['label' => 'Applications', 'count' => $totalApplicants, 'color' => '#3b82f6'],
            ['label' => 'Submitted', 'count' => $submitted, 'color' => '#06b6d4'],
            ['label' => 'Under Review', 'count' => $underReview, 'color' => '#f59e0b'],
            ['label' => 'Shortlisted', 'count' => $shortlisted, 'color' => '#10b981'],
            ['label' => 'Selected', 'count' => $selected, 'color' => '#22c55e'],
            ['label' => 'Enrolled', 'count' => $enrolled, 'color' => '#15803d'],
        ];
        $funnelMax = max(array_column($funnel, 'count') ?: [1]);

        $monthlyTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthlyTrend[] = [
                'label' => $month->format('M Y'),
                'count' => (clone $scopedApplicantQuery)
                    ->whereYear('applied_at', $month->year)
                    ->whereMonth('applied_at', $month->month)
                    ->count(),
            ];
        }
        $trendMax = max(array_column($monthlyTrend, 'count') ?: [1]);

        $visibleProgramIds = (clone $scopedApplicantQuery)
            ->whereNotNull('program_id')
            ->distinct()
            ->pluck('program_id');

        $programStats = Program::where('is_active', true)
            ->whereIn('id', $visibleProgramIds)
            ->orderBy('name')
            ->get()
            ->map(function (Program $program) use ($scopedApplicantQuery) {
                return [
                    'name' => $program->name,
                    'code' => $program->code,
                    'total' => (clone $scopedApplicantQuery)->where('program_id', $program->id)->count(),
                    'submitted' => (clone $scopedApplicantQuery)->where('program_id', $program->id)->where('status', 'submitted')->count(),
                    'shortlisted' => (clone $scopedApplicantQuery)->where('program_id', $program->id)->where('status', 'shortlisted')->count(),
                    'selected' => (clone $scopedApplicantQuery)->where('program_id', $program->id)->whereIn('status', ['selected', 'offer_accepted'])->count(),
                    'rejected' => (clone $scopedApplicantQuery)->where('program_id', $program->id)->where('status', 'rejected')->count(),
                ];
            });

        $sourceStats = (clone $scopedLeadQuery)
            ->select('source', DB::raw('count(*) as total'), DB::raw("sum(case when status='converted' then 1 else 0 end) as converted"))
            ->groupBy('source')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'source' => ucwords(str_replace('_', ' ', (string) $row->source)),
                'total' => $row->total,
                'converted' => $row->converted,
                'conversion_pct' => $row->total > 0 ? round(($row->converted / $row->total) * 100, 1) : 0,
            ]);

        $categoryStats = (clone $scopedApplicantQuery)
            ->select('category', DB::raw('count(*) as total'))
            ->whereNotNull('category')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $yoyData = [];
        for ($y = 2; $y >= 0; $y--) {
            $year = now()->subYears($y)->year;
            $yoyData[] = [
                'year' => $year,
                'applicants' => (clone $scopedApplicantQuery)->whereYear('applied_at', $year)->count(),
                'enrolled' => EnrollmentConfirmation::where('status', 'completed')
                    ->whereIn('applicant_id', $visibleApplicantSubquery())
                    ->whereYear('confirmed_at', $year)
                    ->count(),
            ];
        }

        $aicteNorms = ['SC' => 15, 'ST' => 7.5, 'OBC' => 27, 'EWS' => 10, 'General' => 40.5];
        $totalIntake = (int) SeatMatrix::whereIn('program_id', $visibleProgramIds)->sum('total_seats');
        $mandateIntake = max(1, $totalIntake);
        $categoryCompliance = [];
        foreach ($aicteNorms as $category => $mandatePct) {
            $filled = (clone $scopedApplicantQuery)
                ->where('category', $category)
                ->whereIn('status', ['selected', 'offer_accepted', 'enrolled'])
                ->count();
            $mandateSeats = round($mandateIntake * $mandatePct / 100);
            $categoryCompliance[] = [
                'category' => $category,
                'mandate_pct' => $mandatePct,
                'mandate_seats' => $mandateSeats,
                'filled' => $filled,
                'fill_pct' => $mandateSeats > 0 ? round($filled / $mandateSeats * 100, 1) : 0,
                'compliant' => $filled >= $mandateSeats,
            ];
        }

        $counsellorStats = (clone $scopedLeadQuery)
            ->join('users', 'leads.assigned_to', '=', 'users.id')
            ->whereNotNull('leads.assigned_to')
            ->selectRaw('users.id, users.name, COUNT(*) as total_leads, SUM(CASE WHEN leads.status = ? THEN 1 ELSE 0 END) as converted', ['converted'])
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_leads')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'total_leads' => $row->total_leads,
                'converted' => $row->converted,
                'conversion_pct' => $row->total_leads > 0 ? round($row->converted / $row->total_leads * 100, 1) : 0,
            ]);

        $geoStats = (clone $scopedApplicantQuery)
            ->whereNotNull('personal_data')
            ->get()
            ->map(fn ($applicant) => data_get($applicant->personal_data, 'state')
                ?? data_get($applicant->personal_data, 'city')
                ?? 'Not Specified')
            ->countBy()
            ->sortDesc()
            ->take(10);

        return compact(
            'funnel',
            'funnelMax',
            'monthlyTrend',
            'trendMax',
            'programStats',
            'sourceStats',
            'categoryStats',
            'totalLeads',
            'totalApplicants',
            'selected',
            'enrolled',
            'yoyData',
            'categoryCompliance',
            'counsellorStats',
            'geoStats',
            'totalIntake'
        );
    }

    private function visibleLeadQuery(Request $request)
    {
        $query = Lead::query();
        $this->accessPolicy->applyLeadVisibility($query, $request->user());
        $this->applyReportFilters($query, $request);

        return $query;
    }

    private function visibleApplicantQuery(Request $request)
    {
        $query = Applicant::query();
        $this->accessPolicy->applyApplicantVisibility($query, $request->user());
        $this->applyReportFilters($query, $request);

        return $query;
    }

    private function applyReportFilters($query, Request $request): void
    {
        $query
            ->when($request->filled('program_id'), fn ($q) => $q->where('program_id', $request->program_id))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->priority))
            ->when($request->filled('counsellor_id'), fn ($q) => $q->where('assigned_to', $request->counsellor_id));
    }
}
