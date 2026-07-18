@extends('layouts.admin')
@section('title', 'Admission Partners')
@section('content')
<div class="container-fluid py-4">
    <h1 class="h3 mb-3">Partner And Channel Admissions</h1>
    <div class="row g-4">
        <div class="col-lg-4"><form method="POST" action="{{ route('admission.partners.store') }}" class="card">@csrf<div class="card-header fw-semibold">Partner</div><div class="card-body vstack gap-3"><input aria-label="Agency or partner name" class="form-control" name="name" placeholder="Agency/partner name" required><input aria-label="Partner type" class="form-control" name="type" value="agency"><input aria-label="Contact name" class="form-control" name="contact_name" placeholder="Contact name"><input aria-label="Contact email" class="form-control" name="contact_email" placeholder="Email"><input aria-label="Contact phone" class="form-control" name="contact_phone" placeholder="Phone"><button class="btn btn-primary">Create partner</button></div></form></div>
        <div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th scope="col">Name</th><th scope="col">Status</th><th scope="col">Leads</th><th scope="col">Conversion</th><th scope="col" aria-label="Actions"></th></tr></thead><tbody>@forelse($partners as $partner)@php($summary = $service->dashboard($partner))<tr><td>{{ $partner->name }}</td><td>{{ $partner->status }}</td><td>{{ $summary['leads'] }}</td><td>{{ $summary['conversion_pct'] }}%</td><td>@if($partner->status !== 'approved')<form method="POST" action="{{ route('admission.partners.approve', $partner) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-success">Approve partner</button></form>@endif</td></tr>@empty<tr><td colspan="5" class="text-muted text-center py-3">No partners.</td></tr>@endforelse</tbody></table></div></div></div>
    </div>
</div>
@endsection
