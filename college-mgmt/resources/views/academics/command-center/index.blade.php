@extends('layouts.admin')
@section('title', $title)

@php
    $severityClass = fn ($severity) => match($severity) {
        'critical' => 'danger',
        'high' => 'warning',
        'medium' => 'info',
        default => 'secondary',
    };
@endphp

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ $title }}</h1>
            <div class="text-muted small">{{ $scopeSummary['label'] }} - {{ $scopeSummary['detail'] }}</div>
        </div>
        <div class="btn-group btn-group-sm">
            <a href="{{ route('academics.command-center.index') }}" class="btn btn-outline-primary @if($workspace === 'command') active @endif">Command</a>
            <a href="{{ route('academics.workspaces.show', 'pmc') }}" class="btn btn-outline-primary @if($workspace === 'pmc') active @endif">PMC</a>
            <a href="{{ route('academics.workspaces.show', 'coe') }}" class="btn btn-outline-primary @if($workspace === 'coe') active @endif">CoE</a>
            <a href="{{ route('academics.workspaces.show', 'iqac') }}" class="btn btn-outline-primary @if($workspace === 'iqac') active @endif">IQAC</a>
            <a href="{{ route('academics.workspaces.show', 'program') }}" class="btn btn-outline-primary @if($workspace === 'program') active @endif">Program</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <a class="text-decoration-none" href="{{ route('academics.governance.index') }}">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3">
                        <div class="text-muted small">Members</div>
                        <div class="h4 mb-0">{{ $kpis['active_members'] }}</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a class="text-decoration-none" href="{{ route('academics.governance.index') }}#scopes">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3">
                        <div class="text-muted small">My Active Scopes</div>
                        <div class="h4 mb-0">{{ $kpis['active_scopes'] }}</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a class="text-decoration-none" href="#attention">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3">
                        <div class="text-muted small">Open Attention Items</div>
                        <div class="h4 mb-0">{{ $kpis['open_items'] }}</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a class="text-decoration-none" href="{{ route('academics.governance.index') }}#members">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3">
                        <div class="text-muted small">Visible Users</div>
                        <div class="h4 mb-0">{{ $kpis['visible_users'] }}</div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        @foreach(['governance' => 'Governance', 'pmc' => 'PMC', 'coe' => 'CoE', 'iqac' => 'IQAC'] as $key => $label)
            <div class="col-md-3">
                <a class="text-decoration-none" href="{{ $key === 'governance' ? route('academics.command-center.index') : route('academics.workspaces.show', $key) }}">
                    <div class="card shadow-sm">
                        <div class="card-body py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-semibold">{{ $label }}</span>
                                <span class="badge text-bg-light">{{ $branchSummary[$key] ?? 0 }}</span>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="row g-3" id="attention">
        <div class="col-xl-8">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold py-2">Attention Queues</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Queue</th>
                                <th>Branch</th>
                                <th>Severity</th>
                                <th class="text-end">Open</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($queues as $queue)
                                <tr>
                                    <td>
                                        <a href="{{ $queue['route'] }}" class="fw-semibold">{{ $queue['title'] }}</a>
                                        <div class="small text-muted">{{ $queue['description'] }}</div>
                                    </td>
                                    <td>{{ strtoupper($queue['workspace']) }}</td>
                                    <td><span class="badge text-bg-{{ $severityClass($queue['severity']) }}">{{ ucfirst($queue['severity']) }}</span></td>
                                    <td class="text-end">
                                        <a href="{{ $queue['route'] }}" class="btn btn-sm btn-outline-primary">{{ $queue['count'] }}</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">No attention queues for this workspace.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold py-2">Recent Academic Activity</div>
                <div class="list-group list-group-flush">
                    @forelse($activity as $log)
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between gap-2">
                                <div class="fw-semibold small">{{ str_replace('_', ' ', ucfirst($log->action)) }}</div>
                                <div class="text-muted small">{{ $log->created_at?->diffForHumans() }}</div>
                            </div>
                            <div class="small">{{ $log->description }}</div>
                            <div class="small text-muted">{{ $log->actor?->name ?? 'System' }}</div>
                        </div>
                    @empty
                        <div class="list-group-item text-muted">No activity yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
