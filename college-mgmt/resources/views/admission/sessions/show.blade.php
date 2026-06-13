@extends('layouts.admin')

@section('title', 'Session: ' . $session->session_name)

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-end gap-2 mb-3">
    <a href="{{ route('admission.assessment-control-room.index') }}" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-command me-1"></i>Assessment Control Room
    </a>
    <a href="{{ route('admission.evaluator-scoring.index') }}" class="btn btn-sm btn-outline-success">
        <i class="bi bi-clipboard-check me-1"></i>Evaluator Scoring
    </a>
</div>

{{-- Header --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                @php
                    $typeColors = ['gd'=>'success','pi'=>'primary','case_analysis'=>'dark','wat'=>'warning','written_test'=>'info','aptitude'=>'secondary','presentation'=>'dark','portfolio_review'=>'info','screening_call'=>'secondary'];
                    $stepType = $session->step->type ?? 'gd';
                @endphp
                <span class="badge bg-{{ $typeColors[$stepType] ?? 'secondary' }} me-2">{{ $session->step->typeLabel ?? strtoupper($stepType) }}</span>
                <span class="{{ $session->statusBadge }}">{{ $session->statusLabel }}</span>
                <h3 class="fw-bold mt-2 mb-1">{{ $session->session_name }}</h3>
                <div class="text-muted">
                    <i class="bi bi-calendar3 me-1"></i>{{ $session->scheduled_date->format('d M Y') }}
                    &nbsp;<i class="bi bi-clock me-1"></i>{{ \Carbon\Carbon::parse($session->start_time)->format('H:i') }} – {{ \Carbon\Carbon::parse($session->end_time)->format('H:i') }}
                    @if($session->venue)
                        &nbsp;<i class="bi bi-geo-alt me-1"></i>{{ $session->venue }}
                    @endif
                </div>
                <div class="text-muted small mt-1">
                    <i class="bi bi-mortarboard me-1"></i>{{ $session->program->name ?? 'N/A' }}
                    @if($session->batch)
                        &nbsp;|&nbsp;<i class="bi bi-collection me-1"></i>{{ $session->batch->name }}
                    @endif
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                @if($session->status === 'scheduled')
                    <a href="{{ route('admission.sessions.edit', $session) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-pencil me-1"></i> Edit
                    </a>
                @endif
                @if(in_array($session->status, ['scheduled','ongoing']))
                    <button type="button" class="btn btn-warning btn-sm" onclick="document.getElementById('attendanceForm').scrollIntoView({behavior:'smooth'})">
                        <i class="bi bi-clipboard-check me-1"></i> Mark Attendance
                    </button>
                @endif
                @if(in_array($session->status, ['ongoing','completed']))
                    <a href="{{ route('admission.sessions.scores', $session) }}" class="btn btn-info btn-sm text-white">
                        <i class="bi bi-clipboard2-data me-1"></i> Score Sheet
                    </a>
                @endif
                @if($session->status !== 'completed' && app(\App\Services\DepartmentHierarchyService::class)->canApproveAdmission(auth()->user()))
                    <form method="POST" action="{{ route('admission.sessions.complete', $session) }}" class="d-inline" onsubmit="return confirm('Mark session as completed?')">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-check-circle me-1"></i> Complete Session</button>
                    </form>
                @endif
                <form action="{{ route('admission.sessions.dispatch-call-letters', $session) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-info"
                        onclick="return confirm('Send call letter emails to all {{ $session->sessionApplicants->count() }} assigned candidates?')"
                        {{ $session->sessionApplicants->count() === 0 ? 'disabled' : '' }}>
                        <i class="bi bi-envelope me-1"></i> Send Call Letters ({{ $session->sessionApplicants->count() }})
                    </button>
                </form>
                <a href="{{ route('admission.sessions.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
    </div>
</div>

{{-- Stats Row --}}
<div class="row g-3 mb-4">
    @foreach([
        ['label'=>'Total Assigned','value'=>$stats['total'],'color'=>'primary','icon'=>'people'],
        ['label'=>'Present','value'=>$stats['present'],'color'=>'success','icon'=>'check-circle'],
        ['label'=>'Absent','value'=>$stats['absent'],'color'=>'danger','icon'=>'x-circle'],
        ['label'=>'Excused','value'=>$stats['excused'],'color'=>'warning','icon'=>'dash-circle'],
        ['label'=>'Pending','value'=>$stats['pending'],'color'=>'secondary','icon'=>'hourglass-split'],
    ] as $stat)
    <div class="col-6 col-md">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-{{ $stat['color'] }}">{{ $stat['value'] }}</div>
                <div class="small text-muted"><i class="bi bi-{{ $stat['icon'] }} me-1"></i>{{ $stat['label'] }}</div>
            </div>
        </div>
    </div>
    @endforeach
</div>

@if(!empty($panelSummary) && $panelSummary['panel_count'] > 0)
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent border-0 fw-bold">
        <i class="bi bi-people-fill me-2 text-primary"></i>Assessment Panels
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Panel</th>
                    <th>Type</th>
                    <th>Evaluators</th>
                    <th>Capacity</th>
                    <th>Candidates</th>
                    <th>Pending Scores</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($panelSummary['panels'] as $panel)
                    <tr>
                        <td class="fw-semibold">{{ $panel->name }}</td>
                        <td>{{ ucwords(str_replace('_', ' ', $panel->panel_type)) }}</td>
                        <td class="small">{{ $panel->members->pluck('user.name')->filter()->join(', ') }}</td>
                        <td>{{ $panel->capacity }}</td>
                        <td>{{ $panel->assignments->count() }}</td>
                        <td>{{ $panel->assignments->whereIn('score_status', ['pending','draft'])->count() }}</td>
                        <td><span class="badge bg-secondary">{{ ucfirst($panel->status) }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-transparent d-flex justify-content-between align-items-center">
        <span class="small text-muted">{{ $panelSummary['finalized_scores'] }} finalized, {{ $panelSummary['pending_scores'] }} pending score entries.</span>
        <a href="{{ route('admission.assessment-operations.index') }}" class="btn btn-sm btn-outline-primary">Open Assessment Operations</a>
    </div>
</div>
@endif

<div class="row g-4">
    {{-- Main: Attendance Table --}}
    <div class="col-lg-8">
        @if($session->sessionApplicants->isNotEmpty())
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0 fw-bold">
                <i class="bi bi-clipboard-check me-2 text-warning"></i>Assigned Candidates
            </div>
            <form method="POST" action="{{ route('admission.sessions.attendance', $session) }}" id="attendanceForm">
                @csrf
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>App #</th>
                                <th>Attendance</th>
                                <th>Panel #</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($session->sessionApplicants as $i => $sa)
                            <tr>
                                <td class="text-muted small">{{ $i + 1 }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $sa->applicant->user->name ?? 'N/A' }}</div>
                                </td>
                                <td><code class="small">{{ $sa->applicant->application_number ?? '—' }}</code></td>
                                <td>
                                    <div class="d-flex gap-1 flex-wrap">
                                        @foreach(['present'=>'success','absent'=>'danger','excused'=>'warning','pending'=>'secondary'] as $val => $color)
                                        <div class="form-check form-check-inline mb-0">
                                            <input class="form-check-input" type="radio"
                                                   name="attendance[{{ $sa->applicant_id }}]"
                                                   id="att_{{ $sa->applicant_id }}_{{ $val }}"
                                                   value="{{ $val }}"
                                                   @checked($sa->attendance_status === $val)>
                                            <label class="form-check-label small text-{{ $color }}" for="att_{{ $sa->applicant_id }}_{{ $val }}">
                                                {{ ucfirst($val) }}
                                            </label>
                                        </div>
                                        @endforeach
                                    </div>
                                </td>
                                <td style="width:80px">
                                    <input type="number" name="panel_number[{{ $sa->applicant_id }}]"
                                           class="form-control form-control-sm" min="1" placeholder="—"
                                           value="{{ $sa->panel_number }}">
                                </td>
                                <td>
                                    @if($sa->attendance_status === 'pending')
                                    <form method="POST" action="{{ route('admission.sessions.remove-applicant', [$session, $sa->applicant_id]) }}" class="d-inline" onsubmit="return confirm('Remove this candidate?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1" title="Remove">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if(in_array($session->status, ['scheduled','ongoing']))
                <div class="card-footer bg-transparent">
                    <button type="submit" class="btn btn-warning btn-sm">
                        <i class="bi bi-save me-1"></i> Save Attendance
                    </button>
                </div>
                @endif
            </form>
        </div>
        @else
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-people fs-2 d-block mb-2"></i>No candidates assigned yet.
            </div>
        </div>
        @endif

        {{-- Add Candidates --}}
        @if($availableApplicants->isNotEmpty() && $session->status !== 'completed')
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 fw-bold">
                <i class="bi bi-person-plus me-2 text-success"></i>Add Candidates
                <small class="text-muted fw-normal">(shortlisted, not yet assigned)</small>
            </div>
            <form method="POST" action="{{ route('admission.sessions.assign', $session) }}">
                @csrf
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px"><input type="checkbox" id="selectAll"></th>
                                <th>Name</th>
                                <th>App #</th>
                                <th>Program</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($availableApplicants as $applicant)
                            <tr>
                                <td><input type="checkbox" name="applicant_ids[]" value="{{ $applicant->id }}" class="applicant-check"></td>
                                <td>{{ $applicant->user->name ?? 'N/A' }}</td>
                                <td><code class="small">{{ $applicant->application_number }}</code></td>
                                <td class="small text-muted">{{ $applicant->program->abbreviation ?? '' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-transparent">
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="bi bi-person-plus me-1"></i> Add Selected
                    </button>
                </div>
            </form>
        </div>
        @endif
    </div>

    {{-- Sidebar --}}
    <div class="col-lg-4">
        @if($session->instructions)
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0 fw-bold">
                <i class="bi bi-info-circle me-2 text-info"></i>Session Instructions
            </div>
            <div class="card-body">
                <p class="mb-0" style="white-space:pre-line">{{ $session->instructions }}</p>
            </div>
        </div>
        @endif

        @if($session->step?->instructions)
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0 fw-bold">
                <i class="bi bi-list-check me-2 text-primary"></i>Step Guidelines
            </div>
            <div class="card-body">
                <p class="mb-0 small" style="white-space:pre-line">{{ $session->step->instructions }}</p>
            </div>
        </div>
        @endif

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 fw-bold">
                <i class="bi bi-info me-2"></i>Session Details
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted">Max Candidates</dt>
                    <dd class="col-7">{{ $session->max_candidates ?? 'Unlimited' }}</dd>
                    <dt class="col-5 text-muted">Conducted By</dt>
                    <dd class="col-7">{{ $session->conductedBy->name ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Created By</dt>
                    <dd class="col-7">{{ $session->createdBy->name ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Created At</dt>
                    <dd class="col-7">{{ $session->created_at->format('d M Y') }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('selectAll')?.addEventListener('change', function() {
    document.querySelectorAll('.applicant-check').forEach(cb => cb.checked = this.checked);
});
</script>
@endpush
@endsection
