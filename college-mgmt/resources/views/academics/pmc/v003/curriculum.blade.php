@extends('layouts.admin')
@section('title', 'PMC Curriculum Governance')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><div><h1 class="h4 mb-1">PMC Curriculum Governance</h1><div class="small text-muted">Program structure, syllabus/version rollout, credits, OBE mapping, approval lifecycle, and compliance checks.</div></div>@include('academics.pmc.v003.partials.nav')</div>
    <div class="row g-2 mb-3"><div class="col-md-6"><div class="card shadow-sm"><div class="card-body py-2"><div class="small text-muted">Pending Approval</div><div class="h4 mb-0">{{ $pending_approval }}</div></div></div></div><div class="col-md-6"><div class="card shadow-sm"><div class="card-body py-2"><div class="small text-muted">Rollout Due</div><div class="h4 mb-0">{{ $rollout_due }}</div></div></div></div></div>
    <div class="card shadow-sm"><div class="card-header py-2 small text-muted">Visible filter summary: active curriculum plans | <a href="{{ route('academics.pmc.export', 'curriculum') }}">Export current view</a></div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Plan</th><th>Program</th><th>Owner</th><th>Approval</th><th>Score</th><th>Due</th></tr></thead><tbody>@foreach($plans as $plan)<tr><td>{{ $plan->title }}</td><td>{{ $plan->program?->code ?? '-' }}</td><td>{{ $plan->owner?->name ?? 'Unassigned' }}</td><td>{{ $plan->approval_status }}</td><td>{{ $plan->readiness_score }}%</td><td>{{ optional($plan->rollout_due_at)->format('d M Y') }}</td></tr>@endforeach</tbody></table></div><div class="card-footer py-2">{{ $plans->links() }}</div></div>
</div>
@endsection
