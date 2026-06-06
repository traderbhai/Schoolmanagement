@extends('layouts.admin')
@section('title', 'Institution Analytics')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Institution Analytics</h4>
            <span class="text-muted small">Cross-domain performance overview — {{ $currentYear }}</span>
        </div>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Dashboard
        </a>
    </div>

    {{-- Top KPIs --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body py-3">
                    <div class="text-muted small mb-1">Active Students</div>
                    <div class="fw-bold" style="font-size:2rem;color:#1e40af">{{ $totalStudents }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body py-3">
                    <div class="text-muted small mb-1">Active Faculty</div>
                    <div class="fw-bold" style="font-size:2rem;color:#7c3aed">{{ $totalTeachers }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body py-3">
                    <div class="text-muted small mb-1">Overall Pass Rate</div>
                    @if($overallPassRate !== null)
                    <div class="fw-bold" style="font-size:2rem;color:{{ $overallPassRate >= 70 ? '#16a34a' : ($overallPassRate >= 50 ? '#d97706' : '#dc2626') }}">
                        {{ $overallPassRate }}%
                    </div>
                    @else
                    <div class="fw-bold text-muted" style="font-size:2rem">—</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body py-3">
                    <div class="text-muted small mb-1">Overall Attendance</div>
                    @if($overallAttPct !== null)
                    <div class="fw-bold" style="font-size:2rem;color:{{ $overallAttPct >= 75 ? '#16a34a' : '#dc2626' }}">
                        {{ $overallAttPct }}%
                    </div>
                    @else
                    <div class="fw-bold text-muted" style="font-size:2rem">—</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        {{-- Grade Distribution --}}
        <div class="col-md-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h6 class="fw-semibold mb-0"><i class="bi bi-bar-chart me-2 text-primary"></i>Grade Distribution ({{ $currentYear }})</h6>
                </div>
                <div class="card-body">
                    @php
                        $total = array_sum($gradeDistribution);
                        $gradeColors = ['O'=>'#16a34a','A+'=>'#22c55e','A'=>'#2563eb','B+'=>'#3b82f6','B'=>'#d97706','C'=>'#f59e0b','D'=>'#94a3b8','F'=>'#dc2626','Absent'=>'#475569'];
                    @endphp
                    @foreach($gradeDistribution as $grade => $count)
                    @php $pct = $total > 0 ? round(($count / $total) * 100, 1) : 0; @endphp
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="fw-bold text-center" style="width:32px;font-size:.8rem;color:{{ $gradeColors[$grade] ?? '#475569' }}">
                            {{ $grade }}
                        </span>
                        <div class="flex-grow-1 progress" style="height:14px;border-radius:4px">
                            <div class="progress-bar" role="progressbar"
                                 style="width:{{ $pct }}%;background:{{ $gradeColors[$grade] ?? '#94a3b8' }};border-radius:4px">
                            </div>
                        </div>
                        <span class="text-muted" style="font-size:.75rem;min-width:50px;text-align:right">
                            {{ $count }} <span class="text-muted">({{ $pct }}%)</span>
                        </span>
                    </div>
                    @endforeach
                    @if($total === 0)
                    <p class="text-muted text-center mb-0">No exam results recorded for {{ $currentYear }}.</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Fee Collection Trend --}}
        <div class="col-md-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h6 class="fw-semibold mb-0"><i class="bi bi-cash-coin me-2 text-success"></i>Monthly Fee Collection</h6>
                </div>
                <div class="card-body">
                    @php $maxFee = max(array_column($monthlyFees, 'amount') ?: [1]); @endphp
                    @foreach($monthlyFees as $m)
                    @php $barPct = $maxFee > 0 ? round(($m['amount'] / $maxFee) * 100) : 0; @endphp
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="text-muted" style="font-size:.75rem;min-width:60px">{{ $m['label'] }}</span>
                        <div class="flex-grow-1 progress" style="height:14px;border-radius:4px">
                            <div class="progress-bar bg-success" role="progressbar"
                                 style="width:{{ $barPct }}%;border-radius:4px">
                            </div>
                        </div>
                        <span style="font-size:.75rem;min-width:70px;text-align:right;font-weight:600">
                            ₹{{ number_format($m['amount'] / 1000, 1) }}K
                        </span>
                    </div>
                    @endforeach
                    @php $totalFee = array_sum(array_column($monthlyFees, 'amount')); @endphp
                    <div class="text-end text-muted small mt-2">Total (6 months): <strong>₹{{ number_format($totalFee) }}</strong></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Program-wise Cross-domain Table --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent border-0 pt-3 pb-0">
            <h6 class="fw-semibold mb-0"><i class="bi bi-table me-2 text-info"></i>Program-wise Performance Matrix</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Program</th>
                            <th class="text-center">Students</th>
                            <th class="text-center">Pass Rate</th>
                            <th class="text-center">Attendance</th>
                            <th class="text-center">Placement ({{ $currentYear }})</th>
                            <th class="text-center">Overall</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($programs as $prog)
                        @php
                            $scores = array_filter([
                                $prog->pass_rate,
                                $prog->att_pct,
                                $prog->placement_rate,
                            ], fn($v) => $v !== null);
                            $overall = count($scores) > 0 ? round(array_sum($scores) / count($scores), 1) : null;
                            $overallColor = $overall >= 70 ? 'success' : ($overall >= 50 ? 'warning' : 'danger');
                        @endphp
                        <tr>
                            <td class="ps-3 fw-medium small">{{ $prog->name }}</td>
                            <td class="text-center fw-bold">{{ $prog->students_count }}</td>
                            <td class="text-center">
                                @if($prog->pass_rate !== null)
                                <span class="badge bg-{{ $prog->pass_rate >= 70 ? 'success' : ($prog->pass_rate >= 50 ? 'warning text-dark' : 'danger') }}-subtle
                                             text-{{ $prog->pass_rate >= 70 ? 'success' : ($prog->pass_rate >= 50 ? 'warning' : 'danger') }}">
                                    {{ $prog->pass_rate }}%
                                </span>
                                @else
                                <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($prog->att_pct !== null)
                                <span class="badge bg-{{ $prog->att_pct >= 75 ? 'success' : 'danger' }}-subtle
                                             text-{{ $prog->att_pct >= 75 ? 'success' : 'danger' }}">
                                    {{ $prog->att_pct }}%
                                </span>
                                @else
                                <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($prog->placement_rate !== null)
                                <span class="badge bg-primary-subtle text-primary">{{ $prog->placement_rate }}%</span>
                                @else
                                <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($overall !== null)
                                <span class="badge bg-{{ $overallColor }}-subtle text-{{ $overallColor }} fw-bold">{{ $overall }}%</span>
                                @else
                                <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">No programs found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- At-Risk Students --}}
    @if($atRisk->isNotEmpty())
    <div class="card border-warning shadow-sm mb-4">
        <div class="card-header bg-warning-subtle border-0 pt-3 pb-0 d-flex justify-content-between align-items-center">
            <h6 class="fw-semibold mb-0 text-warning-emphasis">
                <i class="bi bi-exclamation-triangle me-2"></i>At-Risk Students (Attendance &lt; 65%)
            </h6>
            <span class="badge bg-warning text-dark">{{ $atRisk->count() }} students</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Student</th>
                            <th>Program</th>
                            <th class="text-center">Attendance %</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($atRisk as $s)
                        <tr>
                            <td class="ps-3">
                                <div class="fw-medium small">{{ $s->user->name ?? '—' }}</div>
                                <div class="text-muted" style="font-size:.72rem">{{ $s->enrollment_number ?? '' }}</div>
                            </td>
                            <td class="small">{{ $s->program->name ?? '—' }}</td>
                            <td class="text-center">
                                <span class="badge bg-danger">{{ $s->att_pct }}%</span>
                            </td>
                            <td>
                                <a href="{{ route('admin.students.show', $s) }}"
                                   class="btn btn-sm btn-outline-secondary py-0 px-2">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- Enrollment Trend --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-0 pt-3 pb-0">
            <h6 class="fw-semibold mb-0"><i class="bi bi-graph-up me-2 text-primary"></i>New Enrollments (Last 6 Months)</h6>
        </div>
        <div class="card-body">
            @php $maxEnroll = max(array_column($enrollmentTrend, 'count') ?: [1]); @endphp
            <div class="d-flex align-items-end gap-3 justify-content-around" style="height:120px">
                @foreach($enrollmentTrend as $e)
                @php $barH = $maxEnroll > 0 ? max(4, round(($e['count'] / $maxEnroll) * 100)) : 4; @endphp
                <div class="d-flex flex-column align-items-center gap-1" style="flex:1">
                    <span class="small fw-bold text-primary">{{ $e['count'] }}</span>
                    <div style="width:100%;max-width:40px;height:{{ $barH }}px;background:#3b82f6;border-radius:4px 4px 0 0"></div>
                    <span class="text-muted" style="font-size:.65rem;text-align:center">{{ $e['label'] }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
