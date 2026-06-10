@extends('layouts.admin')

@section('title', 'Subject–Faculty Assignments — Program Chair')
@section('page-title', 'Subject–Faculty Assignments')

@section('content')
<div class="container-fluid px-4">

    {{-- Quick Nav --}}
    <div class="d-flex gap-2 mb-4 flex-wrap">
        <a href="{{ route('chair.curriculum.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Curriculum
        </a>
        <span class="btn btn-primary btn-sm disabled">
            <i class="bi bi-person-badge me-1"></i> Assignments
        </span>
        <a href="{{ route('chair.curriculum.electives') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-list-stars me-1"></i> Electives
        </a>
        <a href="{{ route('chair.curriculum.assessment') }}" class="btn btn-outline-info btn-sm">
            <i class="bi bi-clipboard2-data me-1"></i> Assessment
        </a>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Filter Bar --}}
    <div class="card shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('chair.curriculum.assignments') }}" id="filterForm">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold mb-1">Program</label>
                        <select name="program_id" class="form-select" onchange="document.getElementById('filterForm').submit()">
                            <option value="">— All Programs —</option>
                            @foreach($programs as $program)
                                <option value="{{ $program->id }}"
                                    {{ ($selectedProgram && $selectedProgram->id == $program->id) ? 'selected' : '' }}>
                                    {{ $program->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold mb-1">Term</label>
                        <select name="term_id" class="form-select" onchange="document.getElementById('filterForm').submit()">
                            <option value="">— All Terms —</option>
                            @foreach($terms as $term)
                                <option value="{{ $term->id }}"
                                    {{ ($currentTerm && $currentTerm->id == $term->id) ? 'selected' : '' }}>
                                    {{ $term->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold mb-1">Batch</label>
                        <select name="batch_id" class="form-select" onchange="document.getElementById('filterForm').submit()">
                            <option value="">— All Batches —</option>
                            @foreach($batches as $batch)
                                <option value="{{ $batch->id }}"
                                    {{ request('batch_id') == $batch->id ? 'selected' : '' }}>
                                    {{ $batch->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2 align-items-end">
                        <button type="button" class="btn btn-success"
                                data-bs-toggle="modal" data-bs-target="#assignFacultyModal">
                            <i class="bi bi-person-plus me-1"></i> Assign Faculty
                        </button>
                        <a href="{{ route('chair.curriculum.assignments') }}" class="btn btn-outline-secondary" title="Clear filters">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Workload Summary Bar --}}
    @if($assignments->isNotEmpty())
        @php
            $overloadedCount = collect($workload)->filter(fn($v) => $v > 20)->count();
        @endphp
        @if($overloadedCount > 0)
            <div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div>
                    <strong>Workload Warning:</strong>
                    {{ $overloadedCount }} teacher(s) have more than 20 sessions this term.
                    Consider redistributing assignments.
                </div>
            </div>
        @endif
    @endif

    {{-- Assignments Table --}}
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <h6 class="mb-0 fw-bold">
                <i class="bi bi-person-badge text-primary me-2"></i>Current Assignments
            </h6>
            <span class="badge bg-primary rounded-pill">{{ $assignments->count() }} assignment(s)</span>
        </div>
        <div class="card-body p-0">
            @if($assignments->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-person-x display-4 d-block mb-3 opacity-25"></i>
                    <p class="mb-0">No assignments found. Use <strong>Assign Faculty</strong> to create one.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="width:50px;">#</th>
                                <th>Subject</th>
                                <th>Faculty</th>
                                <th>Batch</th>
                                <th>Role</th>
                                <th>Workload</th>
                                <th class="text-end pe-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($assignments as $i => $assignment)
                                @php
                                    $teacherId    = $assignment->teacher_id;
                                    $sessionCount = $workload[$teacherId] ?? 0;
                                    $overloaded   = $sessionCount > 20;
                                @endphp
                                <tr>
                                    <td class="ps-3 text-muted">{{ $i + 1 }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $assignment->subject->name ?? '—' }}</div>
                                        <small class="text-muted">
                                            <code>{{ $assignment->subject->code ?? '' }}</code>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $assignment->teacher->user->name ?? '—' }}</div>
                                        <small class="text-muted">{{ $assignment->teacher->user->email ?? '' }}</small>
                                    </td>
                                    <td>
                                        {{ $assignment->batch?->name ?? '—' }}
                                    </td>
                                    <td>
                                        @if($assignment->is_primary ?? true)
                                            <span class="badge bg-primary">Primary</span>
                                        @else
                                            <span class="badge bg-secondary">Co-Teacher</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $overloaded ? 'danger' : 'success' }}">
                                            {{ $sessionCount }} sessions
                                            @if($overloaded)
                                                <i class="bi bi-exclamation-triangle ms-1"></i>
                                            @endif
                                        </span>
                                    </td>
                                    <td class="text-end pe-3">
                                        <form method="POST"
                                              action="{{ route('chair.curriculum.remove-assignment', $assignment->id) }}"
                                              class="d-inline"
                                              onsubmit="return confirm('Remove this faculty assignment?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- ===================== ASSIGN FACULTY MODAL ===================== --}}
