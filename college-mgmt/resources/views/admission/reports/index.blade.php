@extends('layouts.admin')

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- ── Header ─────────────────────────────────────────────────────────── --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h4 class="mb-0 fw-bold">Admission Reports</h4>
            <small class="text-muted">Conversion funnel &amp; analytics</small>
        </div>

        {{-- KPI Chips --}}
        <div class="d-flex flex-wrap gap-2">
            <span class="badge rounded-pill fs-6 px-3 py-2" style="background:#6366f1">
                <span class="fw-normal opacity-75">Total Leads</span>
                &nbsp;<strong>{{ number_format($totalLeads) }}</strong>
            </span>
            <span class="badge rounded-pill fs-6 px-3 py-2" style="background:#3b82f6">
                <span class="fw-normal opacity-75">Total Applications</span>
                &nbsp;<strong>{{ number_format($totalApplicants) }}</strong>
            </span>
            <span class="badge rounded-pill fs-6 px-3 py-2" style="background:#22c55e">
                <span class="fw-normal opacity-75">Selected</span>
                &nbsp;<strong>{{ number_format($selected) }}</strong>
            </span>
            <span class="badge rounded-pill fs-6 px-3 py-2" style="background:#15803d">
                <span class="fw-normal opacity-75">Enrolled</span>
                &nbsp;<strong>{{ number_format($enrolled) }}</strong>
            </span>
        </div>
    </div>

    {{-- ── Section 1: Conversion Funnel ───────────────────────────────────── --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-semibold">
                <i class="bi bi-filter me-2 text-primary"></i>Conversion Funnel
            </h6>
        </div>
        <div class="card-body">
            @foreach($funnel as $i => $step)
                {{-- Conversion rate arrow between steps --}}
                @if($i > 0)
                    @php
                        $prev = $funnel[$i - 1]['count'];
                        $curr = $step['count'];
                        $pct  = $prev > 0 ? round(($curr / $prev) * 100, 1) : 0;
                    @endphp
                    <div class="text-muted small ps-2 py-1" style="line-height:1">
                        &#8595; {{ $pct }}% conversion
                    </div>
                @endif

                <div class="d-flex align-items-center gap-3 mb-1">
                    {{-- Label --}}
                    <div style="width:140px;flex-shrink:0;font-size:13px;font-weight:500;color:#374151">
                        {{ $step['label'] }}
                    </div>

                    {{-- Bar --}}
                    @php
                        $widthPct = $funnelMax > 0 ? max(2, round(($step['count'] / $funnelMax) * 100)) : 2;
                    @endphp
                    <div style="flex:1;background:#f3f4f6;border-radius:4px;height:28px;overflow:hidden">
                        <div style="
                            width:{{ $widthPct }}%;
                            height:100%;
                            background:{{ $step['color'] }};
                            border-radius:4px;
                            transition:width .3s ease;
                            display:flex;
                            align-items:center;
                            padding-left:8px;
                            min-width:28px;
                        "></div>
                    </div>

                    {{-- Count --}}
                    <div style="width:60px;flex-shrink:0;text-align:right;font-weight:700;font-size:14px;color:#111827">
                        {{ number_format($step['count']) }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ── Section 2: Monthly Trend + Lead Sources ─────────────────────────── --}}
    <div class="row g-4 mb-4">

        {{-- Monthly Submissions Trend --}}
        <div class="col-md-7">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-bar-chart me-2 text-primary"></i>Monthly Applications Submitted
                        <small class="text-muted fw-normal">(Last 6 Months)</small>
                    </h6>
                </div>
                <div class="card-body">
                    <div style="display:flex;align-items:flex-end;height:200px;gap:8px;padding:0 8px">
                        @foreach($monthlyTrend as $m)
                        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">
                            <span style="font-size:11px;font-weight:600;color:#374151">{{ $m['count'] }}</span>
                            <div style="width:100%;background:#4f46e5;border-radius:4px 4px 0 0;height:{{ $trendMax > 0 ? max(4, ($m['count']/$trendMax)*180) : 4 }}px"></div>
                            <span style="font-size:10px;color:#6b7280;white-space:nowrap">{{ $m['label'] }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Lead Source Conversion --}}
        <div class="col-md-5">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-funnel me-2 text-success"></i>Lead Source Conversion
                    </h6>
                </div>
                <div class="card-body">
                    @forelse($sourceStats as $src)
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span style="font-size:13px;font-weight:500;color:#374151">{{ $src['source'] ?: 'Unknown' }}</span>
                                <span class="badge bg-secondary">{{ number_format($src['total']) }}</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height:10px;border-radius:5px">
                                    <div class="progress-bar bg-success"
                                         role="progressbar"
                                         style="width:{{ $src['conversion_pct'] }}%"
                                         aria-valuenow="{{ $src['conversion_pct'] }}"
                                         aria-valuemin="0"
                                         aria-valuemax="100">
                                    </div>
                                </div>
                                <span style="font-size:12px;color:#6b7280;width:42px;text-align:right">
                                    {{ $src['conversion_pct'] }}%
                                </span>
                            </div>
                            <div style="font-size:11px;color:#9ca3af">
                                {{ number_format($src['converted']) }} converted
                            </div>
                        </div>
                    @empty
                        <p class="text-muted text-center py-4 mb-0">No lead source data yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

    </div>

    {{-- ── Section 3: Program-wise Breakdown ───────────────────────────────── --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-semibold">
                <i class="bi bi-table me-2 text-primary"></i>Program-wise Applicant Breakdown
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Program</th>
                            <th>Code</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Submitted</th>
                            <th class="text-center">Shortlisted</th>
                            <th class="text-center">Selected</th>
                            <th class="text-center">Rejected</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($programStats as $ps)
                            <tr>
                                <td class="ps-3" style="font-weight:500">{{ $ps['name'] }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $ps['code'] }}</span></td>
                                <td class="text-center fw-semibold">{{ number_format($ps['total']) }}</td>
                                <td class="text-center text-secondary">{{ number_format($ps['submitted']) }}</td>
                                <td class="text-center text-warning fw-semibold">{{ number_format($ps['shortlisted']) }}</td>
                                <td class="text-center fw-bold" style="color:#16a34a">{{ number_format($ps['selected']) }}</td>
                                <td class="text-center fw-semibold text-danger">{{ number_format($ps['rejected']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No active programs found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($programStats->count() > 0)
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td class="ps-3">Total</td>
                            <td></td>
                            <td class="text-center">{{ number_format($programStats->sum('total')) }}</td>
                            <td class="text-center">{{ number_format($programStats->sum('submitted')) }}</td>
                            <td class="text-center">{{ number_format($programStats->sum('shortlisted')) }}</td>
                            <td class="text-center" style="color:#16a34a">{{ number_format($programStats->sum('selected')) }}</td>
                            <td class="text-center text-danger">{{ number_format($programStats->sum('rejected')) }}</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    {{-- ── Section 4: Category Breakdown ───────────────────────────────────── --}}
    <div class="row g-4 mb-4">
        <div class="col-md-5">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-pie-chart me-2 text-warning"></i>Applicant Category Distribution
                    </h6>
                </div>
                <div class="card-body">
                    @php
                        $catTotal = $categoryStats->sum('total') ?: 1;
                    @endphp
                    @forelse($categoryStats as $cat)
                        @php
                            $catPct = round(($cat->total / $catTotal) * 100, 1);
                        @endphp
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span style="font-size:13px;font-weight:500;color:#374151">
                                    {{ ucwords(strtolower(str_replace('_', ' ', $cat->category))) }}
                                </span>
                                <span style="font-size:13px;font-weight:700;color:#111827">
                                    {{ number_format($cat->total) }}
                                </span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <div style="flex:1;background:#f3f4f6;border-radius:4px;height:12px;overflow:hidden">
                                    <div style="width:{{ $catPct }}%;height:100%;background:#f59e0b;border-radius:4px;min-width:4px"></div>
                                </div>
                                <span style="font-size:12px;color:#6b7280;width:40px;text-align:right">
                                    {{ $catPct }}%
                                </span>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted text-center py-5 mb-0">No category data yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Year-over-Year Comparison --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-semibold"><i class="bi bi-bar-chart-steps me-2"></i>Year-over-Year Comparison</h5>
            <a href="{{ route('admission.reports.export-pdf') }}" class="btn btn-sm btn-outline-danger" target="_blank">
                <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF Report
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Year</th>
                            <th class="text-center">Applications</th>
                            <th class="text-center">Enrolled</th>
                            <th class="text-center">Conversion Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($yoyData as $yoy)
                        <tr>
                            <td class="fw-semibold">{{ $yoy['year'] }}</td>
                            <td class="text-center">{{ number_format($yoy['applicants']) }}</td>
                            <td class="text-center text-success fw-semibold">{{ number_format($yoy['enrolled']) }}</td>
                            <td class="text-center">
                                @php $pct = $yoy['applicants'] > 0 ? round($yoy['enrolled'] / $yoy['applicants'] * 100, 1) : 0; @endphp
                                <span class="badge bg-{{ $pct >= 50 ? 'success' : ($pct >= 25 ? 'warning' : 'danger') }}">{{ $pct }}%</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- AICTE Category Compliance --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom py-3">
            <h5 class="mb-0 fw-semibold"><i class="bi bi-shield-check me-2"></i>AICTE Category Compliance</h5>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">Seat fill vs AICTE mandate for reservation categories. Total intake: {{ $totalIntake ?? '—' }} seats.</p>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Category</th>
                            <th class="text-center">Mandate %</th>
                            <th class="text-center">Mandate Seats</th>
                            <th class="text-center">Filled</th>
                            <th>Fill Progress</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($categoryCompliance as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row['category'] }}</td>
                            <td class="text-center">{{ $row['mandate_pct'] }}%</td>
                            <td class="text-center">{{ $row['mandate_seats'] }}</td>
                            <td class="text-center fw-semibold">{{ $row['filled'] }}</td>
                            <td style="min-width:140px">
                                <div class="progress" style="height:10px">
                                    <div class="progress-bar bg-{{ $row['compliant'] ? 'success' : 'warning' }}"
                                         style="width:{{ min(100, $row['fill_pct']) }}%"></div>
                                </div>
                                <div class="small text-muted mt-1">{{ $row['fill_pct'] }}%</div>
                            </td>
                            <td class="text-center">
                                @if($row['compliant'])
                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Compliant</span>
                                @else
                                    <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-circle me-1"></i>Below Target</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Counsellor Performance --}}
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-semibold"><i class="bi bi-person-badge me-2"></i>Counsellor Performance</h5>
                </div>
                <div class="card-body p-0">
                    @if($counsellorStats->isEmpty())
                        <p class="text-muted p-3 mb-0">No leads assigned yet.</p>
                    @else
                    <table class="table table-sm mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Counsellor</th>
                                <th class="text-center">Leads</th>
                                <th class="text-center">Converted</th>
                                <th class="text-center">Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($counsellorStats as $c)
                            <tr>
                                <td class="fw-semibold">{{ $c['name'] }}</td>
                                <td class="text-center">{{ $c['total_leads'] }}</td>
                                <td class="text-center text-success fw-semibold">{{ $c['converted'] }}</td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $c['conversion_pct'] >= 50 ? 'success' : ($c['conversion_pct'] >= 25 ? 'warning' : 'secondary') }}">
                                        {{ $c['conversion_pct'] }}%
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @endif
                </div>
            </div>
        </div>

        {{-- Geographic Distribution --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-semibold"><i class="bi bi-geo-alt me-2"></i>Geographic Distribution</h5>
                </div>
                <div class="card-body p-0">
                    @if($geoStats->isEmpty())
                        <p class="text-muted p-3 mb-0">No geographic data available yet.</p>
                    @else
                    <table class="table table-sm mb-0">
                        <thead class="bg-light">
                            <tr><th>State / City</th><th class="text-center">Applicants</th></tr>
                        </thead>
                        <tbody>
                            @foreach($geoStats as $place => $count)
                            <tr>
                                <td>{{ $place }}</td>
                                <td class="text-center fw-semibold">{{ $count }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ── Footer: Last Updated ─────────────────────────────────────────────── --}}
    <div class="text-end text-muted" style="font-size:12px">
        <i class="bi bi-clock me-1"></i>Last updated: {{ now()->format('d M Y H:i') }}
    </div>

</div>
@endsection
