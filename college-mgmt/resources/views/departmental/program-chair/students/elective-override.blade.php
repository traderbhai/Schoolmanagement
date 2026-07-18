@extends('layouts.admin')
@section('title', 'Elective Override')
@section('page-title', 'Elective Registration Override')

@section('content')
<div class="container-fluid py-3">

    {{-- Filter --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('chair.students.elective-override') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold mb-1">Filter by Subject</label>
                    <select aria-label="Subject" name="subject_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">— All Elective Subjects —</option>
                        @foreach($electiveSubjects as $es)
                            <option value="{{ $es->subject_id }}" {{ request('subject_id') == $es->subject_id ? 'selected' : '' }}>
                                {{ $es->subject->name ?? '—' }} ({{ $es->subject->code ?? '' }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <span class="text-muted small">Term: <strong>{{ $currentTerm->name ?? '—' }}</strong></span>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><i class="bi bi-shuffle me-2 text-primary"></i>Elective Enrollments</span>
            <span class="badge bg-secondary">{{ $enrollments->total() }} records</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Student</th>
                        <th scope="col">Batch</th>
                        <th scope="col">Current Elective</th>
                        <th scope="col">Change</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($enrollments as $i => $enrollment)
                        <tr>
                            <td class="text-muted">{{ $enrollments->firstItem() + $i }}</td>
                            <td>
                                <div class="fw-semibold">{{ $enrollment->student->user->name ?? '—' }}</div>
                                <div class="text-muted" style="font-size:.72rem">{{ $enrollment->student->enrollment_number ?? '' }}</div>
                            </td>
                            <td class="text-muted">{{ $enrollment->student->batch->name ?? '—' }}</td>
                            <td>
                                <span class="badge bg-primary">{{ $enrollment->subject->name ?? '—' }}</span>
                                <div class="text-muted" style="font-size:.72rem">{{ $enrollment->subject->code ?? '' }}</div>
                            </td>
                            <td>
                                <button class="btn btn-outline-warning btn-sm"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#override-panel-{{ $enrollment->id }}">
                                    <i class="bi bi-pencil-square me-1"></i>Change
                                </button>
                            </td>
                        </tr>
                        <tr class="collapse" id="override-panel-{{ $enrollment->id }}">
                            <td colspan="5" class="bg-light border-start border-4 border-warning px-4 py-3">
                                <form method="POST" action="{{ route('chair.students.elective-override.change') }}" class="row g-2 align-items-end">
                                    @csrf
                                    <input type="hidden" name="enrollment_id" value="{{ $enrollment->id }}">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold mb-1">New Elective Subject</label>
                                        <select aria-label="New Subject" name="new_subject_id" class="form-select form-select-sm" required>
                                            <option value="">— Select Subject —</option>
                                            @foreach($electiveSubjects as $es)
                                                @if($es->subject_id !== $enrollment->subject_id)
                                                    <option value="{{ $es->subject_id }}">
                                                        {{ $es->subject->name ?? '—' }} ({{ $es->subject->code ?? '' }})
                                                    </option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold mb-1">Reason <span class="text-danger">*</span></label>
                                        <input aria-label="Elective override reason" type="text" name="reason" class="form-control form-control-sm"
                                            placeholder="e.g. Schedule conflict, medical reason…" required>
                                    </div>
                                    <div class="col-auto">
                                        <button type="submit" class="btn btn-warning btn-sm">
                                            <i class="bi bi-check2 me-1"></i>Change Elective
                                        </button>
                                        <button type="button" class="btn btn-secondary btn-sm"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#override-panel-{{ $enrollment->id }}">
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                No elective enrollments found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($enrollments->hasPages())
        <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
            <div class="text-muted small">
                Showing {{ $enrollments->firstItem() }}–{{ $enrollments->lastItem() }} of {{ $enrollments->total() }}
            </div>
            {{ $enrollments->withQueryString()->links('pagination::bootstrap-5') }}
        </div>
        @endif
    </div>

</div>
@endsection
