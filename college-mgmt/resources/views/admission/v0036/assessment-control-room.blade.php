@extends('layouts.admin')

@section('title', 'Assessment Control Room')

@push('styles')
<style>
    .v036 .card { border-radius:6px; }
    .v036 .card-body { padding:.75rem; }
    .v036 .table > :not(caption) > * > * { padding:.45rem .55rem; }
    .v036 .metric { text-decoration:none; color:inherit; }
</style>
@endpush

@section('content')
<div class="v036">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h3 class="fw-bold mb-1">Assessment Control Room</h3>
        <div class="text-muted small">Panels, candidate lifecycle, evaluator readiness, pending scores, and variance review.</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admission.evaluator-scoring.index') }}" class="btn btn-primary btn-sm"><i class="bi bi-clipboard-check me-1"></i>Evaluator Workspace</a>
        <a href="{{ route('admission.assessment-schedule-conflicts.index') }}" class="btn btn-outline-danger btn-sm"><i class="bi bi-calendar-x me-1"></i>Schedule Conflicts</a>
        <a href="{{ route('admission.assessment-bulk-assignment.index') }}" class="btn btn-outline-success btn-sm"><i class="bi bi-people me-1"></i>Bulk Assign</a>
        <a href="{{ route('admission.assessment-normalization.index') }}" class="btn btn-outline-dark btn-sm"><i class="bi bi-sliders me-1"></i>Normalization</a>
        <a href="{{ route('admission.assessment-rubrics.index') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-list-stars me-1"></i>Rubrics</a>
    </div>
</div>

<div class="row g-2 mb-3">
@foreach($dashboard['stats'] as $label => $value)
    <div class="col-6 col-lg-2">
        <a class="metric" href="{{ $label === 'pending_scores' ? route('admission.evaluator-scoring.index') : route('admission.assessment-control-room.index') }}">
            <div class="card border-0 shadow-sm"><div class="card-body">
                <div class="small text-muted">{{ ucfirst(str_replace('_', ' ', $label)) }}</div>
                <div class="fs-4 fw-bold">{{ $value }}</div>
            </div></div>
        </a>
    </div>
@endforeach
</div>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent fw-bold">Panel Readiness</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Panel</th><th>Type</th><th>Capacity</th><th>Readiness</th><th>Pending</th><th></th></tr></thead>
                    <tbody>
                    @forelse($dashboard['readiness'] as $row)
                        <tr>
                            <td><strong>{{ $row['panel']->name }}</strong><div class="small text-muted">{{ optional($row['panel']->scheduled_at)->format('d M H:i') }}</div></td>
                            <td>{{ ucwords(str_replace('_', ' ', $row['panel']->panel_type)) }}</td>
                            <td>{{ $row['capacity_filled'] }}</td>
                            <td>
                                <span class="badge bg-{{ $row['ready'] ? 'success' : 'warning text-dark' }}">{{ $row['ready'] ? 'Ready' : 'Needs setup' }}</span>
                                <div class="small text-muted">
                                    {{ $row['has_evaluator'] ? 'Evaluator' : 'No evaluator' }} · {{ $row['has_rubric'] ? 'Rubric' : 'No rubric' }} · {{ $row['has_venue'] ? 'Venue' : 'No venue' }}
                                </div>
                            </td>
                            <td>{{ $row['scores_pending'] }}</td>
                            <td><a class="btn btn-sm btn-outline-primary" href="{{ route('admission.evaluator-scoring.index') }}">Score</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No assessment panels scheduled.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-bold">Upcoming Sessions</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Session</th><th>Program</th><th>Date</th><th>Candidates</th><th></th></tr></thead>
                    <tbody>
                    @foreach($dashboard['sessions'] as $session)
                        <tr>
                            <td><strong>{{ $session->session_name }}</strong><div class="small text-muted">{{ $session->step?->name }}</div></td>
                            <td>{{ $session->program?->name }}</td>
                            <td>{{ optional($session->scheduled_date)->format('d M Y') }} {{ $session->start_time }}</td>
                            <td>{{ $session->sessionApplicants->count() }}</td>
                            <td><a class="btn btn-sm btn-outline-primary" href="{{ route('admission.sessions.show', $session) }}">Open</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent fw-bold">Candidate Lifecycle</div>
            <div class="card-body">
                <div class="row g-2">
                    @foreach(['invited','confirmed','checked_in','waiting','in_progress','completed','no_show','rescheduled','cancelled'] as $state)
                        <div class="col-6"><div class="border rounded p-2 d-flex justify-content-between"><span class="small">{{ ucwords(str_replace('_', ' ', $state)) }}</span><strong>{{ $dashboard['lifecycleCounts']->get($state, 0) }}</strong></div></div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent fw-bold">Pending Score Queue</div>
            <div class="list-group list-group-flush">
                @forelse($dashboard['pendingScores'] as $assignment)
                    <a href="{{ route('admission.evaluator-scoring.index') }}" class="list-group-item list-group-item-action">
                        <strong>{{ $assignment->applicant?->user?->name }}</strong>
                        <div class="small text-muted">{{ $assignment->panel?->name }} · {{ $assignment->evaluator?->name ?? 'Unassigned evaluator' }}</div>
                    </a>
                @empty
                    <div class="list-group-item text-muted">No pending scores.</div>
                @endforelse
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-bold">Variance / Override Queue</div>
            <div class="list-group list-group-flush">
                @forelse($dashboard['varianceQueue'] as $assignment)
                    <div class="list-group-item">
                        <strong>{{ $assignment->applicant?->user?->name }}</strong>
                        <div class="small text-muted">Variance {{ $assignment->variance_score }} · Aggregate {{ $assignment->aggregate_score }}</div>
                    </div>
                @empty
                    <div class="list-group-item text-muted">No score variance flags.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
</div>
@endsection
