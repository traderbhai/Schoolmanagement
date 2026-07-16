@extends('layouts.admin')
@section('title', 'IQAC Operating System')

@php
    $sections = [
        'obe' => ['label' => 'OBE Readiness', 'route' => route('academics.iqac.obe-readiness'), 'icon' => 'bi-diagram-3'],
        'attainment' => ['label' => 'Attainment Monitoring', 'route' => route('academics.iqac.attainment-monitoring'), 'icon' => 'bi-graph-up'],
        'feedback' => ['label' => 'Feedback Quality', 'route' => route('academics.iqac.feedback-quality'), 'icon' => 'bi-chat-square-text'],
        'audit' => ['label' => 'Audit And Compliance', 'route' => route('academics.iqac.audit-compliance'), 'icon' => 'bi-shield-check'],
    ];
@endphp

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">IQAC Operating System</h1>
            <div class="small text-muted">{{ $scopeSummary['label'] }} - {{ $scopeSummary['detail'] }}</div>
        </div>
        <div class="btn-group btn-group-sm">
            <a href="{{ route('academics.workspaces.show', 'iqac') }}" class="btn btn-outline-secondary">Workspace</a>
            <a href="{{ route('academics.iqac.reports') }}" class="btn btn-outline-primary">Reports</a>
            <a href="{{ route('academics.iqac.obe-readiness') }}" class="btn btn-primary">OBE Framework</a>
        </div>
    </div>

    <div class="alert alert-info border-0 shadow-sm py-2 mb-3">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
            <div>
                <div class="fw-semibold">IQAC quality operating sequence</div>
                <div class="small text-muted">Use this as the quality control room. Each KPI opens the scoped source list behind the count.</div>
                <div class="d-flex flex-wrap gap-1 mt-2">
                    <span class="badge text-bg-primary">Owner: IQAC quality team</span>
                    <span class="badge text-bg-secondary">Source: OBE mapping, attainment, feedback, audit evidence, corrective actions</span>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-1">
                <span class="badge text-bg-light">1. Close OBE readiness gaps</span>
                <span class="badge text-bg-light">2. Review CO/PO attainment misses</span>
                <span class="badge text-bg-light">3. Track feedback closure</span>
                <span class="badge text-bg-light">4. Collect audit evidence</span>
                <span class="badge text-bg-light">5. Create corrective actions</span>
            </div>
        </div>
    </div>

    <div class="row g-2 mb-3">
        @foreach([
            ['label' => 'OBE Gaps', 'value' => $kpis['obe_gaps'], 'route' => route('academics.iqac.obe-readiness', ['metric' => 'obe_gaps'])],
            ['label' => 'Mapping Gaps', 'value' => $kpis['mapping_gaps'], 'route' => route('academics.iqac.obe-readiness', ['metric' => 'mapping_gaps'])],
            ['label' => 'Target Misses', 'value' => $kpis['target_misses'], 'route' => route('academics.iqac.attainment-monitoring', ['metric' => 'target_misses'])],
            ['label' => 'Feedback Gaps', 'value' => $kpis['feedback_gaps'], 'route' => route('academics.iqac.feedback-quality', ['metric' => 'feedback_gaps'])],
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
                                <thead><tr><th>Item</th><th>Owner / Source</th><th>Status</th><th></th></tr></thead>
                                <tbody>
                                    @forelse($section['items']->take(5) as $item)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold small">{{ $item['title'] }}</div>
                                                <div class="small text-muted">{{ $item['subtitle'] }}</div>
                                            </td>
                                            <td>
                                                <span class="badge text-bg-light border">Owner: IQAC</span>
                                                <span class="badge text-bg-light border">Source: {{ $meta['label'] }}</span>
                                            </td>
                                            <td><span class="badge text-bg-light">{{ $item['status'] }}</span></td>
                                            <td class="text-end"><a href="{{ $item['action'] }}" class="btn btn-sm btn-outline-secondary">Go</a></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-muted text-center py-3">No current IQAC exceptions for {{ $meta['label'] }}. Continue with evidence review, attainment threshold checks, feedback closure, or open the source list to confirm filters and quality-action boundaries.</td></tr>
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
