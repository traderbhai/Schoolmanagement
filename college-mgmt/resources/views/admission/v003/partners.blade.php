@extends('layouts.admin')
@section('title', 'Admission Partners')
@section('content')
<div class="container-fluid py-4">
    <h1 class="h3 mb-3">Partner And Channel Admissions</h1>
    <div class="row g-4">
        <div class="col-lg-4"><form method="POST" action="{{ route('admission.partners.store') }}" class="card">@csrf<div class="card-header fw-semibold">Partner</div><div class="card-body vstack gap-3"><input class="form-control" name="name" placeholder="Agency/partner name" required><input class="form-control" name="type" value="agency"><input class="form-control" name="contact_name" placeholder="Contact name"><input class="form-control" name="contact_email" placeholder="Email"><input class="form-control" name="contact_phone" placeholder="Phone"><button class="btn btn-primary">Create Partner</button></div></form></div>
        <div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Name</th><th>Status</th><th>Leads</th><th>Conversion</th><th></th></tr></thead><tbody>@forelse($partners as $partner)@php($summary = $service->dashboard($partner))<tr><td>{{ $partner->name }}</td><td>{{ $partner->status }}</td><td>{{ $summary['leads'] }}</td><td>{{ $summary['conversion_pct'] }}%</td><td>@if($partner->status !== 'approved')<form method="POST" action="{{ route('admission.partners.approve', $partner) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-success">Approve</button></form>@endif</td></tr>@empty<tr><td colspan="5" class="text-muted text-center py-3">No partners.</td></tr>@endforelse</tbody></table></div></div></div>
    </div>
</div>
@endsection
