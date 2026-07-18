@extends('layouts.admin')
@section('title', 'Dean Policy Audit')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><div><h1 class="h4 mb-1">Dean Route-Level Policy Audit</h1><div class="small text-muted">Route inventory, expected roles, read/write risk, policy flag, and last test status.</div></div>@include('academics.dean-os.partials.nav')</div>
    <div class="row g-2 mb-3"><div class="col-md-6"><div class="card shadow-sm"><div class="card-body py-2"><div class="small text-muted">Missing Policy</div><div class="h4 mb-0">{{ $missing }}</div></div></div></div><div class="col-md-6"><div class="card shadow-sm"><div class="card-body py-2"><div class="small text-muted">Write Routes</div><div class="h4 mb-0">{{ $write_routes }}</div></div></div></div></div>
    <div class="card shadow-sm"><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th scope="col">Route</th><th scope="col">Method</th><th scope="col">Roles</th><th scope="col">Risk</th><th scope="col">Policy</th><th scope="col">Status</th></tr></thead><tbody>@foreach($records as $record)<tr><td>{{ $record->route_name }}</td><td>{{ $record->method }}</td><td>{{ $record->expected_roles }}</td><td>{{ $record->risk_level }}</td><td>{{ $record->has_policy ? 'Yes' : 'No' }}</td><td>{{ $record->last_test_status }}</td></tr>@endforeach</tbody></table></div><div class="card-footer py-2">{{ $records->links() }}</div></div>
</div>
@endsection
