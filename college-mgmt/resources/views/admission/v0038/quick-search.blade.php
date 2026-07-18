@extends('layouts.admin')
@section('title', 'Admission Quick Search')
@section('content')
<div class="container-fluid py-3">
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><div><h3 class="fw-bold mb-1">Admission Quick Search</h3><div class="text-muted small">Find leads, applicants, offers, sessions, and panels from one compact search.</div></div><a class="btn btn-sm btn-outline-primary" href="{{ route('admission.command-center.index') }}">Command Center</a></div>
<div class="card border-0 shadow-sm mb-3"><div class="card-body"><form method="GET" action="{{ route('admission.quick-search.index') }}" class="d-flex gap-2"><input aria-label="Search by name, phone, email, application number, offer number, or session" name="q" value="{{ $query }}" class="form-control" placeholder="Search by name, phone, email, application number, offer number, or session"><button type="submit" class="btn btn-primary">Search admission records</button></form></div></div>
<div class="card border-0 shadow-sm"><div class="card-header bg-white fw-bold">Results</div><div class="table-responsive"><table class="table table-sm mb-0" aria-label="Admission quick search results"><thead><tr><th scope="col">Type</th><th scope="col">Match</th><th aria-label="Actions" scope="col"></th></tr></thead><tbody>@forelse($results as $result)<tr><td>{{ ucfirst($result['type']) }}</td><td>{{ $result['label'] }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ $result['url'] }}">Open record</a></td></tr>@empty<tr><td colspan="3" class="text-muted text-center">Enter a search term to find admission records.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
