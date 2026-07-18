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

    <div class="alert alert-info border-0 shadow-sm py-2 mb-3">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
            <div>
                <div class="fw-semibold">Program leadership operating sequence</div>
                <div class="small text-muted">Use this as the program owner desk. Each KPI opens the scoped source list behind the count.</div>
                <div class="d-flex flex-wrap gap-1 mt-2">
                    <span class="badge text-bg-primary">Owner: assigned Program Leader / Director</span>
                    <span class="badge text-bg-secondary">Source: portfolio, delivery, student success, quality signals, Chair escalation</span>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-1">
                <span class="badge text-bg-light">1. Review portfolio scope</span>
                <span class="badge text-bg-light">2. Clear course delivery gaps</span>
                <span class="badge text-bg-light">3. Triage student risk</span>
                <span class="badge text-bg-light">4. Check quality signals</span>
                <span class="badge text-bg-light">5. Escalate through Chair workflows</span>
            </div>
        </div>
    </div>

    <div class="row g-2 mb-3">
        @foreach([
            ['label' => 'Programs', 'value' => $kpis['programs'], 'route' => route('academics.program-leadership.portfolio', ['metric' => 'active_programs'])],
            ['label' => 'Active Students', 'value' => $kpis['active_students'], 'route' => route('academics.program-leadership.student-success', ['metric' => 'active_students'])],
            ['label' => 'Delivery Gaps', 'value' => $kpis['delivery_gaps'], 'route' => route('academics.program-leadership.course-delivery', ['metric' => 'delivery_gaps'])],
            ['label' => 'Student Risk', 'value' => $kpis['student_risk'], 'route' => route('academics.program-leadership.student-success', ['metric' => 'student_risk'])],
        ] as $metric)
            <div class="col-6 col-xl-3">
                <x-ui.metric-card :href="$metric['route']" :label="$metric['label']" :value="$metric['value']" />
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
                        <a href="{{ $meta['route'] }}" class="btn btn-sm btn-outline-primary">Open {{ $meta['label'] }}</a>
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
                                <thead><tr><th scope="col">Item</th><th scope="col">Owner / Source</th><th scope="col">Status</th><th aria-label="Actions" scope="col"></th></tr></thead>
                                <tbody>
                                    @forelse($section['items']->take(5) as $item)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold small">{{ $item['title'] }}</div>
                                                <div class="small text-muted">{{ $item['subtitle'] }}</div>
                                            </td>
                                            <td>
                                                <span class="badge text-bg-light border">Owner: Program leadership</span>
                                                <span class="badge text-bg-light border">Source: {{ $meta['label'] }}</span>
                                            </td>
                                            <td><span class="badge text-bg-light">{{ $item['status'] }}</span></td>
                                            <td class="text-end"><a href="{{ $item['action'] }}" class="btn btn-sm btn-outline-secondary">Open {{ $item['title'] }}</a></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-muted text-center py-3">No current program exceptions for {{ $meta['label'] }}. Continue with portfolio scope review, course delivery checks, student interventions, quality signals, or Chair escalation if a source workflow is missing.</td></tr>
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
