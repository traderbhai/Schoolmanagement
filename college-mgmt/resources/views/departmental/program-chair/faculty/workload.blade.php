@extends('layouts.admin')
@section('title', 'Faculty Workload')
@section('page-title', 'Faculty Workload Monitor')

@section('content')
<div class="container-fluid py-3">

    {{-- Term filter --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('chair.faculty.workload') }}" class="row g-2 align-items-end">
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
                @if($selectedTerm)
                <div class="col-auto">
                    <span class="text-muted small">Showing: <strong>{{ $selectedTerm->name }}</strong></span>
                </div>
                @endif
            </form>
        </div>
    </div>

    {{-- Overload alert --}}
    @php $overloadedCount = collect($workload)->where('overload', true)->count(); @endphp
    @if($overloadedCount > 0)
    <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
        <div>
            <strong>{{ $overloadedCount }} {{ Str::plural('teacher', $overloadedCount) }} overloaded</strong>
            (&gt;18 hrs/week). Please review and redistribute workload.
        </div>
    </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-header fw-semibold">
            <i class="bi bi-person-workspace me-2 text-primary"></i>Teacher Workload Summary
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Teacher</th>
                        <th scope="col">Subjects</th>
                        <th scope="col">Batches</th>
                        <th scope="col">Sessions/Week</th>
                        <th scope="col">Hours/Week</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($workload as $rank => $row)
                        <tr class="{{ $row->overload ? 'table-danger' : '' }}">
                            <td class="text-muted fw-semibold">{{ $rank + 1 }}</td>
                            <td>
                                <div class="fw-semibold">{{ $row->teacher?->user?->name ?? '—' }}</div>
                                <div class="text-muted" style="font-size:.72rem">{{ $row->teacher?->designation ?? '' }}</div>
                            </td>
                            <td style="max-width:220px;white-space:normal">
                                {{ $row->subjects->pluck('name')->implode(', ') ?: '—' }}
                            </td>
                            <td style="max-width:160px;white-space:normal">
                                {{ $row->batches->pluck('name')->implode(', ') ?: '—' }}
                            </td>
                            <td class="fw-semibold">{{ $row->sessions }}</td>
                            <td class="fw-semibold">{{ number_format($row->hours, 1) }}</td>
                            <td>
                                @if($row->overload)
                                    <span class="badge bg-danger"><i class="bi bi-exclamation-triangle me-1"></i>Overloaded</span>
                                @else
                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Normal</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                No workload data found for the selected term.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
