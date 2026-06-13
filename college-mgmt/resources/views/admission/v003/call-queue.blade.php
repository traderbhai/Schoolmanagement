@extends('layouts.admin')
@section('title', 'Admission Call Queue')
@section('content')
<div class="container-fluid py-4">
    <h1 class="h3 mb-3">Call Queue</h1>
    <div class="row g-3 mb-4">
        @foreach($productivity as $label => $value)<div class="col-6 col-md-3"><div class="card"><div class="card-body"><div class="small text-muted">{{ ucwords(str_replace('_', ' ', $label)) }}</div><div class="h3 mb-0">{{ $value }}</div></div></div></div>@endforeach
    </div>
    <div class="card">
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Lead</th><th>Phone</th><th>Priority</th><th>Score</th><th>Action</th></tr></thead><tbody>
            @forelse($queue as $lead)
                <tr><td><strong>{{ $lead->name }}</strong><div class="small text-muted">{{ $lead->program?->name }}</div></td><td><a href="tel:{{ $lead->phone }}">{{ $lead->phone }}</a></td><td>{{ ucfirst($lead->priority ?? 'normal') }}</td><td>{{ ucfirst($lead->score_band ?? '-') }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('admission.leads.show', $lead) }}">Open</a></td></tr>
            @empty
                <tr><td colspan="5" class="text-muted text-center py-4">No leads need calls.</td></tr>
            @endforelse
        </tbody></table></div>
    </div>
</div>
@endsection
