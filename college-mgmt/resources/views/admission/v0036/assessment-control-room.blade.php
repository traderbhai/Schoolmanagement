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
<x-ui.page-header
    title="Assessment Control Room"
    subtitle="Daily operating board for sessions, panel readiness, candidate lifecycle, pending scores, and score variance review."
    action-label="Evaluator Workspace"
    :action-route="route('admission.evaluator-scoring.index')"
    action-icon="bi-clipboard-check"
/>

<div class="alert alert-info border-0 shadow-sm d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3 py-3 mb-3">
    <div class="d-flex gap-3">
        <div class="ui-kpi-tile-icon bg-white text-info"><i class="bi bi-display"></i></div>
        <div>
            <div class="fw-bold">Assessment-day control sequence</div>
            <div class="small">1. Confirm panel readiness &nbsp; 2. Move candidates through lifecycle &nbsp; 3. Chase pending scores &nbsp; 4. Review variance before committee decisions.</div>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admission.assessment-schedule-conflicts.index') }}" class="btn btn-outline-info btn-sm">Schedule Conflicts</a>
        <a href="{{ route('admission.assessment-bulk-assignment.index') }}" class="btn btn-outline-info btn-sm">Bulk Assign</a>
        <a href="{{ route('admission.assessment-normalization.index') }}" class="btn btn-outline-info btn-sm">Normalization</a>
        <a href="{{ route('admission.assessment-rubrics.index') }}" class="btn btn-outline-info btn-sm">Rubrics</a>
    </div>
</div>

<div class="row g-2 mb-3">
@foreach($dashboard['stats'] as $label => $value)
    @php
        $metricUrl = match ($label) {
            'pending_scores' => route('admission.evaluator-scoring.index'),
            'no_show', 'rescheduled' => route('admission.assessment-slots.index'),
            'sessions_today', 'upcoming_sessions' => route('admission.sessions.index'),
            default => route('admission.assessment-control-room.index'),
        };
    @endphp
    <div class="col-6 col-lg-2">
        <a class="metric" href="{{ $metricUrl }}">
            <div class="card border-0 shadow-sm"><div class="card-body">
                <div class="d-flex justify-content-between"><div class="small text-muted">{{ ucfirst(str_replace('_', ' ', $label)) }}</div><i class="bi bi-arrow-up-right small text-muted"></i></div>
                <div class="fs-4 fw-bold">{{ $value }}</div>
            </div></div>
        </a>
    </div>
@endforeach
</div>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center"><span class="fw-bold">Panel Readiness</span><span class="small text-muted">Evaluator, rubric, venue, capacity, and pending score status</span></div>
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
                                    {{ $row['has_evaluator'] ? 'Evaluator' : 'No evaluator' }} | {{ $row['has_rubric'] ? 'Rubric' : 'No rubric' }} | {{ $row['has_venue'] ? 'Venue' : 'No venue' }}
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
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center"><span class="fw-bold">Upcoming Sessions</span><span class="small text-muted">Open the source session before assessment day</span></div>
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
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center"><span class="fw-bold">Candidate Lifecycle</span><span class="small text-muted">Track invited to completed/no-show/rescheduled</span></div>
            <div class="card-body">
                <div class="row g-2">
                    @foreach(['invited','confirmed','checked_in','waiting','in_progress','completed','no_show','rescheduled','cancelled'] as $state)
                        <div class="col-6"><div class="border rounded p-2 d-flex justify-content-between"><span class="small">{{ ucwords(str_replace('_', ' ', $state)) }}</span><strong>{{ $dashboard['lifecycleCounts']->get($state, 0) }}</strong></div></div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center"><span class="fw-bold">Pending Score Queue</span><span class="small text-muted">Ask evaluator or chair to finalize</span></div>
            <div class="list-group list-group-flush">
                @forelse($dashboard['pendingScores'] as $assignment)
                    <a href="{{ route('admission.evaluator-scoring.index') }}" class="list-group-item list-group-item-action">
                        <strong>{{ $assignment->applicant?->user?->name }}</strong>
                        <div class="small text-muted">{{ $assignment->panel?->name }} | {{ $assignment->evaluator?->name ?? 'Unassigned evaluator' }}</div>
                    </a>
                @empty
                    <div class="list-group-item text-muted">No pending scores.</div>
                @endforelse
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center"><span class="fw-bold">Variance / Override Queue</span><span class="small text-muted">Review before committee selection</span></div>
            <div class="list-group list-group-flush">
                @forelse($dashboard['varianceQueue'] as $assignment)
                    <div class="list-group-item">
                        <strong>{{ $assignment->applicant?->user?->name }}</strong>
                        <div class="small text-muted">Variance {{ $assignment->variance_score }} | Aggregate {{ $assignment->aggregate_score }}</div>
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
