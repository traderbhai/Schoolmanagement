@extends('layouts.admission-partner')

@section('title', 'Submitted Partner Leads')
@section('page-title', 'Submitted Partner Leads')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="h5 mb-1">Submitted Leads</h2>
            <div class="text-muted small">Showing leads submitted by {{ $partner->name }} only.</div>
        </div>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('admission.partner-portal.dashboard') }}">Back to dashboard</a>
    </div>

    <form class="card card-body py-2 mb-3" method="GET">
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small text-muted mb-1" for="q">Search</label>
                <input id="q" class="form-control form-control-sm" name="q" value="{{ $q }}" placeholder="Name, phone, email, reference">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1" for="status">Status</label>
                <select id="status" class="form-select form-select-sm" name="status">
                    <option value="">All statuses</option>
                    @foreach(['new', 'contacted', 'interested', 'converted', 'not_interested'] as $option)
                        <option value="{{ $option }}" @selected($status === $option)>{{ str_replace('_', ' ', ucfirst($option)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-sm btn-primary">Apply filters</button>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('admission.partner-portal.leads') }}">Reset</a>
            </div>
        </div>
    </form>

    <div class="small text-muted mb-2">
        Filtered Source List ({{ $leads->total() }})
        @if($q) · Search: {{ $q }} @endif
        @if($status) · Status: {{ str_replace('_', ' ', $status) }} @endif
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <caption class="visually-hidden">Submitted partner leads</caption>
                <thead><tr><th scope="col">Lead</th><th scope="col">Program</th><th scope="col">Status</th><th scope="col">Priority</th><th scope="col">Reference</th><th scope="col">Submitted</th></tr></thead>
                <tbody>
                    @forelse($leads as $lead)
                        <tr>
                            <td><div class="fw-semibold">{{ $lead->name }}</div><div class="small text-muted">{{ $lead->email ?: $lead->phone }}</div></td>
                            <td>{{ $lead->program?->name ?? 'Not selected' }}</td>
                            <td><span class="badge text-bg-secondary">{{ str_replace('_', ' ', $lead->status) }}</span></td>
                            <td>{{ ucfirst($lead->priority ?? 'normal') }}</td>
                            <td>{{ $lead->partner_reference ?: '-' }}</td>
                            <td>{{ optional($lead->created_at)->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No leads match the current filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer py-2">{{ $leads->links() }}</div>
    </div>
</div>
@endsection
