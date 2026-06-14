@extends('layouts.admin')
@section('title', 'PMC Reports')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><div><h1 class="h4 mb-1">PMC Reports</h1><div class="small text-muted">Export-ready curriculum, faculty, timetable, student success, and action reports with source links.</div></div>@include('academics.pmc.v003.partials.nav')</div>
    <div class="row g-3">@foreach($reports as $report)<div class="col-md-6 col-xl-4"><div class="card shadow-sm h-100"><div class="card-body py-3"><div class="d-flex justify-content-between"><div><div class="fw-semibold">{{ $report['label'] }}</div><div class="small text-muted">Current filtered result: {{ $report['count'] }}</div></div><div class="h4">{{ $report['count'] }}</div></div><div class="mt-2 d-flex gap-2"><a class="btn btn-sm btn-outline-primary" href="{{ $report['route'] }}">Open source</a><a class="btn btn-sm btn-outline-secondary" href="{{ route('academics.pmc.export', $report['key']) }}">Export</a></div></div></div></div>@endforeach</div>
</div>
@endsection
