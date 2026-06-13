@extends('layouts.admin')
@section('title', 'Admission Lead Scoring')
@section('content')
<div class="container-fluid py-4">
    <h1 class="h3 mb-3">Lead Scoring</h1>
    <form method="POST" action="{{ route('admission.scoring.recalculate') }}" class="card mb-4">@csrf<div class="card-body row g-3 align-items-end"><div class="col-md-4"><label class="form-label">Lead ID</label><input name="lead_id" class="form-control" required></div><div class="col-md-4"><label class="form-label">Manual points</label><input name="manual_priority_points" type="number" class="form-control" value="0"></div><div class="col-md-4"><button class="btn btn-primary w-100">Recalculate</button></div></div></form>
    <div class="card"><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Lead</th><th>Score</th><th>Band</th><th>Scored</th></tr></thead><tbody>@forelse($scores as $score)<tr><td>{{ $score->lead?->name }}</td><td>{{ $score->score }}</td><td>{{ ucfirst($score->band) }}</td><td>{{ $score->scored_at?->diffForHumans() }}</td></tr>@empty<tr><td colspan="4" class="text-muted text-center py-3">No scores.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
