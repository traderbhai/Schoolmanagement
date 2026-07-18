@extends('layouts.admin')

@section('title', 'Assessment Schedule Conflicts')

@section('content')
<div class="v037">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h3 class="fw-bold mb-1">Assessment Schedule Conflicts</h3>
        <div class="text-muted small">Evaluator availability, double-booking, capacity, rubric, and location checks.</div>
    </div>
    <a href="{{ route('admission.assessment-control-room.index') }}" class="btn btn-outline-primary btn-sm">Control Room</a>
</div>

<div class="row g-2 mb-3">
@foreach($dashboard['stats'] as $label => $value)
    <div class="col-6 col-lg-3"><a class="text-decoration-none text-reset" href="{{ route('admission.assessment-schedule-conflicts.index') }}"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="small text-muted">{{ ucfirst(str_replace('_', ' ', $label)) }}</div><div class="fs-4 fw-bold">{{ $value }}</div></div></div></a></div>
@endforeach
</div>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-bold">Open Conflict Queue</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th scope="col">Panel</th><th scope="col">Issue</th><th scope="col">Owner</th><th scope="col">Severity</th><th scope="col">Detected</th></tr></thead>
                    <tbody>
                    @forelse($dashboard['openConflicts'] as $conflict)
                        <tr>
                            <td><strong>{{ $conflict->panel?->name }}</strong><div class="small text-muted">{{ optional($conflict->panel?->scheduled_at)->format('d M H:i') }}</div></td>
                            <td>{{ ucwords(str_replace('_', ' ', $conflict->conflict_type)) }}<div class="small text-muted">{{ $conflict->message }}</div></td>
                            <td>{{ $conflict->user?->name ?? 'Panel setup' }}</td>
                            <td><span class="badge bg-{{ $conflict->severity === 'high' ? 'danger' : 'warning text-dark' }}">{{ ucfirst($conflict->severity) }}</span></td>
                            <td>{{ optional($conflict->detected_at)->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No open scheduling conflicts.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white">{{ $dashboard['openConflicts']->links() }}</div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent fw-bold">Panels Reviewed</div>
            <div class="list-group list-group-flush">
                @foreach($dashboard['panels'] as $panel)
                    <div class="list-group-item d-flex justify-content-between gap-2">
                        <span><strong>{{ $panel->name }}</strong><div class="small text-muted">{{ $panel->members->pluck('user.name')->filter()->join(', ') ?: 'No evaluators' }}</div></span>
                        <form method="POST" action="{{ route('admission.assessment-schedule-conflicts.refresh', $panel) }}">@csrf<button class="btn btn-sm btn-outline-primary">Refresh</button></form>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-bold">Evaluator Availability</div>
            <div class="list-group list-group-flush">
                @forelse($dashboard['availability'] as $availability)
                    <div class="list-group-item small"><strong>{{ $availability->user?->name }}</strong><div class="text-muted">{{ $availability->available_from->format('d M H:i') }} - {{ $availability->available_until->format('d M H:i') }} · {{ ucfirst($availability->location_mode ?? 'any') }}</div></div>
                @empty
                    <div class="list-group-item text-muted">No evaluator availability seeded.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
</div>
@endsection
