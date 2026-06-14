@extends('layouts.admin')
@section('title', 'Dean Reports')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><div><h1 class="h4 mb-1">Dean Reports</h1><div class="small text-muted">Exportable reports with visible filters and source links.</div></div>@include('academics.dean-os.partials.nav')</div>
    <div class="row g-3">
        @foreach($reports as $report)
            <div class="col-md-6 col-xl-4"><div class="card shadow-sm h-100"><div class="card-body py-3"><div class="d-flex justify-content-between"><div><div class="fw-semibold">{{ $report['label'] }}</div><div class="small text-muted">Current filtered result: {{ $report['count'] }}</div></div><div class="h4">{{ $report['count'] }}</div></div><div class="mt-2 d-flex gap-2"><a href="{{ $report['route'] }}" class="btn btn-sm btn-outline-primary">Open source</a><a href="{{ route('academics.dean-os.export', $report['key']) }}" class="btn btn-sm btn-outline-secondary">Export</a></div></div></div></div>
        @endforeach
    </div>
    <div class="card shadow-sm mt-3"><div class="card-header py-2 fw-semibold">Saved Views</div><div class="card-body py-2">@forelse($savedViews as $view)<span class="badge text-bg-light me-1">{{ $view->name }}</span>@empty<span class="text-muted small">No saved Dean views yet.</span>@endforelse</div></div>
</div>
@endsection
