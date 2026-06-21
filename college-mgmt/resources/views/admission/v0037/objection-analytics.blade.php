@extends('layouts.admin')

@section('title', 'Objection Analytics')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="fw-bold mb-1">Objection Analytics</h3>
            <div class="text-muted small">Review fee, location, placement, eligibility, and parent decision objections before changing scripts or escalation rules.</div>
        </div>
        <form method="POST" action="{{ route('admission.objection-analytics.store') }}">
            @csrf
            <button class="btn btn-primary btn-sm">Log Demo Objection</button>
        </form>
    </div>

    <div class="alert alert-info py-2 small mb-3">
        <strong>How to use this:</strong> review open objections, identify the strongest objection category, then update playbooks, parent journeys, or manager coaching for the affected stage.
    </div>

    <div class="row g-2 mb-3">
        @foreach($dashboard['stats'] as $label => $value)
            <div class="col-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body py-2">
                        <div class="small text-muted">{{ ucwords(str_replace('_', ' ', $label)) }}</div>
                        <div class="fs-5 fw-bold">{{ $value }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
            <span class="fw-bold">Objection Events</span>
            <span class="small text-muted">{{ $dashboard['events']->total() }} records</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" aria-label="Objection events">
                <thead class="table-light">
                    <tr>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Stage</th>
                        <th>Status</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dashboard['events'] as $event)
                        <tr>
                            <td>{{ $event->type?->name ?? 'Objection type not set' }}</td>
                            <td>{{ $event->type?->category ?? 'Category not set' }}</td>
                            <td>{{ $event->stage ? ucwords(str_replace('_', ' ', $event->stage)) : 'Stage not captured' }}</td>
                            <td><span class="badge bg-{{ $event->status === 'resolved' ? 'success' : 'warning text-dark' }}">{{ ucwords(str_replace('_', ' ', $event->status)) }}</span></td>
                            <td>{{ $event->notes ? Str::limit($event->notes, 70) : 'No notes captured yet' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                <div class="fw-semibold text-dark">No structured objections are logged yet</div>
                                <div class="small">Objection trends appear after counsellors or telecallers log call outcomes with fee, location, placement, eligibility, or parent-decision reasons.</div>
                                <a href="{{ route('admission.calling-desk.index') }}" class="btn btn-sm btn-outline-primary mt-2">Open Calling Desk</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $dashboard['events']->links() }}</div>
    </div>
</div>
@endsection
