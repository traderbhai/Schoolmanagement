@extends('layouts.admin')
@section('title', 'Dean Branch Health')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><div><h1 class="h4 mb-1">Dean Branch Health</h1><div class="small text-muted">PMC, CoE, IQAC, Program Leadership, and Course Delivery rollups.</div></div>@include('academics.dean-os.partials.nav')</div>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th scope="col">Branch</th><th scope="col">Risk Band</th><th scope="col">Metrics</th><th scope="col">Actions</th><th aria-label="Actions" scope="col"></th></tr></thead>
                <tbody>
                @foreach($branches as $branch)
                    <tr>
                        <td class="fw-semibold">{{ $branch['label'] }}</td>
                        <td><span class="badge text-bg-{{ $branch['band'] === 'critical' ? 'danger' : ($branch['band'] === 'high' ? 'warning' : 'light') }}">{{ $branch['band'] }}</span></td>
                        <td class="small text-muted">{{ collect($branch['metrics'])->map(fn($v,$k)=>$k.': '.$v)->join(' | ') }}</td>
                        <td class="small">{{ $branch['open_actions'] }} open, {{ $branch['overdue_actions'] }} overdue</td>
                        <td class="text-end"><a href="{{ $branch['route'] }}" class="btn btn-sm btn-outline-primary">Open source</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
