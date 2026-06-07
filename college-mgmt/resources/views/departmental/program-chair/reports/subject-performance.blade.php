@extends('layouts.admin')
@section('title', 'Subject Performance Report')
@section('page-title', 'Subject Performance Report')

@section('content')
<div class="container-fluid py-3">

    {{-- Term filter --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('chair.reports.subject-performance') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Term</label>
                    <select name="term_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">— All Terms —</option>
                        @foreach($terms as $term)
                            <option value="{{ $term->id }}" {{ $selectedTerm?->id == $term->id ? 'selected' : '' }}>
                                {{ $term->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>
    </div>

    @forelse($subjects as $subject)
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <span class="fw-semibold fs-6">{{ $subject->name }}</span>
                    <span class="text-muted ms-2 small">({{ $subject->code ?? '—' }})</span>
                </div>
                <span class="badge bg-info text-dark">{{ $subject->program->name ?? '—' }}</span>
            </div>
            <div class="card-body">
                @if($subject->stats)
                    {{-- KPI chips --}}
                    <div class="row g-2 mb-3">
                        <div class="col-6 col-md-auto">
                            <div class="border rounded px-3 py-2 text-center" style="min-width:90px">
                                <div class="fw-bold fs-5 text-primary">{{ number_format($subject->stats->avg_pct ?? 0, 1) }}%</div>
                                <div class="text-muted" style="font-size:.72rem">Avg %</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-auto">
                            <div class="border rounded px-3 py-2 text-center" style="min-width:90px">
                                <div class="fw-bold fs-5 text-success">{{ number_format($subject->stats->pass_rate ?? 0, 1) }}%</div>
                                <div class="text-muted" style="font-size:.72rem">Pass Rate</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-auto">
                            <div class="border rounded px-3 py-2 text-center" style="min-width:90px">
                                <div class="fw-bold fs-5 text-dark">{{ $subject->stats->max ?? '—' }}</div>
                                <div class="text-muted" style="font-size:.72rem">Max</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-auto">
                            <div class="border rounded px-3 py-2 text-center" style="min-width:90px">
                                <div class="fw-bold fs-5 text-danger">{{ $subject->stats->min ?? '—' }}</div>
                                <div class="text-muted" style="font-size:.72rem">Min</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-auto">
                            <div class="border rounded px-3 py-2 text-center" style="min-width:90px">
                                <div class="fw-bold fs-5 text-secondary">{{ $subject->stats->count ?? 0 }}</div>
                                <div class="text-muted" style="font-size:.72rem">Students</div>
                            </div>
                        </div>
                    </div>

                    {{-- Grade distribution --}}
                    <div class="small">
                        <span class="fw-semibold text-muted me-2">Grade Distribution:</span>
                        <span class="badge bg-success me-1">O: {{ $subject->stats->grade_O ?? 0 }}</span>
                        <span class="badge bg-primary me-1">A+: {{ $subject->stats->grade_Aplus ?? 0 }}</span>
                        <span class="badge bg-info text-dark me-1">A: {{ $subject->stats->grade_A ?? 0 }}</span>
                        <span class="badge bg-warning text-dark me-1">B+: {{ $subject->stats->grade_Bplus ?? 0 }}</span>
                        <span class="badge bg-secondary me-1">B: {{ $subject->stats->grade_B ?? 0 }}</span>
                        <span class="badge bg-danger me-1">F: {{ $subject->stats->grade_F ?? 0 }}</span>
                    </div>
                @else
                    <p class="text-muted mb-0"><i class="bi bi-hourglass me-2"></i>No exam data available for this subject.</p>
                @endif
            </div>
        </div>
    @empty
        <div class="text-center text-muted py-5">
            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
            No subjects found for the selected term.
        </div>
    @endforelse

</div>
@endsection
