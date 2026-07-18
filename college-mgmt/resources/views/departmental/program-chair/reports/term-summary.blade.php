@extends('layouts.admin')
@section('title', 'Term-End Summary Report')
@section('page-title', 'Term-End Summary Report')

@section('content')
<div class="container-fluid py-3">

    {{-- Term filter + PDF download --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap gap-2 align-items-end">
                <form method="GET" action="{{ route('chair.reports.term-summary') }}" class="row g-2 align-items-end flex-grow-1">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold mb-1">Term</label>
                        <select aria-label="Term" name="term_id" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">— Select Term —</option>
                            @foreach($terms as $term)
                                <option value="{{ $term->id }}" {{ $selectedTerm?->id == $term->id ? 'selected' : '' }}>
                                    {{ $term->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </form>
                <a href="{{ route('chair.reports.term-summary', ['term_id' => $selectedTerm?->id, 'pdf' => 1]) }}"
                    class="btn btn-outline-danger btn-sm {{ !$selectedTerm ? 'disabled' : '' }}">
                    <i class="bi bi-file-earmark-pdf me-1"></i>Download PDF
                </a>
            </div>
        </div>
    </div>

    @forelse($stats as $programStat)
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header fw-semibold fs-6 bg-primary text-white">
                <i class="bi bi-mortarboard me-2"></i>{{ $programStat->program->name ?? '—' }}
            </div>
            <div class="card-body">

                {{-- KPI tiles --}}
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="border rounded text-center py-3">
                            <div class="fw-bold fs-4 text-primary">{{ $programStat->enrollment ?? 0 }}</div>
                            <div class="text-muted small">Enrollment</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="border rounded text-center py-3">
                            <div class="fw-bold fs-4 text-info">
                                {{ isset($programStat->avg_marks) ? number_format($programStat->avg_marks, 1) : '—' }}
                            </div>
                            <div class="text-muted small">Avg Marks</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="border rounded text-center py-3">
                            <div class="fw-bold fs-4 text-success">
                                {{ isset($programStat->pass_rate) ? number_format($programStat->pass_rate, 1) . '%' : '—' }}
                            </div>
                            <div class="text-muted small">Pass Rate</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="border rounded text-center py-3">
                            <div class="fw-bold fs-4 {{ isset($programStat->att_pct) && $programStat->att_pct < 75 ? 'text-danger' : 'text-success' }}">
                                {{ isset($programStat->att_pct) ? number_format($programStat->att_pct, 1) . '%' : '—' }}
                            </div>
                            <div class="text-muted small">Attendance %</div>
                        </div>
                    </div>
                </div>

                {{-- Top students --}}
                <div class="fw-semibold small mb-2"><i class="bi bi-trophy me-2 text-warning"></i>Top 5 Students</div>
                @if($programStat->top_students && $programStat->top_students->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Name</th>
                                <th scope="col">Enrollment No.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($programStat->top_students->take(5) as $rank => $topStudent)
                            <tr>
                                <td class="fw-semibold text-warning">{{ $rank + 1 }}</td>
                                <td class="fw-semibold">{{ $topStudent->user->name ?? $topStudent->name ?? '—' }}</td>
                                <td class="text-muted">{{ $topStudent->enrollment_number ?? '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                    <p class="text-muted small mb-0">No top student data available.</p>
                @endif

            </div>
        </div>
    @empty
        <div class="text-center text-muted py-5">
            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
            No program data found. Select a term to generate the summary.
        </div>
    @endforelse

    @if($stats->isNotEmpty())
    <div class="text-center mt-2">
        <a href="{{ route('chair.reports.term-summary', ['term_id' => $selectedTerm?->id, 'pdf' => 1]) }}"
            class="btn btn-primary {{ !$selectedTerm ? 'disabled' : '' }}">
            <i class="bi bi-file-earmark-pdf me-2"></i>Download PDF
        </a>
    </div>
    @endif

</div>
@endsection
