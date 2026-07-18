@extends('layouts.admin')

@section('title', 'Leads and Enquiries')

@push('styles')
<style>
    .admission-compact .card { border-radius: 6px; }
    .admission-compact .card-body { padding: .75rem; }
    .admission-compact .table > :not(caption) > * > * { padding: .45rem .6rem; }
    .admission-compact .metric-link { display:block; color:inherit; text-decoration:none; }
    .admission-compact .metric-link:hover .card { border-color:#0d6efd; box-shadow:0 .125rem .45rem rgba(13,110,253,.18); }
    .admission-compact .sort-link { color:inherit; text-decoration:none; }
</style>
@endpush

@section('content')
@php
    $nextDirection = fn (string $field) => ($sort === $field && $direction === 'asc') ? 'desc' : 'asc';
    $sortIcon = fn (string $field) => $sort === $field ? ($direction === 'asc' ? 'bi-sort-up' : 'bi-sort-down') : 'bi-arrow-down-up';
    $sortUrl = fn (string $field) => request()->fullUrlWithQuery(['sort' => $field, 'direction' => $nextDirection($field)]);
    $filterSummary = collect([
        $status ? 'Status: ' . ucfirst(str_replace('_', ' ', $status)) : null,
        $source ? 'Source: ' . ucfirst(str_replace('_', ' ', $source)) : null,
        $programId ? 'Program filtered' : null,
        $search ? 'Search: ' . $search : null,
    ])->filter()->implode(' | ') ?: 'All visible leads';
@endphp

<div class="admission-compact">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="fw-bold mb-1">Leads & Enquiries</h3>
            <div class="text-muted small">{{ $leads->total() }} records after filters</div>
            <div class="small text-muted">Filter: {{ $filterSummary }}</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admission.leads.export-csv', request()->query()) }}" class="btn btn-outline-success btn-sm">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export
            </a>
            <a href="{{ route('admission.leads.analytics') }}" class="btn btn-outline-info btn-sm">
                <i class="bi bi-graph-up me-1"></i>Analytics
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
            {{ session('success') }}
            <button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="alert alert-info border-0 shadow-sm small mb-3">
        <div class="fw-semibold mb-1">Lead queue workflow</div>
        <div class="d-flex flex-wrap gap-2 mb-2">
            <span class="badge text-bg-light border">1. Filter by status/source/program</span>
            <span class="badge text-bg-light border">2. Open the highest-priority record</span>
            <span class="badge text-bg-light border">3. Confirm owner and last contact</span>
            <span class="badge text-bg-light border">4. Log call, reminder, or next action</span>
            <span class="badge text-bg-light border">5. Convert only when ready</span>
        </div>
        <div class="text-muted">The total above is the exact visible lead set after your Admission role scope and filters. Export uses the same filters shown here.</div>
    </div>

    <div class="row g-2 mb-3">
        @foreach([
            ['label' => 'Total', 'value' => $stats['total'], 'color' => 'primary', 'query' => []],
            ['label' => 'New', 'value' => $stats['new'], 'color' => 'info', 'query' => ['status' => 'new']],
            ['label' => 'Contacted', 'value' => $stats['contacted'], 'color' => 'secondary', 'query' => ['status' => 'contacted']],
            ['label' => 'Interested', 'value' => $stats['interested'], 'color' => 'warning', 'query' => ['status' => 'interested']],
            ['label' => 'Converted', 'value' => $stats['converted'], 'color' => 'success', 'query' => ['status' => 'converted']],
        ] as $metric)
            <div class="col-6 col-md">
                <a class="metric-link" href="{{ route('admission.leads.index', $metric['query']) }}">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small">{{ $metric['label'] }}</div>
                            <div class="fs-4 fw-bold text-{{ $metric['color'] }}">{{ $metric['value'] }}</div>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
        <div class="col-6 col-md">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Conversion</div>
                    <div class="fs-4 fw-bold text-success">{{ $stats['conversion_rate'] }}%</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form action="{{ route('admission.leads.index') }}" method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1">Search</label>
                    <input aria-label="Name, email, phone" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="Name, email, phone">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Status</label>
                    <select aria-label="Status" name="status" class="form-select form-select-sm">
                        <option value="">All Status</option>
                        @foreach(['new','contacted','interested','not_interested','converted'] as $leadStatus)
                            <option value="{{ $leadStatus }}" @selected($status === $leadStatus)>{{ ucfirst(str_replace('_', ' ', $leadStatus)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Source</label>
                    <select aria-label="Source" name="source" class="form-select form-select-sm">
                        <option value="">All Sources</option>
                        @foreach(['web_form','referral','advertisement','social_media','event','agent','other'] as $leadSource)
                            <option value="{{ $leadSource }}" @selected($source === $leadSource)>{{ ucfirst(str_replace('_', ' ', $leadSource)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Program</label>
                    <select aria-label="Program" name="program_id" class="form-select form-select-sm">
                        <option value="">All Programs</option>
                        @foreach($programs as $program)
                            <option value="{{ $program->id }}" @selected($programId == $program->id)>{{ $program->abbreviation ?? $program->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label small mb-1">Rows</label>
                    <select aria-label="Per Page" name="per_page" class="form-select form-select-sm">
                        @foreach([10,25,50,100] as $size)
                            <option value="{{ $size }}" @selected(request('per_page', 25) == $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-search me-1"></i>Apply filters</button>
                    <a href="{{ route('admission.leads.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col"><a class="sort-link" href="{{ $sortUrl('name') }}">Name <i class="bi {{ $sortIcon('name') }}"></i></a></th>
                        <th scope="col">Email / Phone</th>
                        <th scope="col">Program</th>
                        <th scope="col"><a class="sort-link" href="{{ $sortUrl('source') }}">Source <i class="bi {{ $sortIcon('source') }}"></i></a></th>
                        <th scope="col"><a class="sort-link" href="{{ $sortUrl('status') }}">Status <i class="bi {{ $sortIcon('status') }}"></i></a></th>
                        <th scope="col"><a class="sort-link" href="{{ $sortUrl('priority') }}">Priority <i class="bi {{ $sortIcon('priority') }}"></i></a></th>
                        <th scope="col">Owner</th>
                        <th scope="col"><a class="sort-link" href="{{ $sortUrl('last_contacted_at') }}">Last Contact <i class="bi {{ $sortIcon('last_contacted_at') }}"></i></a></th>
                        <th aria-label="Actions" scope="col"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($leads as $lead)
                        <tr>
                            <td class="fw-semibold">{{ $lead->name }}</td>
                            <td class="small">
                                <div>{{ $lead->email }}</div>
                                <div class="text-muted">{{ $lead->phone ?? 'Phone not provided' }}</div>
                            </td>
                            <td class="small">{{ $lead->program?->abbreviation ?? $lead->program?->name ?? 'Program not selected' }}</td>
                            <td class="small">{{ $lead->source_label }}</td>
                            <td><span class="{{ $lead->status_badge }}">{{ ucfirst(str_replace('_', ' ', $lead->status)) }}</span></td>
                            <td><span class="badge bg-{{ in_array($lead->priority, ['urgent','high']) ? 'danger' : 'secondary' }}">{{ ucfirst($lead->priority ?? 'normal') }}</span></td>
                            <td class="small">{{ $lead->assignedTo->name ?? 'Unassigned' }}</td>
                            <td class="small text-muted">{{ $lead->last_contacted_at?->format('d M H:i') ?? 'Never' }}</td>
                            <td class="text-end">
                                <a href="{{ route('admission.leads.show', $lead) }}" class="btn btn-sm btn-outline-primary py-0 px-2">Open lead</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-4">
                                <div class="text-center text-muted">
                                    <div class="fw-semibold text-body mb-1">No leads match this scoped view.</div>
                                    <div class="small mb-3">Clear filters, broaden status/source/program, or confirm whether new enquiries are entering through web forms, walk-ins, partner submissions, or imports.</div>
                                    <div class="d-flex justify-content-center flex-wrap gap-2">
                                        <a href="{{ route('admission.leads.index') }}" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
                                        <a href="{{ route('admission.walk-ins.index') }}" class="btn btn-sm btn-outline-primary">Open Walk-ins</a>
                                        <a href="{{ route('admission.partners.index') }}" class="btn btn-sm btn-outline-primary">Partner Leads</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2 py-2">
            <div class="small text-muted">Showing {{ $leads->firstItem() ?? 0 }}-{{ $leads->lastItem() ?? 0 }} of {{ $leads->total() }}</div>
            {{ $leads->links() }}
        </div>
    </div>
</div>
@endsection
