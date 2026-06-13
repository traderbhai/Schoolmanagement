@extends('layouts.admin')

@section('title', 'Assessment Panels')

@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h3 class="fw-bold mb-1">Assessment Panels</h3><div class="text-muted small">{{ $panels->total() }} panels after filters. Panels for PI, GD, case analysis, WAT, presentation, portfolio review, and screening calls.</div></div>
    <a href="{{ route('admission.assessment-operations.index') }}" class="btn btn-outline-primary btn-sm">Operations</a>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Panel</th><th>Type</th><th>Session</th><th>Evaluators</th><th>Capacity</th><th>Candidates</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach($panels as $panel)
                        <tr>
                            <td class="fw-semibold">{{ $panel->name }}</td>
                            <td>{{ str_replace('_', ' ', $panel->panel_type) }}</td>
                            <td>{{ $panel->session->session_name ?? 'Standalone' }}</td>
                            <td>{{ $panel->members->pluck('user.name')->filter()->join(', ') }}</td>
                            <td>{{ $panel->capacity }}</td>
                            <td>{{ $panel->assignments->count() }}</td>
                            <td><span class="badge bg-secondary">{{ ucfirst($panel->status) }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2 py-2">
                <div class="small text-muted">Showing {{ $panels->firstItem() ?? 0 }}-{{ $panels->lastItem() ?? 0 }} of {{ $panels->total() }}</div>
                {{ $panels->links() }}
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-bold">Create Panel</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admission.assessment-panels.store') }}" class="vstack gap-2">
                    @csrf
                    <input class="form-control form-control-sm" name="name" placeholder="Panel name" required>
                    <select class="form-select form-select-sm" name="panel_type">
                        @foreach(['case_analysis','personal_interview','group_discussion','written_ability_test','aptitude_test','presentation','portfolio_review','screening_call'] as $type)
                            <option value="{{ $type }}">{{ ucwords(str_replace('_', ' ', $type)) }}</option>
                        @endforeach
                    </select>
                    <select class="form-select form-select-sm" name="selection_session_id"><option value="">Selection session</option>@foreach($sessions as $session)<option value="{{ $session->id }}">{{ $session->session_name }}</option>@endforeach</select>
                    <input class="form-control form-control-sm" type="number" name="capacity" value="20" min="1">
                    <input class="form-control form-control-sm" name="venue" placeholder="Venue or room">
                    <input class="form-control form-control-sm" type="datetime-local" name="scheduled_at">
                    <select class="form-select form-select-sm" name="evaluator_ids[]" multiple size="5">@foreach($evaluators as $evaluator)<option value="{{ $evaluator->id }}">{{ $evaluator->name }}</option>@endforeach</select>
                    <button class="btn btn-primary btn-sm">Create Panel</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
