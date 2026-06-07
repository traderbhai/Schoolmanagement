@extends('layouts.admin')
@section('title', 'Attendance Defaulters')
@section('page-title', 'Attendance Defaulter Report')

@section('content')
<div class="container-fluid py-3">

    {{-- Filters --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('chair.reports.attendance-defaulters') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Program</label>
                    <select name="program_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">— All Programs —</option>
                        @foreach($programs as $program)
                            <option value="{{ $program->id }}" {{ request('program_id') == $program->id ? 'selected' : '' }}>
                                {{ $program->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Batch</label>
                    <select name="batch_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">— All Batches —</option>
                        @foreach($batches as $batch)
                            <option value="{{ $batch->id }}" {{ request('batch_id') == $batch->id ? 'selected' : '' }}>
                                {{ $batch->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Threshold %</label>
                    <input type="number" name="threshold" class="form-control form-control-sm"
                        value="{{ $threshold }}" min="1" max="100"
                        onchange="this.form.submit()">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Count badge + Export --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <span class="badge bg-danger fs-6 px-3 py-2">
            <i class="bi bi-exclamation-triangle me-2"></i>
            {{ count($defaulters) }} {{ Str::plural('defaulter', count($defaulters)) }} below {{ $threshold }}%
        </span>
        <a href="{{ route('chair.reports.attendance-defaulters', array_merge(request()->query(), ['export' => 1])) }}"
            class="btn btn-outline-success btn-sm">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Program</th>
                        <th>Batch</th>
                        <th>Overall Att %</th>
                        <th>Low-Att Subjects</th>
                        <th>Parent Phone</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($defaulters as $i => $student)
                        <tr class="{{ $student->overall_pct < 60 ? 'table-danger' : '' }}">
                            <td class="text-muted">{{ $i + 1 }}</td>
                            <td>
                                <div class="fw-semibold">{{ $student->user->name ?? '—' }}</div>
                                <div class="text-muted" style="font-size:.72rem">{{ $student->enrollment_number ?? '' }}</div>
                            </td>
                            <td class="text-muted">{{ $student->batch->program->name ?? '—' }}</td>
                            <td class="text-muted">{{ $student->batch->name ?? '—' }}</td>
                            <td>
                                <span class="badge {{ $student->overall_pct < 60 ? 'bg-danger' : 'bg-warning text-dark' }}">
                                    {{ number_format($student->overall_pct, 1) }}%
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-secondary">{{ $student->low_subjects }}</span>
                                {{ Str::plural('subject', $student->low_subjects) }}
                            </td>
                            <td>
                                @if($student->parent_phone)
                                    <a href="tel:{{ $student->parent_phone }}" class="text-decoration-none">
                                        <i class="bi bi-telephone me-1"></i>{{ $student->parent_phone }}
                                    </a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-check-circle fs-3 d-block mb-2 text-success"></i>
                                No defaulters found below {{ $threshold }}% for the selected filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if(count($defaulters) > 0 && collect($defaulters)->where('overall_pct', '<', 60)->count() > 0)
    <div class="mt-2">
        <span class="text-danger small"><i class="bi bi-circle-fill me-1" style="font-size:.5rem"></i>
            Rows highlighted in red have attendance below 60% — immediate intervention required.
        </span>
    </div>
    @endif

</div>
@endsection
