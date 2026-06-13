@extends('layouts.admin')

@section('title', 'Assessment Operations')

@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h3 class="fw-bold mb-1">Assessment Operations</h3><div class="text-muted small">Panels, evaluator scoring, attendance, pending scores, and overrides.</div></div>
    <div class="d-flex gap-2">
        <a href="{{ route('admission.assessment-control-room.index') }}" class="btn btn-primary btn-sm">Control Room</a>
        <a href="{{ route('admission.evaluator-scoring.index') }}" class="btn btn-outline-success btn-sm">Evaluator Scoring</a>
    </div>
</div>
<div class="row g-4">
    <div class="col-xl-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-bold">Pending Score Queue</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Applicant</th><th>Panel</th><th>Evaluator</th><th>Status</th><th>Recommendation</th></tr></thead>
                    <tbody>
                    @foreach($pendingScores as $assignment)
                        <tr>
                            <td class="fw-semibold">{{ $assignment->applicant->user->name ?? $assignment->applicant->application_number }}</td>
                            <td>{{ $assignment->panel->name }}</td>
                            <td>{{ $assignment->evaluator->name ?? 'Unassigned' }}</td>
                            <td><span class="badge bg-warning text-dark">{{ ucfirst($assignment->score_status) }}</span></td>
                            <td>{{ $assignment->recommendation ?? 'Pending' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-bold">Panel Load</div>
            <div class="list-group list-group-flush">
                @foreach($panels as $panel)
                    <div class="list-group-item d-flex justify-content-between">
                        <div><div class="fw-semibold">{{ $panel->name }}</div><div class="small text-muted">{{ $panel->members->count() }} evaluators</div></div>
                        <span class="badge bg-primary align-self-center">{{ $panel->assignments->count() }}/{{ $panel->capacity }}</span>
                    </div>
                @endforeach
            </div>
            <div class="card-footer bg-transparent py-2">{{ $panels->links() }}</div>
        </div>
    </div>
</div>
@endsection
