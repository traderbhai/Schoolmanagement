@extends('layouts.admin')
@section('title', 'PMC Policy Audit')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3"><div><h1 class="h4 mb-1">PMC Policy Audit</h1><div class="small text-muted">Route-level read/write risk, middleware, policy coverage, tested roles, and missing enforcement flags.</div></div>@include('academics.pmc.v004.partials.nav')</div>
    <div class="row g-2 mb-3"><div class="col-md-3"><div class="card shadow-sm"><div class="card-body py-2"><div class="small text-muted">Missing Enforcement</div><div class="h4 mb-0">{{ $missing }}</div></div></div></div><div class="col-md-3"><div class="card shadow-sm"><div class="card-body py-2"><div class="small text-muted">High Risk Routes</div><div class="h4 mb-0">{{ $highRisk }}</div></div></div></div></div>
    <div class="card shadow-sm"><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Route</th><th>Risk</th><th>Policy</th><th>Test</th><th>Roles</th></tr></thead><tbody>@foreach($audits as $audit)<tr><td><div class="fw-semibold">{{ $audit->route_name }}</div><div class="small text-muted">{{ $audit->method }} | {{ $audit->required_scope }}</div></td><td>{{ $audit->risk_level }}</td><td>{{ $audit->policy_present && !$audit->missing_enforcement ? 'enforced' : 'missing' }}</td><td>{{ $audit->last_test_status }}</td><td>{{ collect($audit->roles_tested ?? [])->join(', ') }}</td></tr>@endforeach</tbody></table></div><div class="card-footer py-2">{{ $audits->links() }}</div></div>
</div>
@endsection
