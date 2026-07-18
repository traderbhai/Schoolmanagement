@extends('layouts.admin')
@section('title', 'PMC Operating System')

@php
    $sections = [
        'curriculum' => ['label' => 'Curriculum Readiness', 'route' => route('academics.pmc.curriculum-readiness'), 'icon' => 'bi-journal-check'],
        'faculty' => ['label' => 'Faculty Allocation', 'route' => route('academics.pmc.faculty-allocation'), 'icon' => 'bi-person-badge'],
        'timetable' => ['label' => 'Timetable Readiness', 'route' => route('academics.pmc.timetable-readiness'), 'icon' => 'bi-calendar-week'],
        'students' => ['label' => 'Student Monitoring', 'route' => route('academics.pmc.student-monitoring'), 'icon' => 'bi-exclamation-triangle'],
    ];
@endphp

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">PMC Operating System</h1>
            <div class="small text-muted">{{ $scopeSummary['label'] }} - {{ $scopeSummary['detail'] }}</div>
        </div>
        <div class="btn-group btn-group-sm">
            <a href="{{ route('academics.pmc.command') }}" class="btn btn-primary">PMC Command</a>
            <a href="{{ route('academics.workspaces.show', 'pmc') }}" class="btn btn-outline-secondary">Workspace</a>
            <a href="{{ route('academics.pmc.reports') }}" class="btn btn-outline-primary">Reports</a>
            <a href="{{ route('chair.timetable.builder') }}" class="btn btn-primary">Timetable Builder</a>
        </div>
    </div>

    <div class="row g-2 mb-3">
        @foreach([
            ['label' => 'Scoped Programs', 'value' => $kpis['programs'], 'route' => route('academics.pmc.programs', ['metric' => 'active_programs'])],
            ['label' => 'Curriculum Gaps', 'value' => $kpis['curriculum_gaps'], 'route' => route('academics.pmc.curriculum-readiness', ['metric' => 'curriculum_gaps'])],
            ['label' => 'Faculty Gaps', 'value' => $kpis['faculty_gaps'], 'route' => route('academics.pmc.faculty-allocation', ['metric' => 'faculty_gaps'])],
            ['label' => 'Student Risk', 'value' => $kpis['student_risk'], 'route' => route('academics.pmc.student-monitoring', ['metric' => 'student_risk'])],
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
                                <thead><tr><th scope="col">Item</th><th scope="col">Status</th><th aria-label="Actions" scope="col"></th></tr></thead>
                                <tbody>
                                    @forelse($section['items']->take(5) as $item)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold small">{{ $item['title'] }}</div>
                                                <div class="small text-muted">{{ $item['subtitle'] }}</div>
                                            </td>
                                            <td><span class="badge text-bg-light">{{ $item['status'] }}</span></td>
                                            <td class="text-end"><a href="{{ $item['action'] }}" class="btn btn-sm btn-outline-secondary">Open {{ $item['title'] }}</a></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="text-muted text-center py-3">No current exceptions in this PMC area.</td></tr>
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
