@extends('layouts.admin')
@section('title', 'PMC Automation And Attention')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3"><div><h1 class="h4 mb-1">PMC Automation And Attention</h1><div class="small text-muted">Rule-based signal refresh for blockers, assignments, escalations, review agenda, and internal notifications.</div></div>@include('academics.pmc.v004.partials.nav')</div>
    <form method="POST" action="{{ route('academics.pmc.v004.automation.refresh') }}" class="mb-3">@csrf<button class="btn btn-primary btn-sm">Run PMC Signal Refresh</button></form>
    <div class="row g-3"><div class="col-xl-5"><div class="card shadow-sm"><div class="card-header py-2 fw-semibold">Automation Rules</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Rule</th><th>Trigger</th><th>Status</th></tr></thead><tbody>@foreach($rules as $rule)<tr><td>{{ $rule->name }}</td><td>{{ $rule->trigger_key }}</td><td>{{ $rule->is_active ? 'active' : 'inactive' }}</td></tr>@endforeach</tbody></table></div></div></div>
    <div class="col-xl-7"><div class="card shadow-sm"><div class="card-header py-2 fw-semibold">Execution Log</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Execution</th><th>Status</th><th>When</th></tr></thead><tbody>@foreach($executions as $execution)<tr><td><div class="fw-semibold">{{ $execution->rule?->name ?? $execution->subject_key }}</div><div class="small text-muted">{{ $execution->result }}</div></td><td>{{ $execution->status }}</td><td>{{ optional($execution->executed_at)->format('d M H:i') }}</td></tr>@endforeach</tbody></table></div></div></div></div>
</div>
@endsection
