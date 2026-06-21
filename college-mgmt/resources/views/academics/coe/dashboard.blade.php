@extends('layouts.admin')
@section('title', 'CoE Operating System')

@php
    $sections = [
        'exam' => ['label' => 'Exam Readiness', 'route' => route('academics.coe.exam-readiness'), 'icon' => 'bi-calendar-check'],
        'marks' => ['label' => 'Marks And Results', 'route' => route('academics.coe.marks-results'), 'icon' => 'bi-award'],
        'hall' => ['label' => 'Hall Tickets', 'route' => route('academics.coe.hall-ticket-readiness'), 'icon' => 'bi-ticket-perforated'],
        'transcripts' => ['label' => 'Transcripts', 'route' => route('academics.coe.transcripts'), 'icon' => 'bi-file-earmark-text'],
        'appeals' => ['label' => 'Appeals And Anomalies', 'route' => route('academics.coe.appeals-anomalies'), 'icon' => 'bi-exclamation-triangle'],
    ];
@endphp

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">CoE Operating System</h1>
            <div class="small text-muted">{{ $scopeSummary['label'] }} - {{ $scopeSummary['detail'] }}</div>
        </div>
        <div class="btn-group btn-group-sm">
            <a href="{{ route('academics.workspaces.show', 'coe') }}" class="btn btn-outline-secondary">Workspace</a>
            <a href="{{ route('academics.coe.reports') }}" class="btn btn-outline-primary">Reports</a>
            <a href="{{ route('academics.coe.exam-readiness') }}" class="btn btn-primary">Exam Readiness</a>
        </div>
    </div>

    <div class="alert alert-info border-0 shadow-sm py-2 mb-3">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
            <div>
                <div class="fw-semibold">CoE exam operations sequence</div>
                <div class="small text-muted">Use this as the CoE starting point. Each KPI opens the scoped source list behind the count.</div>
            </div>
            <div class="d-flex flex-wrap gap-1">
                <span class="badge text-bg-light">1. Confirm exam readiness</span>
                <span class="badge text-bg-light">2. Clear marks/result blockers</span>
                <span class="badge text-bg-light">3. Release eligible hall tickets</span>
                <span class="badge text-bg-light">4. Issue transcripts from published results</span>
                <span class="badge text-bg-light">5. Resolve appeals and anomalies</span>
            </div>
        </div>
    </div>

    <div class="row g-2 mb-3">
        @foreach([
            ['label' => 'Upcoming Exams', 'value' => $kpis['upcoming_exams'], 'route' => route('academics.coe.exam-readiness', ['metric' => 'upcoming_exams'])],
            ['label' => 'Marks Pending', 'value' => $kpis['marks_pending'], 'route' => route('academics.coe.marks-results', ['metric' => 'marks_pending'])],
            ['label' => 'Hall Ticket Blocks', 'value' => $kpis['hall_ticket_blocks'], 'route' => route('academics.coe.hall-ticket-readiness', ['metric' => 'blocked_registrations'])],
            ['label' => 'Appeals/Anomalies', 'value' => $kpis['appeals_anomalies'], 'route' => route('academics.coe.appeals-anomalies', ['metric' => 'appeals_anomalies'])],
        ] as $metric)
            <div class="col-6 col-xl-3">
                <a class="text-decoration-none" href="{{ $metric['route'] }}">
                    <div class="card shadow-sm h-100">
                        <div class="card-body py-2">
                            <div class="small text-muted">{{ $metric['label'] }}</div>
                            <div class="h4 mb-0">{{ $metric['value'] }}</div>
                        </div>
                    </div>
                </a>
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
                                        <tr><td colspan="3" class="text-muted text-center py-3">No current exceptions in this CoE area.</td></tr>
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
