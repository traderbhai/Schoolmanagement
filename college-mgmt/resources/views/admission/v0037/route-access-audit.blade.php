@extends('layouts.admin')

@section('title', 'Admission Route Access Audit')

@section('content')
<div class="v037">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h3 class="fw-bold mb-1">Admission Route Access Audit</h3>
        <div class="text-muted small">Review route scopes, write-route risks, middleware coverage, and missing enforcement before opening Admission features to staff roles.</div>
    </div>
    <div class="d-flex gap-2"><a href="{{ route('admission.command-center.index') }}" class="btn btn-outline-primary btn-sm">Command Center</a><a href="{{ route('admission.v039.exports','route-policy') }}" class="btn btn-outline-secondary btn-sm">Export Policy Audit</a></div>
</div>

<div class="alert alert-info py-2 small mb-3">
    <strong>Security review workflow:</strong> scan high-risk write routes first, confirm expected scope and middleware, export the audit, then fix any route marked missing enforcement before release.
</div>

<div class="row g-2 mb-3">
@foreach($dashboard['stats'] as $label => $value)
    <div class="col-6 col-lg-4"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="small text-muted">{{ ucfirst(str_replace('_', ' ', $label)) }}</div><div class="fs-4 fw-bold">{{ $value }}</div></div></div></div>
@endforeach
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <span class="fw-bold">Route Scope Register</span>
        <span class="small text-muted">{{ $dashboard['audits']->total() }} records</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th scope="col">Route</th><th scope="col">Method</th><th scope="col">URI</th><th scope="col">Scope</th><th scope="col">Risk</th><th scope="col">Status</th></tr></thead>
            <tbody>
            @forelse($dashboard['audits'] as $audit)
                <tr>
                    <td><strong>{{ $audit->route_name }}</strong></td>
                    <td>{{ $audit->method }}</td>
                    <td class="small">{{ $audit->uri }}</td>
                    <td>{{ $audit->required_scope }}</td>
                    <td><span class="badge bg-{{ $audit->risk_level === 'high' ? 'danger' : ($audit->risk_level === 'medium' ? 'warning text-dark' : 'secondary') }}">{{ ucfirst($audit->risk_level) }}</span></td>
                    <td>{{ ucfirst($audit->status) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">
                        <div class="fw-semibold text-dark">No route scope audit records are available</div>
                        <div class="small">Refresh the Admission route audit before using this page as release evidence.</div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">{{ $dashboard['audits']->links() }}</div>
</div>

<div class="card border-0 shadow-sm mt-3">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <span class="fw-bold">v0.039 Enforcement Review</span>
        <span class="small text-muted">{{ count($enforcement) }} routes reviewed</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th scope="col">Route</th><th scope="col">Method</th><th scope="col">URI</th><th scope="col">Expected Scope</th><th scope="col">Middleware</th><th scope="col">Risk</th><th scope="col">Missing Enforcement</th></tr></thead>
            <tbody>
            @forelse($enforcement as $route)
                <tr>
                    <td><strong>{{ $route['route_name'] }}</strong></td>
                    <td>{{ $route['method'] }}</td>
                    <td class="small">{{ $route['uri'] }}</td>
                    <td class="small">{{ $route['expected_scope'] }}</td>
                    <td class="small">{{ Str::limit($route['middleware'], 80) }}</td>
                    <td><span class="badge bg-{{ $route['risk'] === 'write' ? 'warning text-dark' : 'secondary' }}">{{ $route['risk'] }}</span></td>
                    <td>{!! $route['missing_enforcement'] ? '<span class="badge bg-danger">Yes</span>' : '<span class="badge bg-success">No</span>' !!}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        <div class="fw-semibold text-dark">No enforcement review rows are available</div>
                        <div class="small">Route inventory must run before Admission security reviewers can confirm middleware and policy coverage.</div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
</div>
@endsection
