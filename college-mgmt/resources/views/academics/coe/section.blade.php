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
            <a href="{{ route('academics.coe.index') }}" class="btn btn-outline-secondary">CoE OS</a>
            <a href="{{ route('academics.coe.reports') }}" class="btn btn-outline-primary">Reports</a>
        </div>
    </div>

    <div class="alert alert-light border shadow-sm py-2 mb-3">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
            <div>
                <div class="fw-semibold">CoE source-list workflow</div>
                <div class="small text-muted">Use this list to diagnose the queue before changing source records or issuing official documents.</div>
                <div class="d-flex flex-wrap gap-1 mt-2">
                    <span class="badge text-bg-primary">Owner: CoE / Examination team</span>
                    <span class="badge text-bg-secondary">Source: {{ $section['title'] }}</span>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-1">
                <span class="badge text-bg-light">1. Filter exam/program/status</span>
                <span class="badge text-bg-light">2. Review blockers</span>
                <span class="badge text-bg-light">3. Open source workflow</span>
                <span class="badge text-bg-light">4. Export current view</span>
                <span class="badge text-bg-light">5. Recheck official/published boundary</span>
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
                    <span class="small text-muted">Scoped, database-backed CoE records linked to their source workflows.</span>
                </div>
                <a href="{{ request()->fullUrlWithQuery(['export' => 'current']) }}" class="btn btn-sm btn-outline-secondary">Export current view</a>
            </div>
            <form method="GET" action="{{ request()->url() }}" class="row g-2 align-items-end mt-2">
                @if(! empty($filters['metric']))
                    <input type="hidden" name="metric" value="{{ $filters['metric'] }}">
                @endif
                <div class="col-12 col-md-5">
                    <label for="coe-search" class="form-label small text-muted mb-1">Search</label>
                    <input id="coe-search" type="search" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control form-control-sm" placeholder="Search record, status, or program">
                </div>
                <div class="col-12 col-md-4">
                    <label for="coe-status" class="form-label small text-muted mb-1">Status</label>
                    <select id="coe-status" name="status" class="form-select form-select-sm">
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
            <div class="small text-muted mt-2">Visible filter summary: {{ $section['filter_summary'] ?? 'Showing all scoped CoE records.' }}</div>
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
                                <span class="badge text-bg-light border">Owner: CoE</span>
                                <span class="badge text-bg-light border">Source: {{ $section['title'] }}</span>
                            </td>
                            <td><span class="badge text-bg-light">{{ $item['status'] }}</span></td>
                            <td class="text-end"><a href="{{ $item['action'] }}" class="btn btn-sm btn-outline-primary">Open source</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                <div class="fw-semibold text-body">No CoE records match this source list.</div>
                                <div class="small">This usually means the current exam/program/status filters have no blockers, or the source workflow has not yet created matching exam, result, hall-ticket, transcript, appeal, or anomaly records.</div>
                                <div class="small mt-1">Before issuing official documents, recheck published-result, eligibility, registration approval, and transcript-readiness boundaries.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
