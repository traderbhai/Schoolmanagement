@extends('layouts.admin')
@section('title', 'PMC Analytics And Reports')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3"><div><h1 class="h4 mb-1">PMC Analytics And Reports</h1><div class="small text-muted">Trend snapshots, review packs, scheduled reports, and filtered exports. {{ $filterSummary }}</div></div>@include('academics.pmc.v004.partials.nav')</div>
    <div class="card shadow-sm mb-3"><div class="row g-0">@foreach($reports as $report)<div class="col-md-3 border-end"><a href="{{ $report['route'] }}" class="d-block p-2 text-decoration-none"><div class="small text-muted">{{ $report['label'] }}</div><div class="fw-semibold">{{ $report['count'] }}</div></a></div>@endforeach</div></div>
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <form class="row g-2 align-items-end">
                <div class="col-md-3"><label class="form-label small">Search</label><input aria-label="Snapshot type" class="form-control form-control-sm" name="search" value="{{ request('search') }}" placeholder="Snapshot type"></div>
                <div class="col-md-2"><label class="form-label small">Band</label><select aria-label="Band" class="form-select form-select-sm" name="band"><option value="">All</option>@foreach(['low','medium','high','critical'] as $band)<option value="{{ $band }}" @selected(request('band')===$band)>{{ ucfirst($band) }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label small">Sort</label><select aria-label="Sort" class="form-select form-select-sm" name="sort"><option value="snapshot_date" @selected(request('sort','snapshot_date')==='snapshot_date')>Date</option><option value="snapshot_type" @selected(request('sort')==='snapshot_type')>Snapshot</option><option value="score" @selected(request('sort')==='score')>Score</option><option value="band" @selected(request('sort')==='band')>Band</option></select></div>
                <div class="col-md-1"><label class="form-label small">Order</label><select aria-label="Direction" class="form-select form-select-sm" name="direction"><option value="desc" @selected(request('direction','desc')==='desc')>Desc</option><option value="asc" @selected(request('direction')==='asc')>Asc</option></select></div>
                <div class="col-md-4 d-flex gap-1"><button class="btn btn-sm btn-primary">Filter</button><a class="btn btn-sm btn-outline-secondary" href="{{ route('academics.pmc.analytics.index') }}">Reset</a><a class="btn btn-sm btn-outline-success" href="{{ route('academics.pmc.export', ['report' => 'analytics'] + request()->query()) }}">Export</a></div>
            </form>
        </div>
    </div>
    <div class="card shadow-sm"><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th scope="col">Snapshot</th><th scope="col">Program</th><th scope="col">Score</th><th scope="col">Band</th><th scope="col">Date</th></tr></thead><tbody>@foreach($snapshots as $snapshot)<tr><td>{{ str($snapshot->snapshot_type)->headline() }}</td><td>{{ $snapshot->program?->code ?? 'All programs' }}</td><td>{{ $snapshot->score }}%</td><td>{{ $snapshot->band }}</td><td>{{ optional($snapshot->snapshot_date)->format('d M Y') }}</td></tr>@endforeach</tbody></table></div><div class="card-footer py-2">{{ $snapshots->links() }}</div></div>
</div>
@endsection
