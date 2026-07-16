@extends('layouts.admin')
@section('title', 'Dean Academics Command OS')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Dean Academics Command OS</h1>
            <div class="small text-muted">Department-level academic command, risk, reviews, handoff, and action tracking.</div>
        </div>
        @include('academics.dean-os.partials.nav')
    </div>
    <div class="alert alert-info border-0 shadow-sm small mb-3">
        <div class="fw-semibold mb-1">Dean daily command sequence</div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge text-bg-light border">1. Open today priority</span>
            <span class="badge text-bg-light border">2. Clear overdue approvals</span>
            <span class="badge text-bg-light border">3. Review critical risks</span>
            <span class="badge text-bg-light border">4. Assign branch actions</span>
            <span class="badge text-bg-light border">5. Check handoff blockers</span>
        </div>
        <div class="text-muted mt-2">Use this page as the Dean's starting point. Each linked metric opens the source list that explains the count and who owns the next action.</div>
    </div>

    <div class="card shadow-sm mb-3 border-{{ $todayPriority['level'] === 'danger' ? 'danger' : ($todayPriority['level'] === 'warning' ? 'warning' : 'primary') }}">
        <div class="card-body py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="small text-muted text-uppercase fw-semibold">Today Priority</div>
                <div class="fw-semibold">{{ $todayPriority['title'] }}</div>
                <div class="small text-muted">{{ $todayPriority['body'] }}</div>
                <div class="d-flex flex-wrap gap-1 mt-2">
                    @if($todayPriority['level'] === 'danger')
                        <span class="badge text-bg-danger">Owner: Dean + assigned branch</span>
                        <span class="badge text-bg-light">Source: Critical attention queue</span>
                    @elseif($todayPriority['level'] === 'warning')
                        <span class="badge text-bg-warning">Owner: Program or branch leader</span>
                        <span class="badge text-bg-light">Source: Program risk heatmap</span>
                    @else
                        <span class="badge text-bg-light">Owner: Dean</span>
                        <span class="badge text-bg-light">Source: Branch health review</span>
                    @endif
                </div>
            </div>
            <a class="btn btn-sm btn-{{ $todayPriority['level'] === 'danger' ? 'danger' : ($todayPriority['level'] === 'warning' ? 'warning' : 'primary') }}" href="{{ $todayPriority['route'] }}">{{ $todayPriority['action'] }}</a>
        </div>
    </div>

    <div class="row g-2 mb-3">
        @foreach([
            ['label' => 'Overdue Approvals', 'value' => $kpis['overdue_approvals'], 'route' => route('academics.dean-os.attention', 'overdue_dean_approvals')],
            ['label' => 'Open Actions', 'value' => $kpis['open_actions'], 'route' => route('academics.dean-os.reviews', ['status' => 'open'])],
            ['label' => 'Critical Program Risks', 'value' => $kpis['critical_program_risks'], 'route' => route('academics.dean-os.program-risk', ['band' => 'critical_high'])],
            ['label' => 'Handoff Blockers', 'value' => $kpis['handoff_blockers'], 'route' => route('academics.dean-os.handoff', ['status' => 'blocking'])],
            ['label' => 'Critical Attention', 'value' => $kpis['critical_attention'], 'route' => route('academics.dean-os.attention', 'critical_attention')],
        ] as $metric)
            <div class="col-6 col-xl">
                <x-ui.metric-card :href="$metric['route']" :label="$metric['label']" :value="$metric['value']" />
            </div>
        @endforeach
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap gap-2">
                @foreach([
                    ['Plan', route('academics.dean-os.planning.index')],
                    ['Govern', route('academics.dean-os.approval-cockpit.index')],
                    ['Deliver', route('academics.dean-os.faculty-workload.index')],
                    ['Assess', route('academics.dean-os.exam-readiness.index')],
                    ['Improve', route('academics.dean-os.quality-command.index')],
                    ['Student Success', route('academics.dean-os.student-success.index')],
                    ['Induction', route('academics.dean-os.induction.index')],
                    ['Analytics', route('academics.dean-os.analytics.index')],
                    ['Policy Audit', route('academics.dean-os.policy-audit.index')],
                ] as [$label, $url])
                    <a href="{{ $url }}" class="btn btn-sm btn-outline-secondary">{{ $label }}</a>
                @endforeach
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-7">
            <div class="card shadow-sm h-100">
                <div class="card-header py-2 d-flex justify-content-between"><span class="fw-semibold">Branch Health</span><a href="{{ route('academics.dean-os.branch-health') }}" class="btn btn-sm btn-outline-primary">Open</a></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Branch</th><th>Risk</th><th>Open Actions</th><th></th></tr></thead>
                        <tbody>
                        @foreach($branchHealth as $branch)
                            <tr>
                                <td><div class="fw-semibold">{{ $branch['label'] }}</div><div class="small text-muted">{{ collect($branch['metrics'])->map(fn($v,$k)=>$k.': '.$v)->join(' | ') }}</div></td>
                                <td><span class="badge text-bg-{{ $branch['band'] === 'critical' ? 'danger' : ($branch['band'] === 'high' ? 'warning' : 'light') }}">{{ $branch['band'] }}</span></td>
                                <td>{{ $branch['open_actions'] }} open, {{ $branch['overdue_actions'] }} overdue</td>
                                <td class="text-end"><a href="{{ $branch['route'] }}" class="btn btn-sm btn-outline-secondary">Source</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card shadow-sm h-100">
                <div class="card-header py-2 d-flex justify-content-between"><div><span class="fw-semibold">Dean Attention</span><div class="small text-muted">Start with critical and overdue items before routine reports.</div></div><a href="{{ route('academics.dean-os.attention', 'overdue_dean_approvals') }}" class="btn btn-sm btn-outline-primary">Queues</a></div>
                <div class="list-group list-group-flush">
                    @forelse($criticalItems as $item)
                        <a href="{{ $item['route'] }}" class="list-group-item list-group-item-action py-2">
                            <div class="d-flex justify-content-between gap-2"><span class="fw-semibold small">{{ $item['title'] }}</span><span class="badge text-bg-{{ $item['severity'] === 'critical' ? 'danger' : 'warning' }}">{{ $item['severity'] }}</span></div>
                            <div class="small text-muted">{{ $item['subtitle'] }}</div>
                            <div class="d-flex flex-wrap gap-1 mt-1">
                                <span class="badge text-bg-light">Owner: {{ $item['owner'] ?: 'Owner not assigned' }}</span>
                                <span class="badge text-bg-light">Source: {{ str($item['sourceType'] ?? 'dean')->replace('_', ' ')->title() }}</span>
                                @if(!empty($item['due']))
                                    <span class="badge text-bg-light">Due: {{ \Illuminate\Support\Carbon::parse($item['due'])->format('d M Y') }}</span>
                                @else
                                    <span class="badge text-bg-light">Due date not set</span>
                                @endif
                            </div>
                        </a>
                    @empty
                        <div class="list-group-item text-muted">No critical attention items. Continue with branch health, risk review, or open Dean actions.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="card shadow-sm">
                <div class="card-header py-2 d-flex justify-content-between"><div><span class="fw-semibold">Program Risk Heatmap</span><div class="small text-muted">Use reasons to decide whether the owner needs a mitigation plan or review meeting.</div></div><a href="{{ route('academics.dean-os.program-risk') }}" class="btn btn-sm btn-outline-primary">Open</a></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Program</th><th>Band</th><th>Reasons</th></tr></thead>
                        <tbody>
                        @foreach($programRisks->take(6) as $risk)
                            <tr>
                                <td class="fw-semibold">{{ $risk['program']->code ?? $risk['program']->name ?? 'Program not assigned' }}</td>
                                <td><span class="badge text-bg-{{ $risk['band'] === 'critical' ? 'danger' : ($risk['band'] === 'high' ? 'warning' : 'light') }}">{{ $risk['band'] }} {{ $risk['score'] }}</span></td>
                                <td class="small text-muted">{{ $risk['reasons']->join(', ') ?: 'No major risk signals' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card shadow-sm">
                <div class="card-header py-2 d-flex justify-content-between"><span class="fw-semibold">Review Actions</span><a href="{{ route('academics.dean-os.reviews') }}" class="btn btn-sm btn-outline-primary">Manage</a></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Action</th><th>Owner</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse($actions as $action)
                            <tr><td class="small fw-semibold">{{ $action->title }}</td><td class="small">{{ $action->owner?->name ?? 'Owner not assigned' }}</td><td><span class="badge text-bg-light">{{ str($action->status)->replace('_', ' ')->title() }}</span></td></tr>
                        @empty
                            <tr><td colspan="3" class="text-muted text-center py-3">No open Dean actions. Create actions from attention queues, review meetings, or branch health issues when follow-up is needed.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
