@extends('layouts.admin')
@section('title', 'Marks Submission Tracker')
@section('page-title', 'Marks Submission Tracker')

@section('content')
<div class="container-fluid py-3">

    {{-- Term filter --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('chair.faculty.marks-tracker') }}" class="row g-2 align-items-end">
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

    {{-- Progress bar --}}
    @php
        $totalComponents = 0;
        $submittedComponents = 0;
        foreach($bySubject as $group) {
            foreach($group as $component) {
                $totalComponents++;
                if($component->submitted) $submittedComponents++;
            }
        }
        $progressPct = $totalComponents > 0 ? round(($submittedComponents / $totalComponents) * 100) : 0;
        $progressColor = $progressPct >= 90 ? 'success' : ($progressPct >= 60 ? 'warning' : 'danger');
    @endphp

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold small">Overall Marks Submission Progress</span>
                <span class="badge bg-{{ $progressColor }}">{{ $submittedComponents }} of {{ $totalComponents }} components submitted</span>
            </div>
            <div class="progress" style="height:12px">
                <div class="progress-bar bg-{{ $progressColor }}" role="progressbar"
                    style="width:{{ $progressPct }}%"
                    aria-valuenow="{{ $progressPct }}" aria-valuemin="0" aria-valuemax="100">
                    {{ $progressPct }}%
                </div>
            </div>
        </div>
    </div>

    {{-- Per-subject grouped table --}}
    @forelse($bySubject as $subjectId => $components)
        @php
            $subjectName = $components->first()->subject->name ?? 'Unknown Subject';
            $subjectCode = $components->first()->subject->code ?? '';
            $anyMissing = $components->contains(fn($c) => !$c->submitted);
        @endphp
        <div class="card border-0 shadow-sm mb-3 {{ $anyMissing ? 'border border-danger' : '' }}">
            <div class="card-header {{ $anyMissing ? 'bg-danger bg-opacity-10 text-danger' : 'bg-light' }} fw-semibold d-flex justify-content-between align-items-center">
                <span>
                    <i class="bi bi-book me-2"></i>{{ $subjectName }}
                    @if($subjectCode)<span class="text-muted fw-normal ms-2 small">({{ $subjectCode }})</span>@endif
                </span>
                @if($anyMissing)
                    <span class="badge bg-danger"><i class="bi bi-exclamation-triangle me-1"></i>Pending Submissions</span>
                @else
                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Complete</span>
                @endif
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Component</th>
                            <th>Max Marks</th>
                            <th>Submitted</th>
                            <th>Result Count</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($components as $component)
                            <tr class="{{ !$component->submitted ? 'table-danger' : '' }}">
                                <td class="fw-semibold">{{ $component->name }}</td>
                                <td>{{ $component->max_marks ?? '—' }}</td>
                                <td>
                                    @if($component->submitted)
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-lg me-1"></i>Yes
                                        </span>
                                    @else
                                        <span class="badge bg-danger">
                                            <i class="bi bi-x-lg me-1"></i>No
                                        </span>
                                    @endif
                                </td>
                                <td>{{ $component->result_count ?? 0 }}</td>
                                <td></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="text-center text-muted py-5">
            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
            No assessment components found for the selected term.
        </div>
    @endforelse

</div>
@endsection
