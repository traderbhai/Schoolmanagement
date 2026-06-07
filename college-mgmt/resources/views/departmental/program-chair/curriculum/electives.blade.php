@extends('layouts.admin')

@section('title', 'Elective Management — Program Chair')
@section('page-title', 'Elective Management')

@section('content')
<div class="container-fluid px-4">

    {{-- Quick Nav --}}
    <div class="d-flex gap-2 mb-4 flex-wrap">
        <a href="{{ route('chair.curriculum.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Curriculum
        </a>
        <a href="{{ route('chair.curriculum.assignments') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-person-badge me-1"></i> Assignments
        </a>
        <span class="btn btn-primary btn-sm disabled">
            <i class="bi bi-list-stars me-1"></i> Electives
        </span>
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
            <form method="GET" action="{{ route('chair.curriculum.electives') }}" id="filterForm">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold mb-1">Program</label>
                        <select name="program_id" class="form-select"
                                onchange="document.getElementById('filterForm').submit()">
                            <option value="">— Select Program —</option>
                            @foreach($programs as $program)
                                <option value="{{ $program->id }}"
                                    {{ ($selectedProgram && $selectedProgram->id == $program->id) ? 'selected' : '' }}>
                                    {{ $program->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold mb-1">Term</label>
                        <select name="term_id" class="form-select"
                                onchange="document.getElementById('filterForm').submit()">
                            <option value="">— All Terms —</option>
                            @foreach($terms as $term)
                                <option value="{{ $term->id }}"
                                    {{ ($currentTerm && $currentTerm->id == $term->id) ? 'selected' : '' }}>
                                    {{ $term->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 d-flex gap-2 align-items-end">
                        <button type="button" class="btn btn-success"
                                data-bs-toggle="modal" data-bs-target="#createWindowModal">
                            <i class="bi bi-calendar-plus me-1"></i> Create Window
                        </button>
                        <a href="{{ route('chair.curriculum.electives') }}" class="btn btn-outline-secondary" title="Clear">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-4">
        {{-- LEFT: Elective Pools --}}
        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h6 class="mb-0 fw-bold">
                        <i class="bi bi-layers text-warning me-2"></i>Elective Pools
                    </h6>
                    @if($selectedProgram)
                        <small class="text-muted">{{ $selectedProgram->name }}</small>
                    @endif
                </div>
                <div class="card-body p-0">
                    @if($electiveSubjects->isEmpty())
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-list-x display-4 d-block mb-3 opacity-25"></i>
                            <p class="mb-0">No elective subjects found. Select a program and term.</p>
                        </div>
                    @else
                        @foreach($electiveSubjects->groupBy('elective_group') as $group => $subjects)
                            <div class="border-bottom">
                                <div class="px-3 py-2 bg-light d-flex justify-content-between align-items-center">
                                    <span class="fw-semibold text-secondary">
                                        <i class="bi bi-collection me-1"></i>
                                        @if($group)
                                            Elective Group {{ $group }}
                                        @else
                                            Ungrouped Electives
                                        @endif
                                    </span>
                                    @php $maxChoices = $subjects->first()->max_elective_choices ?? 1; @endphp
                                    <span class="badge bg-info text-dark">
                                        Choose {{ $maxChoices }} of {{ $subjects->count() }}
                                    </span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light small">
                                            <tr>
                                                <th class="ps-3">Subject</th>
                                                <th>Code</th>
                                                <th>Type</th>
                                                <th class="text-center">Credits</th>
                                                <th class="text-center">Demand</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($subjects as $ps)
                                                @php
                                                    $demandCount = $demand[$ps->subject_id] ?? 0;
                                                    $isHighDemand = $demandCount >= 30;
                                                @endphp
                                                <tr>
                                                    <td class="ps-3 fw-semibold">
                                                        {{ $ps->subject->name ?? '—' }}
                                                    </td>
                                                    <td>
                                                        <code class="text-secondary">{{ $ps->subject->code ?? '—' }}</code>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-{{ $ps->type === 'open_elective' ? 'success' : 'warning' }}">
                                                            {{ $ps->type === 'open_elective' ? 'Open' : 'Elective' }}
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        {{ $ps->credits ?? $ps->subject->credits ?? '—' }}
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-{{ $isHighDemand ? 'danger' : ($demandCount > 10 ? 'warning text-dark' : 'secondary') }}">
                                                            {{ $demandCount }} reg.
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

        {{-- RIGHT: Registration Windows --}}
        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h6 class="mb-0 fw-bold">
                        <i class="bi bi-calendar-range text-primary me-2"></i>Registration Windows
                    </h6>
                    <span class="badge bg-secondary">{{ $windows->count() }} window(s)</span>
                </div>
                <div class="card-body p-0">
                    @if($windows->isEmpty())
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-calendar-x fs-1 d-block mb-2 opacity-25"></i>
                            <p class="small mb-0">No registration windows created yet.</p>
                        </div>
                    @else
                        <div class="list-group list-group-flush">
                            @foreach($windows as $window)
                                @php
                                    $statusColors = [
                                        'draft'     => 'secondary',
                                        'open'      => 'success',
                                        'closed'    => 'danger',
                                        'finalized' => 'primary',
                                    ];
                                    $statusColor = $statusColors[$window->status] ?? 'secondary';
                                @endphp
                                <div class="list-group-item px-3 py-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <span class="fw-semibold">{{ $window->program?->name ?? 'All Programs' }}</span>
                                            <br>
                                            <small class="text-muted">
                                                <i class="bi bi-calendar me-1"></i>
                                                {{ \Carbon\Carbon::parse($window->opens_at)->format('d M Y, h:i A') }}
                                                &nbsp;→&nbsp;
                                                {{ \Carbon\Carbon::parse($window->closes_at)->format('d M Y, h:i A') }}
                                            </small>
                                        </div>
                                        <span class="badge bg-{{ $statusColor }} ms-2">
                                            {{ ucfirst($window->status) }}
                                        </span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <small class="text-muted">
                                            Max selections: <strong>{{ $window->max_selections ?? $window->max_per_student ?? '—' }}</strong>
                                        </small>
                                        <div class="ms-auto d-flex gap-1">
                                            @if($window->status !== 'open')
                                                <form method="POST"
                                                      action="{{ route('chair.curriculum.electives.window.status', $window->id) }}">
                                                    @csrf
                                                    <input type="hidden" name="status" value="open">
                                                    <button type="submit" class="btn btn-xs btn-success py-0 px-2">
                                                        <i class="bi bi-unlock me-1"></i>Open
                                                    </button>
                                                </form>
                                            @endif
                                            @if($window->status === 'open')
                                                <form method="POST"
                                                      action="{{ route('chair.curriculum.electives.window.status', $window->id) }}">
                                                    @csrf
                                                    <input type="hidden" name="status" value="closed">
                                                    <button type="submit" class="btn btn-xs btn-warning py-0 px-2">
                                                        <i class="bi bi-lock me-1"></i>Close
                                                    </button>
                                                </form>
                                            @endif
                                            @if(in_array($window->status, ['closed']))
                                                <form method="POST"
                                                      action="{{ route('chair.curriculum.electives.window.status', $window->id) }}">
                                                    @csrf
                                                    <input type="hidden" name="status" value="finalized">
                                                    <button type="submit" class="btn btn-xs btn-primary py-0 px-2">
                                                        <i class="bi bi-check2-circle me-1"></i>Finalize
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ===================== CREATE WINDOW MODAL ===================== --}}
<div class="modal fade" id="createWindowModal" tabindex="-1" aria-labelledby="createWindowModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow">
            <form method="POST" action="{{ route('chair.curriculum.electives.window') }}">
                @csrf
                @if($selectedProgram)
                    <input type="hidden" name="program_id" value="{{ $selectedProgram->id }}">
                @endif
                @if($currentTerm)
                    <input type="hidden" name="term_id" value="{{ $currentTerm->id }}">
                @endif

                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-semibold" id="createWindowModalLabel">
                        <i class="bi bi-calendar-plus me-2"></i>Create Elective Registration Window
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Opens At <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="opens_at" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Closes At <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="closes_at" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Max Selections Per Student <span class="text-danger">*</span></label>
                            <input type="number" name="max_selections" class="form-control" min="1" value="1" required>
                            <div class="form-text">How many electives can each student pick in this window.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Elective Group</label>
                            <input type="number" name="elective_group" class="form-control" min="1" placeholder="e.g. 1">
                            <div class="form-text">Leave blank to apply to all elective groups.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Instructions for Students</label>
                            <textarea name="instructions" class="form-control" rows="3"
                                      placeholder="e.g. Select exactly one elective from Group 1 based on your interest and career goals…"></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-calendar-check me-1"></i>Create Window
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('styles')
<style>
.btn-xs {
    font-size: 0.75rem;
    line-height: 1.5;
}
</style>
@endpush
@endsection
