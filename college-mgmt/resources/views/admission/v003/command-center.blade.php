@extends('layouts.admin')
@section('title', 'Admission Command Center')
@section('content')
<div class="container-fluid py-4">
    @php
        $kpiCards = [
            [
                'label' => 'Workload',
                'value' => $dashboard['kpiSummary']['workload'] ?? 0,
                'url' => route('admission.applicants.index'),
                'hint' => 'Open scoped applicant workload',
            ],
            [
                'label' => 'SLA Breaches',
                'value' => $dashboard['kpiSummary']['sla_breaches'] ?? 0,
                'url' => route('admission.attention.index'),
                'hint' => 'Review breached priority queues',
            ],
            [
                'label' => 'Calls Due',
                'value' => $dashboard['callProductivity']['calls_due'] ?? 0,
                'url' => route('admission.calling-desk.index'),
                'hint' => 'Start the next-call workflow',
            ],
            [
                'label' => 'Projected Gap',
                'value' => $dashboard['forecast']->projected_gap ?? 0,
                'url' => route('admission.forecasting.index'),
                'hint' => 'Open forecast detail',
            ],
        ];
    @endphp
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
        @foreach($kpiCards as $card)
            <div class="col-6 col-lg-3">
                <a class="text-decoration-none text-reset" href="{{ $card['url'] }}" aria-label="{{ $card['hint'] }}">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body py-3">
                            <div class="d-flex justify-content-between gap-2">
                                <div>
                                    <div class="text-muted small">{{ $card['label'] }}</div>
                                    <div class="display-6 fw-semibold">{{ $card['value'] }}</div>
                                </div>
                                <i class="bi bi-arrow-up-right text-muted"></i>
                            </div>
                            <div class="small text-muted">{{ $card['hint'] }}</div>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header fw-semibold">Immediate Attention</div>
                <div class="card-body row g-3">
                    @foreach($dashboard['attentionQueues'] as $name => $items)
                        <div class="col-md-4">
                            <a class="text-decoration-none text-reset" href="{{ route('admission.attention.index', ['queue' => $name]) }}">
                                <div class="border rounded p-3 h-100 bg-white">
                                    <div class="d-flex justify-content-between gap-2">
                                        <div class="small text-muted">{{ ucwords(str_replace('_', ' ', $name)) }}</div>
                                        <i class="bi bi-arrow-up-right small text-muted"></i>
                                    </div>
                                    <div class="h4 mb-0">{{ count($items) }}</div>
                                </div>
                            </a>
                        </div>
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
