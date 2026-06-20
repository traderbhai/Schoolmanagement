@extends('layouts.admin')
@section('title', 'Program Leadership OS')

@php
    $sections = [
        'portfolio' => ['label' => 'Program Portfolio', 'route' => route('academics.program-leadership.portfolio'), 'icon' => 'bi-mortarboard'],
        'delivery' => ['label' => 'Course Delivery', 'route' => route('academics.program-leadership.course-delivery'), 'icon' => 'bi-calendar-week'],
        'students' => ['label' => 'Student Success', 'route' => route('academics.program-leadership.student-success'), 'icon' => 'bi-people'],
        'quality' => ['label' => 'Quality Signals', 'route' => route('academics.program-leadership.quality-signals'), 'icon' => 'bi-graph-up-arrow'],
    ];
@endphp

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Program Leadership OS</h1>
            <div class="small text-muted">{{ $scopeSummary['label'] }} - {{ $scopeSummary['detail'] }}</div>
        </div>
        <div class="btn-group btn-group-sm">
            <a href="{{ route('academics.workspaces.show', 'program') }}" class="btn btn-outline-secondary">Workspace</a>
            <a href="{{ route('academics.program-leadership.reports') }}" class="btn btn-outline-primary">Reports</a>
            <a href="{{ route('chair.dashboard') }}" class="btn btn-primary">Chair Dashboard</a>
        </div>
    </div>

    <div class="row g-2 mb-3">
        @foreach([
            ['label' => 'Programs', 'value' => $kpis['programs'], 'route' => route('academics.program-leadership.portfolio', ['metric' => 'active_programs'])],
            ['label' => 'Active Students', 'value' => $kpis['active_students'], 'route' => null],
            ['label' => 'Delivery Gaps', 'value' => $kpis['delivery_gaps'], 'route' => route('academics.program-leadership.course-delivery', ['metric' => 'delivery_gaps'])],
            ['label' => 'Student Risk', 'value' => $kpis['student_risk'], 'route' => route('academics.program-leadership.student-success', ['metric' => 'student_risk'])],
        ] as $metric)
            <div class="col-6 col-xl-3">
                @if($metric['route'])
                    <a class="text-decoration-none d-block h-100" href="{{ $metric['route'] }}">
                        <div class="card shadow-sm h-100">
                            <div class="card-body py-2">
                                <div class="small text-muted">{{ $metric['label'] }}</div>
                                <div class="h4 mb-0">{{ $metric['value'] }}</div>
                            </div>
                        </div>
                    </a>
                @else
                    <div class="card shadow-sm h-100" aria-label="{{ $metric['label'] }} summary">
                        <div class="card-body py-2">
                            <div class="small text-muted">{{ $metric['label'] }}</div>
                            <div class="h4 mb-0">{{ $metric['value'] }}</div>
                            <div class="small text-muted">Summary only</div>
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        @foreach($sections as $key => $meta)
            @php($section = $$key)
            <div class="col-xl-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <div class="fw-semibold"><i class="bi {{ $meta['icon'] }} me-1"></i>{{ $meta['label'] }}</div>
                        <a href="{{ $meta['route'] }}" class="btn btn-sm btn-outline-primary">Open</a>
                    </div>
                    <div class="card-body py-2">
                        <div class="small text-muted mb-2">{{ $section['description'] }}</div>
                        <div class="row g-2 mb-2">
                            @foreach($section['metrics'] as $label => $value)
                                <div class="col-6 col-md-3">
                                    <a href="{{ $meta['route'] }}?{{ http_build_query(['metric' => $label]) }}" class="text-decoration-none">
                                        <div class="border rounded p-2 h-100">
                                            <div class="small text-muted">{{ str($label)->replace('_', ' ')->title() }}</div>
                                            <div class="fw-semibold">{{ $value }}</div>
                                        </div>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th>Item</th><th>Status</th><th></th></tr></thead>
                                <tbody>
                                    @forelse($section['items']->take(5) as $item)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold small">{{ $item['title'] }}</div>
                                                <div class="small text-muted">{{ $item['subtitle'] }}</div>
                                            </td>
                                            <td><span class="badge text-bg-light">{{ $item['status'] }}</span></td>
                                            <td class="text-end"><a href="{{ $item['action'] }}" class="btn btn-sm btn-outline-secondary">Go</a></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="text-muted text-center py-3">No current exceptions in this program area.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
