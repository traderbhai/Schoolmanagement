@extends('layouts.admin')
@section('title', 'Dean — Academics')

@section('content')
<h4 class="mb-4"><i class="bi bi-bar-chart me-2 text-primary"></i>Academic Performance</h4>

<div class="row g-3">
    {{-- Program pass rates --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">Program Pass Rates</div>
            <div class="card-body">
                @foreach($programs as $p)
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span>{{ $p->name }}</span><span>{{ $p->pass_rate }}%</span>
                    </div>
                    <div class="progress" style="height:8px">
                        <div class="progress-bar bg-{{ $p->pass_rate >= 70 ? 'success' : ($p->pass_rate >= 50 ? 'warning' : 'danger') }}"
                             style="width:{{ $p->pass_rate }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Top performers --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">Top Performers</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>#</th><th>Student</th><th>Avg Marks</th></tr></thead>
                    <tbody>
                    @forelse($topPerformers as $i => $s)
                        <tr>
                            <td>{{ $i+1 }}</td>
                            <td>{{ $s->user->name ?? '—' }}<br><span class="text-muted small">{{ $s->program->name ?? '' }}</span></td>
                            <td><span class="badge bg-success">{{ number_format($s->avg_marks,1) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted">No data.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- At-risk --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold text-danger">At-Risk Students (&lt;40%)</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Student</th><th>Score%</th></tr></thead>
                    <tbody>
                    @forelse($atRisk as $s)
                        <tr>
                            <td>{{ $s->user->name ?? '—' }}<br><span class="text-muted small">{{ $s->program->name ?? '' }}</span></td>
                            <td><span class="badge bg-danger">{{ $s->score_pct }}%</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="text-center text-muted">No at-risk students.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
