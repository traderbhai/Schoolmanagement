@extends('layouts.admin')
@section('title', 'PMC Policy Audit')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3"><div><h1 class="h4 mb-1">PMC Policy Audit</h1><div class="small text-muted">Route-level read/write risk, middleware, policy coverage, tested roles, and missing enforcement flags.</div></div>@include('academics.pmc.v004.partials.nav')</div>
    <div class="row g-2 mb-3"><div class="col-md-3"><div class="card shadow-sm"><div class="card-body py-2"><div class="small text-muted">Missing Enforcement</div><div class="h4 mb-0">{{ $missing }}</div></div></div></div><div class="col-md-3"><div class="card shadow-sm"><div class="card-body py-2"><div class="small text-muted">High Risk Routes</div><div class="h4 mb-0">{{ $highRisk }}</div></div></div></div></div>
    @if(!empty($enforcementDiagnostics))
        <div class="card shadow-sm mb-3">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <div>
                    <div class="fw-semibold">PMC High-Risk Route Enforcement Diagnostics</div>
                    <div class="small text-muted">Scope-aware lifecycle actions, broad write routes, audit-row gaps, and untested policy rows for real-world PMC control.</div>
                </div>
                <span class="badge text-bg-{{ ($enforcementDiagnostics['status'] ?? '') === 'ready' ? 'success' : 'warning' }}">{{ str_replace('_', ' ', $enforcementDiagnostics['status'] ?? 'attention_required') }}</span>
            </div>
            <div class="card-body py-2">
                <div class="row g-2 text-center">
                    @foreach([
                        ['Tracked Routes', $enforcementDiagnostics['tracked_routes'] ?? 0],
                        ['Critical Routes', $enforcementDiagnostics['critical_routes'] ?? 0],
                        ['High/Critical', $enforcementDiagnostics['high_risk_routes'] ?? 0],
                        ['Scope-Aware', $enforcementDiagnostics['scope_aware_routes'] ?? 0],
                        ['Broad Write', $enforcementDiagnostics['broad_write_routes'] ?? 0],
                        ['Missing Audit Rows', $enforcementDiagnostics['missing_audit_rows'] ?? 0],
                        ['Missing Enforcement', $enforcementDiagnostics['missing_enforcement_rows'] ?? 0],
                        ['Untested Rows', $enforcementDiagnostics['untested_rows'] ?? 0],
                    ] as [$label, $value])
                        <div class="col-6 col-md-3 col-xl-2">
                            <div class="border rounded p-2 h-100">
                                <div class="small text-muted">{{ $label }}</div>
                                <div class="fw-semibold">{{ $value }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
                @if(!empty($enforcementDiagnostics['broad_routes']) && $enforcementDiagnostics['broad_routes']->isNotEmpty())
                    <div class="small text-muted mt-2">Broad write routes still needing source-scope review: {{ $enforcementDiagnostics['broad_routes']->pluck('route')->join(', ') }}</div>
                @endif
                @if(!empty($enforcementDiagnostics['missing_route_names']) && $enforcementDiagnostics['missing_route_names']->isNotEmpty())
                    <div class="small text-danger mt-1">Missing audit rows: {{ $enforcementDiagnostics['missing_route_names']->join(', ') }}</div>
                @endif
            </div>
            <div class="card-footer py-2 small">{{ $enforcementDiagnostics['recommended_action'] }}</div>
        </div>
    @endif
    <div class="card shadow-sm"><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th scope="col">Route</th><th scope="col">Risk</th><th scope="col">Policy</th><th scope="col">Test</th><th scope="col">Roles</th></tr></thead><tbody>@foreach($audits as $audit)<tr><td><div class="fw-semibold">{{ $audit->route_name }}</div><div class="small text-muted">{{ $audit->method }} | {{ $audit->required_scope }}</div></td><td>{{ $audit->risk_level }}</td><td>{{ $audit->policy_present && !$audit->missing_enforcement ? 'enforced' : 'missing' }}</td><td>{{ $audit->last_test_status }}</td><td>{{ collect($audit->roles_tested ?? [])->join(', ') }}</td></tr>@endforeach</tbody></table></div><div class="card-footer py-2">{{ $audits->links() }}</div></div>
</div>
@endsection
