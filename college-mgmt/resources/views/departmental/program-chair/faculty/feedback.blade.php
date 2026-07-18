@extends('layouts.admin')
@section('title', 'Faculty Feedback Summary')
@section('page-title', 'Faculty Feedback Summary')

@section('content')
<div class="container-fluid py-3">

    {{-- Confidentiality note --}}
    <div class="alert alert-info d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-shield-lock-fill me-2 fs-5"></i>
        <div>Ratings are anonymous and visible only to <strong>PMC</strong>, <strong>HOD</strong>, and <strong>Dean</strong>.</div>
    </div>

    {{-- Term filter --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('chair.faculty.feedback') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Term</label>
                    <select aria-label="Term" name="term_id" class="form-select form-select-sm" onchange="this.form.submit()">
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

    <div class="card border-0 shadow-sm">
        <div class="card-header fw-semibold">
            <i class="bi bi-star-half me-2 text-warning"></i>Student Feedback — Aggregated Ratings
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Subject</th>
                        <th scope="col">Responses</th>
                        <th scope="col">Teaching (/ 5)</th>
                        <th scope="col">Content (/ 5)</th>
                        <th scope="col">Overall (/ 5)</th>
                        <th scope="col">Trend</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($feedbackData as $row)
                        @php
                            $lowOverall = isset($row->avg_overall) && $row->avg_overall < 3.0;
                            $starsFilled = isset($row->avg_overall) ? round($row->avg_overall) : 0;
                        @endphp
                        <tr class="{{ $lowOverall ? 'table-warning' : '' }}">
                            <td>
                                <div class="fw-semibold">{{ $row->subject->name ?? '—' }}</div>
                                <div class="text-muted" style="font-size:.72rem">{{ $row->subject->code ?? '' }}</div>
                            </td>
                            <td>{{ $row->count }}</td>
                            <td>
                                @if(isset($row->avg_teaching))
                                    <span class="fw-semibold">{{ number_format($row->avg_teaching, 1) }}</span>
                                    <span class="text-muted">/ 5</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if(isset($row->avg_content))
                                    <span class="fw-semibold">{{ number_format($row->avg_content, 1) }}</span>
                                    <span class="text-muted">/ 5</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if(isset($row->avg_overall))
                                    <span class="fw-semibold {{ $lowOverall ? 'text-danger' : 'text-success' }}">
                                        {{ number_format($row->avg_overall, 1) }}
                                    </span>
                                    <span class="text-muted">/ 5</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if(isset($row->avg_overall))
                                    @php $filled = round($row->avg_overall); @endphp
                                    <span class="text-warning" title="{{ number_format($row->avg_overall, 1) }} / 5">
                                        @for($s = 1; $s <= 5; $s++)
                                            {{ $s <= $filled ? '★' : '☆' }}
                                        @endfor
                                    </span>
                                @else
                                    <span class="text-muted">No data</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                No feedback data available for the selected term.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
