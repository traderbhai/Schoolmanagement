@extends('layouts.admin')
@section('title', 'Admission Command Center')
@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Admission Command Center</h1>
            <div class="text-muted">Live operating view across attention, calls, KPIs, forecast, and saved workflows.</div>
        </div>
        <div class="btn-group flex-wrap">
            <a class="btn btn-outline-primary" href="{{ route('admission.communication.index') }}">Communication</a>
            <a class="btn btn-outline-primary" href="{{ route('admission.call-queue.index') }}">Call Queue</a>
            <a class="btn btn-outline-primary" href="{{ route('admission.pipeline.index') }}">Pipeline</a>
            <a class="btn btn-outline-primary" href="{{ route('admission.automations.index') }}">Automations</a>
        </div>
    </div>
    <div class="row g-3 mb-4">
        @foreach([
            'Workload' => $dashboard['kpiSummary']['workload'] ?? 0,
            'SLA Breaches' => $dashboard['kpiSummary']['sla_breaches'] ?? 0,
            'Calls Due' => $dashboard['callProductivity']['calls_due'] ?? 0,
            'Projected Gap' => $dashboard['forecast']->projected_gap ?? 0,
        ] as $label => $value)
            <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">{{ $label }}</div><div class="display-6 fw-semibold">{{ $value }}</div></div></div></div>
        @endforeach
    </div>
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header fw-semibold">Immediate Attention</div>
                <div class="card-body row g-3">
                    @foreach($dashboard['attentionQueues'] as $name => $items)
                        <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="small text-muted">{{ ucwords(str_replace('_', ' ', $name)) }}</div><div class="h4 mb-0">{{ count($items) }}</div></div></div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header fw-semibold">Next Calls</div>
                <div class="list-group list-group-flush">
                    @forelse($dashboard['callQueue'] as $lead)
                        <a class="list-group-item list-group-item-action" href="{{ route('admission.leads.show', $lead) }}"><strong>{{ $lead->name }}</strong><div class="small text-muted">{{ $lead->phone }} - {{ ucfirst($lead->priority ?? 'normal') }}</div></a>
                    @empty
                        <div class="list-group-item text-muted">No calls in queue.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
