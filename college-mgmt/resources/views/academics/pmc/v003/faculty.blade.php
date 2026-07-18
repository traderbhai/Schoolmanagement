@extends('layouts.admin')
@section('title', 'PMC Faculty Workload')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><div><h1 class="h4 mb-1">PMC Faculty Workload Governance</h1><div class="small text-muted">Load norms, overload/underload, adjunct needs, faculty availability, substitutions, and approvals.</div></div>@include('academics.pmc.v003.partials.nav')</div>
    <div class="row g-2 mb-3"><div class="col-md-6"><div class="card shadow-sm"><div class="card-body py-2"><div class="small text-muted">Overload</div><div class="h4 mb-0">{{ $overload }}</div></div></div></div><div class="col-md-6"><div class="card shadow-sm"><div class="card-body py-2"><div class="small text-muted">Adjunct Required</div><div class="h4 mb-0">{{ $adjunct_required }}</div></div></div></div></div>
    <div class="card shadow-sm"><div class="card-header py-2 small text-muted">Visible filter summary: all faculty load plans | <a href="{{ route('academics.pmc.export', 'faculty') }}">Export current view</a></div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th scope="col">Faculty</th><th scope="col">Program</th><th scope="col">Planned</th><th scope="col">Allocated</th><th scope="col">Mentor</th><th scope="col">Band</th><th scope="col">Status</th></tr></thead><tbody>@foreach($loads as $load)<tr><td>{{ $load->teacher?->user?->name ?? 'Faculty' }}</td><td>{{ $load->program?->code ?? '-' }}</td><td>{{ $load->planned_hours }}</td><td>{{ $load->allocated_hours }}</td><td>{{ $load->mentoring_load }}</td><td>{{ $load->load_band }}</td><td>{{ $load->status }}</td></tr>@endforeach</tbody></table></div><div class="card-footer py-2">{{ $loads->links() }}</div></div>
</div>
@endsection
