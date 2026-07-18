@extends('layouts.admin')
@section('title', 'Admission Data Quality')
@section('content')
<div class="container-fluid py-4">
    <h1 class="h3 mb-3">Data Quality</h1>
    <form method="POST" action="{{ route('admission.data-quality.scan') }}" class="card mb-4">@csrf<div class="card-body row g-3 align-items-end"><div class="col-md-3"><label class="form-label">Type</label><select aria-label="Subject Type" class="form-select" name="subject_type"><option value="lead">Lead</option><option value="applicant">Applicant</option></select></div><div class="col-md-3"><label class="form-label">Record ID</label><input aria-label="Subject" name="subject_id" class="form-control" required></div><div class="col-md-3"><button class="btn btn-primary w-100">Scan</button></div></div></form>
    <div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th scope="col">Flag</th><th scope="col">Severity</th><th scope="col">Message</th><th scope="col">Status</th><th aria-label="Actions" scope="col"></th></tr></thead><tbody>@forelse($flags as $flag)<tr><td>{{ $flag->flag_type }}</td><td>{{ $flag->severity }}</td><td>{{ $flag->message }}</td><td>{{ $flag->status }}</td><td>@if($flag->status === 'open')<form method="POST" action="{{ route('admission.data-quality.resolve', $flag) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-success">Resolve</button></form>@endif</td></tr>@empty<tr><td colspan="5" class="text-muted text-center py-3">No flags.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
