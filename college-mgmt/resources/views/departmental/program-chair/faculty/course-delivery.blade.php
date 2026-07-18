@extends('layouts.admin')
@section('title', 'Course Delivery Tracking')
@section('page-title', 'Course Delivery Tracking')

@section('content')
<div class="container-fluid py-3">

    {{-- Term filter --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('chair.faculty.course-delivery') }}" class="row g-2 align-items-end">
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
            <i class="bi bi-bar-chart-steps me-2 text-primary"></i>
            Session Coverage — sorted by lowest coverage first
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Subject</th>
                        <th scope="col">Teacher</th>
                        <th scope="col">Batch</th>
                        <th scope="col">Planned</th>
                        <th scope="col">Conducted</th>
                        <th scope="col" style="min-width:160px">Progress</th>
                        <th scope="col">%</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $sorted = collect($assignments)->sortBy('pct');
                    @endphp
                    @forelse($sorted as $row)
                        @php
                            $pct = $row->pct ?? 0;
                            $barColor = $pct >= 90 ? 'success' : ($pct >= 60 ? 'warning' : 'danger');
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $row->subject->name ?? '—' }}</div>
                                <div class="text-muted" style="font-size:.72rem">{{ $row->subject->code ?? '' }}</div>
                            </td>
                            <td>{{ $row->teacher?->user?->name ?? '—' }}</td>
                            <td class="text-muted">{{ $row->batch->name ?? '—' }}</td>
                            <td>{{ $row->planned }}</td>
                            <td>{{ $row->conducted }}</td>
                            <td>
                                <div class="progress" style="height:10px">
                                    <div class="progress-bar bg-{{ $barColor }}"
                                        role="progressbar"
                                        style="width:{{ min($pct, 100) }}%"
                                        aria-valuenow="{{ $pct }}"
                                        aria-valuemin="0"
                                        aria-valuemax="100">
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="fw-semibold text-{{ $barColor }}">{{ $pct }}%</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                No course delivery data found for the selected term.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
