@extends('layouts.admin')
@section('title', $section['title'])

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ $section['title'] }}</h1>
            <div class="small text-muted">{{ $section['description'] }}</div>
        </div>
        <div class="btn-group btn-group-sm">
            <a href="{{ route('academics.pmc.index') }}" class="btn btn-outline-secondary">PMC OS</a>
            <a href="{{ route('academics.pmc.reports') }}" class="btn btn-outline-primary">Reports</a>
        </div>
    </div>

    @if(! empty($section['metrics']))
        <div class="row g-2 mb-3">
            @foreach($section['metrics'] as $label => $value)
                <div class="col-6 col-xl-3">
                    <a href="#source-list" class="text-decoration-none">
                        <div class="card shadow-sm">
                            <div class="card-body py-2">
                                <div class="small text-muted">{{ str($label)->replace('_', ' ')->title() }}</div>
                                <div class="h5 mb-0">{{ $value }}</div>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    <div class="card shadow-sm" id="source-list">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <div class="fw-semibold">Filtered Source List</div>
            <span class="small text-muted">Search/filter/sort can be layered here as volume grows; current list is scoped and database-backed.</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Record</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($section['items'] as $item)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $item['title'] }}</div>
                                <div class="small text-muted">{{ $item['subtitle'] }}</div>
                            </td>
                            <td><span class="badge text-bg-light">{{ $item['status'] }}</span></td>
                            <td class="text-end"><a href="{{ $item['action'] }}" class="btn btn-sm btn-outline-primary">Open source</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted py-4">No records match the current PMC scope.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
