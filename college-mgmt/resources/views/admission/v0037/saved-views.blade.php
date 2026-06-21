@extends('layouts.admin')

@section('title', 'Admission Saved Views')

@section('content')
<div class="container-fluid py-3">
    @php
        $surfaceRoutes = [
            'assessment_control_room' => Route::has('admission.assessment-control-room.index') ? route('admission.assessment-control-room.index') : null,
            'counsellor_desk' => Route::has('admission.counsellor-desk.index') ? route('admission.counsellor-desk.index') : null,
            'calling_desk' => Route::has('admission.calling-desk.index') ? route('admission.calling-desk.index') : null,
            'command_center' => Route::has('admission.command-center.index') ? route('admission.command-center.index') : null,
            'communication_safety' => Route::has('admission.communication-safety.index') ? route('admission.communication-safety.index') : null,
            'offer_seat_control' => Route::has('admission.offer-seat-control.index') ? route('admission.offer-seat-control.index') : null,
            'automation_logs' => Route::has('admission.automation-simulation.index') ? route('admission.automation-simulation.index') : null,
            'schedule_conflicts' => Route::has('admission.assessment-schedule-conflicts.index') ? route('admission.assessment-schedule-conflicts.index') : null,
            'counsellor_performance' => Route::has('admission.counsellor-performance.index') ? route('admission.counsellor-performance.index') : null,
            'handoff_queue' => Route::has('admission.handoff.index') ? route('admission.handoff.index') : null,
            'assessment_day' => Route::has('admission.assessment-control-room.index') ? route('admission.assessment-control-room.index') : null,
        ];
        $selectedSurfaceLabel = $surfaces[$selectedSurface] ?? str_replace('_', ' ', $selectedSurface);
        $selectedSurfaceRoute = $surfaceRoutes[$selectedSurface] ?? null;
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div>
            <h3 class="fw-bold mb-1">Admission Saved Views</h3>
            <div class="text-muted small">Create reusable filtered work queues for command center, counsellor desk, assessments, communications, offers, and handoff.</div>
        </div>
        <form method="GET" action="{{ route('admission.saved-views.index') }}" class="d-flex gap-2 align-items-end">
            <div>
                <label class="form-label small mb-1" for="surface-filter">Surface</label>
                <select id="surface-filter" class="form-select form-select-sm" name="surface">
                    @foreach($surfaces as $key => $label)
                        <option value="{{ $key }}" @selected($selectedSurface === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-sm btn-outline-primary">Show</button>
        </form>
    </div>

    <div class="alert alert-info py-2 small mb-3">
        <strong>Saved-view workflow:</strong> pick the work surface, define status/priority/owner/date/sort filters, save a named view, then open the source surface and apply the same filter set during daily work.
        @if($selectedSurfaceRoute)
            <a href="{{ $selectedSurfaceRoute }}" class="ms-1">Open {{ $selectedSurfaceLabel }}</a>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success py-2">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger py-2">
            <div class="fw-semibold">Saved view could not be stored.</div>
            <div class="small">{{ $errors->first() }}</div>
        </div>
    @endif

    <form method="POST" action="{{ route('admission.saved-views.store') }}" class="card border-0 shadow-sm mb-3">
        @csrf
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <span class="fw-semibold">Create Saved View</span>
            <span class="small text-muted">Structured filters are stored in the database and reused by Admission work surfaces.</span>
        </div>
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-lg-3 col-md-4">
                    <label class="form-label small" for="surface">Work surface</label>
                    <select id="surface" class="form-select form-select-sm" name="surface" required>
                        @foreach($surfaces as $key => $label)
                            <option value="{{ $key }}" @selected(old('surface', $selectedSurface) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 col-md-4">
                    <label class="form-label small" for="view-name">View name</label>
                    <input id="view-name" class="form-control form-control-sm" name="name" value="{{ old('name') }}" placeholder="High priority parent calls" required>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label small" for="filter-status">Status</label>
                    <select id="filter-status" class="form-select form-select-sm" name="filters[status]">
                        @foreach($filterOptions['status'] as $key => $label)
                            <option value="{{ $key }}" @selected(old('filters.status') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label small" for="filter-priority">Priority</label>
                    <select id="filter-priority" class="form-select form-select-sm" name="filters[priority]">
                        @foreach($filterOptions['priority'] as $key => $label)
                            <option value="{{ $key }}" @selected(old('filters.priority') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label small" for="filter-owner">Owner scope</label>
                    <select id="filter-owner" class="form-select form-select-sm" name="filters[owner_scope]">
                        @foreach($filterOptions['owner_scope'] as $key => $label)
                            <option value="{{ $key }}" @selected(old('filters.owner_scope') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label small" for="filter-date">Date range</label>
                    <select id="filter-date" class="form-select form-select-sm" name="filters[date_range]">
                        @foreach($filterOptions['date_range'] as $key => $label)
                            <option value="{{ $key }}" @selected(old('filters.date_range') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label small" for="filter-sort">Sort</label>
                    <select id="filter-sort" class="form-select form-select-sm" name="filters[sort]">
                        @foreach($filterOptions['sort'] as $key => $label)
                            <option value="{{ $key }}" @selected(old('filters.sort', 'due_soon') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-8 col-md-8">
                    <label class="form-label small" for="advanced-filters">Optional advanced filter payload</label>
                    <input id="advanced-filters" class="form-control form-control-sm" name="filters_json" value="{{ old('filters_json') }}" placeholder='Optional, for migration only: {"program":"MBA"}'>
                    <div class="form-text">Use this only when a surface has a filter that is not in the structured controls yet.</div>
                </div>
                <div class="col-lg-2 col-md-4 d-grid">
                    <button class="btn btn-sm btn-primary">Save View</button>
                </div>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="card-header py-2 d-flex flex-wrap justify-content-between gap-2">
            <span class="fw-semibold">{{ $selectedSurfaceLabel }} Views</span>
            <span class="small text-muted">Visible filter summary: surface = {{ $selectedSurfaceLabel }}; {{ $views->count() }} saved view(s)</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" aria-label="Saved views">
                <thead>
                    <tr>
                        <th>View</th>
                        <th>Surface</th>
                        <th>Scope</th>
                        <th>Filters</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($views as $view)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $view->name }}</div>
                                <div class="small text-muted">{{ $view->is_default ? 'Default view' : 'Saved view' }}</div>
                            </td>
                            <td>{{ $surfaces[$view->surface] ?? str_replace('_', ' ', $view->surface) }}</td>
                            <td>{{ $view->role_name ? str_replace('_', ' ', $view->role_name) : 'Personal/global' }}</td>
                            <td>
                                @forelse(($view->filters ?? []) as $key => $value)
                                    <span class="badge text-bg-light border me-1 mb-1">
                                        {{ str_replace('_', ' ', $key) }}:
                                        @if(is_array($value))
                                            {{ implode(', ', $value) }}
                                        @else
                                            {{ str_replace('_', ' ', (string) $value) }}
                                        @endif
                                    </span>
                                @empty
                                    <span class="text-muted small">No filters saved; this opens the full permitted surface scope.</span>
                                @endforelse
                                @if($surfaceRoutes[$view->surface] ?? null)
                                    <div class="small mt-1"><a href="{{ $surfaceRoutes[$view->surface] }}">Open source surface</a></div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                <div class="fw-semibold text-dark">No saved views exist for {{ $selectedSurfaceLabel }} yet</div>
                                <div class="small">Create the first view after choosing the status, priority, owner scope, date range, and sort order that this role should reuse.</div>
                                @if($selectedSurfaceRoute)
                                    <a href="{{ $selectedSurfaceRoute }}" class="btn btn-sm btn-outline-primary mt-2">Open {{ $selectedSurfaceLabel }}</a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
