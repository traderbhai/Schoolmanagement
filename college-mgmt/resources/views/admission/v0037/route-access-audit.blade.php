@extends('layouts.admin')

@section('title', 'Admission Route Access Audit')

@section('content')
<div class="v037">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h3 class="fw-bold mb-1">Admission Route Access Audit</h3><div class="text-muted small">Reviewed route scopes, write-route risks, and access-control notes for production hardening.</div></div>
    <div class="d-flex gap-2"><a href="{{ route('admission.command-center.index') }}" class="btn btn-outline-primary btn-sm">Command Center</a><a href="{{ route('admission.v039.exports','route-policy') }}" class="btn btn-outline-secondary btn-sm">Export Policy Audit</a></div>
</div>

<div class="row g-2 mb-3">
@foreach($dashboard['stats'] as $label => $value)
    <div class="col-6 col-lg-4"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="small text-muted">{{ ucfirst(str_replace('_', ' ', $label)) }}</div><div class="fs-4 fw-bold">{{ $value }}</div></div></div></div>
@endforeach
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent fw-bold">Route Scope Register</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>Route</th><th>Method</th><th>URI</th><th>Scope</th><th>Risk</th><th>Status</th></tr></thead>
            <tbody>
            @foreach($dashboard['audits'] as $audit)
                <tr>
                    <td><strong>{{ $audit->route_name }}</strong></td>
                    <td>{{ $audit->method }}</td>
                    <td class="small">{{ $audit->uri }}</td>
                    <td>{{ $audit->required_scope }}</td>
                    <td><span class="badge bg-{{ $audit->risk_level === 'high' ? 'danger' : ($audit->risk_level === 'medium' ? 'warning text-dark' : 'secondary') }}">{{ ucfirst($audit->risk_level) }}</span></td>
                    <td>{{ ucfirst($audit->status) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">{{ $dashboard['audits']->links() }}</div>
</div>

<div class="card border-0 shadow-sm mt-3">
    <div class="card-header bg-transparent fw-bold">v0.039 Enforcement Review</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>Route</th><th>Method</th><th>URI</th><th>Expected Scope</th><th>Middleware</th><th>Risk</th><th>Missing Enforcement</th></tr></thead>
            <tbody>
            @foreach($enforcement as $route)
                <tr>
                    <td><strong>{{ $route['route_name'] }}</strong></td>
                    <td>{{ $route['method'] }}</td>
                    <td class="small">{{ $route['uri'] }}</td>
                    <td class="small">{{ $route['expected_scope'] }}</td>
                    <td class="small">{{ Str::limit($route['middleware'], 80) }}</td>
                    <td><span class="badge bg-{{ $route['risk'] === 'write' ? 'warning text-dark' : 'secondary' }}">{{ $route['risk'] }}</span></td>
                    <td>{!! $route['missing_enforcement'] ? '<span class="badge bg-danger">Yes</span>' : '<span class="badge bg-success">No</span>' !!}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
</div>
@endsection
