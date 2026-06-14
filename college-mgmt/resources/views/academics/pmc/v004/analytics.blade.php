@extends('layouts.admin')
@section('title', 'PMC Analytics And Reports')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3"><div><h1 class="h4 mb-1">PMC Analytics And Reports</h1><div class="small text-muted">Trend snapshots, review packs, scheduled reports, and filtered exports. {{ $filterSummary }}</div></div>@include('academics.pmc.v004.partials.nav')</div>
    <div class="card shadow-sm mb-3"><div class="row g-0">@foreach($reports as $report)<div class="col-md-3 border-end"><a href="{{ $report['route'] }}" class="d-block p-2 text-decoration-none"><div class="small text-muted">{{ $report['label'] }}</div><div class="fw-semibold">{{ $report['count'] }}</div></a></div>@endforeach</div></div>
    <div class="card shadow-sm"><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Snapshot</th><th>Program</th><th>Score</th><th>Band</th><th>Date</th></tr></thead><tbody>@foreach($snapshots as $snapshot)<tr><td>{{ str($snapshot->snapshot_type)->headline() }}</td><td>{{ $snapshot->program?->code ?? 'All programs' }}</td><td>{{ $snapshot->score }}%</td><td>{{ $snapshot->band }}</td><td>{{ optional($snapshot->snapshot_date)->format('d M Y') }}</td></tr>@endforeach</tbody></table></div><div class="card-footer py-2">{{ $snapshots->links() }}</div></div>
</div>
@endsection
