@extends('layouts.admin')
@section('title', 'PMC Student Success')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><div><h1 class="h4 mb-1">PMC Student Success Command</h1><div class="small text-muted">Cohort risk, mentor intervention, retention risk, parent escalation, and academic action plans.</div></div>@include('academics.pmc.v003.partials.nav')</div>
    <div class="alert alert-light border py-2 small d-flex justify-content-between align-items-center gap-2 mb-3">
        <span>Use the current PMC student success surface for intervention effectiveness, overdue actions, parent-call follow-through, evidence gaps, and repeat-risk diagnostics.</span>
        <a class="btn btn-sm btn-outline-primary" href="{{ route('academics.pmc.student-success-v004.index') }}">Open Student Success v0.085</a>
    </div>
    <div class="row g-2 mb-3"><div class="col-md-6"><div class="card shadow-sm"><div class="card-body py-2"><div class="small text-muted">High Risk</div><div class="h4 mb-0">{{ $high_risk }}</div></div></div></div><div class="col-md-6"><div class="card shadow-sm"><div class="card-body py-2"><div class="small text-muted">Parent Escalations</div><div class="h4 mb-0">{{ $parent_escalations }}</div></div></div></div></div>
    <div class="card shadow-sm"><div class="card-header py-2 small text-muted">Visible filter summary: open student success plans | <a href="{{ route('academics.pmc.export', 'student_success') }}">Export current view</a></div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th scope="col">Student</th><th scope="col">Program</th><th scope="col">Risk</th><th scope="col">Band</th><th scope="col">Mentor</th><th scope="col">Status</th><th scope="col">Next Review</th></tr></thead><tbody>@foreach($plans as $plan)<tr><td>{{ $plan->student?->user?->name ?? 'Student' }}</td><td>{{ $plan->program?->code ?? '-' }}</td><td>{{ str_replace('_',' ', $plan->risk_type) }}</td><td>{{ $plan->risk_band }}</td><td>{{ $plan->mentor?->name ?? 'Unassigned' }}</td><td>{{ $plan->status }}</td><td>{{ optional($plan->next_review_at)->format('d M Y') }}</td></tr>@endforeach</tbody></table></div><div class="card-footer py-2">{{ $plans->links() }}</div></div>
</div>
@endsection
