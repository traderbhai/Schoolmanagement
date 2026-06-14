@extends('layouts.admin')
@section('title', 'PMC Reviews, Decisions And Actions')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3"><div><h1 class="h4 mb-1">PMC Reviews, Decisions And Actions</h1><div class="small text-muted">Templates, recurring reviews, agenda, minutes, decision register, evidence, dependencies, and closure governance.</div></div>@include('academics.pmc.v004.partials.nav')</div>
    <div class="row g-3">
        <div class="col-lg-3"><div class="card shadow-sm h-100"><div class="card-header py-2 fw-semibold">Review Templates</div><div class="list-group list-group-flush">@foreach($templates as $row)<div class="list-group-item py-2"><div class="fw-semibold">{{ $row->title }}</div><div class="small text-muted">{{ $row->decision_type ?: 'template' }}</div></div>@endforeach</div></div></div>
        <div class="col-lg-9"><div class="card shadow-sm"><div class="card-header py-2 fw-semibold">Agenda Items</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Agenda</th><th>Owner</th><th>Status</th><th>Due</th></tr></thead><tbody>@foreach($agenda as $row)<tr><td><div class="fw-semibold">{{ $row->title }}</div><div class="small text-muted">{{ $row->body }}</div></td><td>{{ $row->owner?->name ?? 'PMC' }}</td><td>{{ $row->status }}</td><td>{{ optional($row->due_at)->format('d M') }}</td></tr>@endforeach</tbody></table></div><div class="card-footer py-2">{{ $agenda->links() }}</div></div></div>
    </div>
    <div class="row g-3 mt-0">
        <div class="col-xl-6"><div class="card shadow-sm"><div class="card-header py-2 fw-semibold">Minutes</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Minutes</th><th>Status</th><th>Evidence</th></tr></thead><tbody>@foreach($minutes as $row)<tr><td><div class="fw-semibold">{{ $row->title }}</div><div class="small text-muted">{{ $row->body }}</div></td><td>{{ $row->status }}</td><td>{{ collect($row->evidence ?? [])->pluck('label')->join(', ') }}</td></tr>@endforeach</tbody></table></div></div></div>
        <div class="col-xl-6"><div class="card shadow-sm"><div class="card-header py-2 fw-semibold">Decision Register</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Decision</th><th>Owner</th><th>Status</th></tr></thead><tbody>@foreach($decisions as $row)<tr><td><div class="fw-semibold">{{ $row->title }}</div><div class="small text-muted">{{ $row->decision_type }}</div></td><td>{{ $row->owner?->name ?? 'PMC' }}</td><td>{{ $row->status }}</td></tr>@endforeach</tbody></table></div></div></div>
    </div>
</div>
@endsection