<div class="modal fade" id="assignFacultyModal" tabindex="-1" aria-labelledby="assignFacultyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow">
            <form method="POST" action="{{ route('chair.curriculum.assign-faculty') }}">
                @csrf
                @if($selectedProgram)
                    <input type="hidden" name="program_id" value="{{ $selectedProgram->id }}">
                @endif
                @if($currentTerm)
                    <input type="hidden" name="term_id" value="{{ $currentTerm->id }}">
                @endif

                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-semibold" id="assignFacultyModalLabel">
                        <i class="bi bi-person-plus me-2"></i>Assign Faculty to Subject
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
                            <select name="subject_id" class="form-select" required>
                                <option value="">— Select Subject —</option>
                                @foreach($programSubjects as $ps)
                                    <option value="{{ $ps->subject_id ?? $ps->id }}">
                                        {{ $ps->subject->name ?? '?' }}
                                        ({{ $ps->subject->code ?? '' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Faculty <span class="text-danger">*</span></label>
                            <select name="teacher_id" class="form-select" required id="teacherSelect">
                                <option value="">— Select Teacher —</option>
                                @foreach($teachers as $teacher)
                                    @php
                                        $sessions  = $workload[$teacher->id] ?? 0;
                                        $overFlag  = $sessions > 20 ? ' ⚠ Overloaded' : '';
                                    @endphp
                                    <option value="{{ $teacher->id }}" data-sessions="{{ $sessions }}">
                                        {{ $teacher->user->name ?? '?' }}
                                        ({{ $sessions }} sessions{{ $overFlag }})
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">
                                <i class="bi bi-exclamation-triangle text-warning"></i>
                                Teachers marked ⚠ have &gt;20 sessions and may be overloaded.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Batch</label>
                            <select name="batch_id" class="form-select">
                                <option value="">— All Batches / Not Specific —</option>
                                @foreach($batches as $batch)
                                    <option value="{{ $batch->id }}"
                                        {{ request('batch_id') == $batch->id ? 'selected' : '' }}>
                                        {{ $batch->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6 d-flex align-items-end pb-1">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_primary"
                                       id="isPrimaryCheck" value="1" checked>
                                <label class="form-check-label fw-semibold" for="isPrimaryCheck">
                                    Primary Teacher
                                </label>
                                <div class="form-text">Uncheck to assign as Co-Teacher / Lab Instructor.</div>
                            </div>
                        </div>

                        {{-- Workload warning panel --}}
                        <div class="col-12" id="workloadWarningPanel" style="display:none;">
                            <div class="alert alert-danger py-2 mb-0">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <span id="workloadWarningText"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-person-check me-1"></i>Assign Faculty
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('teacherSelect')?.addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    const sessions = parseInt(opt.dataset.sessions || '0', 10);
    const panel = document.getElementById('workloadWarningPanel');
    const text  = document.getElementById('workloadWarningText');
    if (sessions > 20) {
        text.textContent = `This teacher already has ${sessions} sessions this term, which exceeds the recommended limit of 20. Assigning more sessions may affect teaching quality.`;
        panel.style.display = '';
    } else {
        panel.style.display = 'none';
    }
});
</script>
@endpush
@endsection
