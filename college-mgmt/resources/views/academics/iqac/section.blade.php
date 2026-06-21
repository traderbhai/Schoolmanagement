@extends('layouts.admin')
@section('title', $section['title'])

@section('content')
@php
    $filters = $section['filters'] ?? [];
    $statusOptions = collect($section['items'] ?? [])->pluck('status')->filter()->unique()->sort()->values();
    $metricRoute = request()->url();
@endphp
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ $section['title'] }}</h1>
            <div class="small text-muted">{{ $section['description'] }}</div>
        </div>
        <div class="btn-group btn-group-sm">
            <a href="{{ route('academics.iqac.index') }}" class="btn btn-outline-secondary">IQAC OS</a>
            <a href="{{ route('academics.iqac.reports') }}" class="btn btn-outline-primary">Reports</a>
        </div>
    </div>

    <div class="alert alert-light border shadow-sm py-2 mb-3">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
            <div>
                <div class="fw-semibold">IQAC source-list workflow</div>
                <div class="small text-muted">Use this list to move from quality signal to owner, evidence, action, and closure.</div>
                <div class="d-flex flex-wrap gap-1 mt-2">
                    <span class="badge text-bg-primary">Owner: IQAC quality team</span>
                    <span class="badge text-bg-secondary">Source: {{ $section['title'] }}</span>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-1">
                <span class="badge text-bg-light">1. Filter program/term/status</span>
                <span class="badge text-bg-light">2. Review quality gap</span>
                <span class="badge text-bg-light">3. Open source workflow</span>
                <span class="badge text-bg-light">4. Check evidence/action owner</span>
                <span class="badge text-bg-light">5. Export current view</span>
            </div>
        </div>
    </div>

    @if(! empty($section['metrics']))
        <div class="row g-2 mb-3">
            @foreach($section['metrics'] as $label => $value)
                <div class="col-6 col-xl-3">
                    <a href="{{ $metricRoute }}?{{ http_build_query(array_filter(array_merge(request()->query(), ['metric' => $label]), fn ($value) => $value !== null && $value !== '')) }}" class="text-decoration-none">
                        <div class="card shadow-sm">
                            <div class="card-body py-2">
                                <div class="small text-muted">{{ str($label)->replace('_', ' ')->title() }}</div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="h5 mb-0">{{ $value }}</div>
                                    <span class="small text-primary">Open</span>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    <div class="card shadow-sm" id="source-list">
        <div class="card-header py-2">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <div class="fw-semibold">Filtered Source List ({{ $section['items']->count() }})</div>
                    <span class="small text-muted">Scoped, database-backed IQAC records linked to source workflows.</span>
                </div>
                <a href="{{ request()->fullUrlWithQuery(['export' => 'current']) }}" class="btn btn-sm btn-outline-secondary">Export current view</a>
            </div>
            <form method="GET" action="{{ request()->url() }}" class="row g-2 align-items-end mt-2">
                @if(! empty($filters['metric']))
                    <input type="hidden" name="metric" value="{{ $filters['metric'] }}">
                @endif
                <div class="col-12 col-md-5">
                    <label for="iqac-search" class="form-label small text-muted mb-1">Search</label>
                    <input id="iqac-search" type="search" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control form-control-sm" placeholder="Search record, status, or program">
                </div>
                <div class="col-12 col-md-4">
                    <label for="iqac-status" class="form-label small text-muted mb-1">Status</label>
                    <select id="iqac-status" name="status" class="form-select form-select-sm">
                        <option value="">All statuses</option>
                        @foreach($statusOptions as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill">Apply</button>
                    <a href="{{ ! empty($filters['metric']) ? request()->url() . '?' . http_build_query(['metric' => $filters['metric']]) : request()->url() }}" class="btn btn-sm btn-outline-secondary flex-fill">
                        {{ ! empty($filters['metric']) ? 'Reset queue' : 'Reset' }}
                    </a>
                </div>
            </form>
            <div class="small text-muted mt-2">Visible filter summary: {{ $section['filter_summary'] ?? 'Showing all scoped IQAC records.' }}</div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <caption class="visually-hidden">{{ $section['title'] }} source records</caption>
                <thead><tr><th>Record</th><th>Owner / Source</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                    @forelse($section['items'] as $item)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $item['title'] }}</div>
                                <div class="small text-muted">{{ $item['subtitle'] }}</div>
                            </td>
                            <td>
                                <span class="badge text-bg-light border">Owner: IQAC</span>
                                <span class="badge text-bg-light border">Source: {{ $section['title'] }}</span>
                            </td>
                            <td><span class="badge text-bg-light">{{ $item['status'] }}</span></td>
                            <td class="text-end"><a href="{{ $item['action'] }}" class="btn btn-sm btn-outline-primary">Open source</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                <div class="fw-semibold text-body">No IQAC records match this source list.</div>
                                <div class="small">This usually means the current program/term/status filters have no quality gaps, or source workflows have not yet created OBE mapping, attainment, feedback, audit evidence, or corrective-action records.</div>
                                <div class="small mt-1">Before closing a quality review, recheck owner assignment, evidence availability, action status, and target/threshold boundaries.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
