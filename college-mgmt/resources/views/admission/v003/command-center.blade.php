@extends('layouts.admin')
@section('title', 'Admission Command Center')
@section('content')
<div class="container-fluid py-4">
    @php
        $kpiCards = [
            [
                'label' => 'Lead Workload',
                'value' => $dashboard['kpiSummary']['workload'] ?? 0,
                'url' => route('admission.leads.index'),
                'hint' => 'Open scoped lead workload',
            ],
            [
                'label' => 'SLA Breaches',
                'value' => $dashboard['kpiSummary']['sla_breaches'] ?? 0,
                'url' => route('admission.attention.index', ['queue' => 'sla_breaches']),
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
    <x-ui.page-header
        title="Admission Command Center"
        subtitle="Control room for team workload, attention queues, calling progress, forecast gaps, and escalation decisions."
        action-label="Open Workbench"
        :action-route="route('admission.workbench')"
        action-icon="bi-kanban"
    />

    <details class="card border-0 shadow-sm mb-4 admission-control-cycle">
        <summary class="card-body py-3 d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3" aria-label="Show Admission supervisor control cycle">
            <div class="d-flex gap-3">
                <div class="ui-kpi-tile-icon bg-primary-subtle text-primary"><i class="bi bi-command"></i></div>
                <div>
                    <div class="fw-bold">Supervisor control cycle</div>
                    <div class="small text-muted">Use this when queues look noisy or the team needs a daily operating order.</div>
                </div>
            </div>
            <span class="btn btn-sm btn-outline-primary">View cycle</span>
        </summary>
        <div class="card-body border-top pt-3">
            <div class="small mb-3">1. Clear immediate attention &nbsp; 2. Assign or rebalance work &nbsp; 3. Unblock documents/payments/offers &nbsp; 4. Review forecast and automation.</div>
            <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-primary btn-sm" href="{{ route('admission.attention.index') }}">Attention Queues</a>
            <a class="btn btn-outline-primary btn-sm" href="{{ route('admission.manager-workspace.index') }}">Team Workspace</a>
            <a class="btn btn-outline-primary btn-sm" href="{{ route('admission.forecasting.index') }}">Forecast</a>
            <a class="btn btn-outline-primary btn-sm" href="{{ route('admission.automations.index') }}">Automations</a>
            </div>
        </div>
    </details>
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
                <div class="card-header d-flex justify-content-between align-items-center"><span class="fw-semibold">Immediate Attention</span><span class="small text-muted">Open the queue, assign an owner, close the blocker</span></div>
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
                <div class="card-header d-flex justify-content-between align-items-center"><span class="fw-semibold">Next Calls</span><span class="small text-muted">Monitor calling pressure</span></div>
                <div class="list-group list-group-flush">
                    @forelse($dashboard['callQueue'] as $lead)
                        <a class="list-group-item list-group-item-action" href="{{ route('admission.leads.show', $lead) }}">
                            <strong>{{ $lead->name }}</strong>
                            <div class="small text-muted">
                                {{ $lead->phone ?: 'Phone not recorded' }} |
                                {{ $lead->program?->name ?? 'Program not assigned' }} |
                                {{ ucfirst($lead->priority ?? 'normal') }}
                            </div>
                        </a>
                    @empty
                        <div class="list-group-item text-muted">
                            <div class="fw-semibold text-dark">No next calls in the current scope</div>
                            <div class="small mb-2">Assigned callbacks, hot leads, and no-response follow-ups are clear for the selected team view.</div>
                            <a href="{{ route('admission.calling-desk.index') }}" class="btn btn-sm btn-outline-primary">Open Calling Desk</a>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
