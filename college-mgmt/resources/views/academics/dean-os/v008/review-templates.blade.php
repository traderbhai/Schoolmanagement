@extends('layouts.admin')
@section('title', 'Dean Review Templates')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><div><h1 class="h4 mb-1">Advanced Review Meetings</h1><div class="small text-muted">Agenda templates, recurring reviews, minutes approval, and decision register.</div></div>@include('academics.dean-os.partials.nav')</div>
    <div class="row g-3">
        <div class="col-lg-5"><div class="card shadow-sm"><div class="card-header py-2 fw-semibold">Agenda Templates</div><div class="list-group list-group-flush">@foreach($templates as $template)<div class="list-group-item"><div class="fw-semibold">{{ $template->name }}</div><div class="small text-muted">{{ str_replace('_',' ', $template->review_type) }} | {{ $template->recurrence }}</div></div>@endforeach</div><div class="card-footer py-2">{{ $templates->links() }}</div></div></div>
        <div class="col-lg-7"><div class="card shadow-sm"><div class="card-header py-2 fw-semibold">Meeting Minutes</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th scope="col">Meeting</th><th scope="col">Status</th><th scope="col">Minutes</th></tr></thead><tbody>@foreach($meetings as $meeting)<tr><td>{{ $meeting->title }}</td><td>{{ $meeting->status }}</td><td><form method="POST" action="{{ route('academics.dean-os.meeting-minutes.store', $meeting) }}" class="d-flex gap-1">@csrf<input aria-label="Minutes and decisions" class="form-control form-control-sm" name="minutes" placeholder="Minutes and decisions" required><button class="btn btn-sm btn-primary">Save minutes</button></form></td></tr>@endforeach</tbody></table></div><div class="card-footer py-2">{{ $meetings->links() }}</div></div></div>
    </div>
</div>
@endsection
