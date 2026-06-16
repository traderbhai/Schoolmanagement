@extends('layouts.admin')
@section('title', $title)
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ $title }}</h1>
            <div class="small text-muted">Cross-check PMC allocations, groups, generated timetable, operational timetable, delivery trackers, and notifications. Scope: {{ $scopeLabel }}</div>
        </div>
        @include('academics.pmc.v041.partials.nav')
    </div>

    <div class="row g-2 mb-3">
        @foreach([
            ['Total Checks', $summary['total']],
            ['OK', $summary['ok']],
            ['Warnings', $summary['warn']],
            ['Blocks', $summary['block']],
            ['Mismatches', $summary['mismatches']],
        ] as [$label, $value])
            <div class="col-6 col-lg">
                <div class="card shadow-sm h-100">
                    <div class="card-body py-2">
                        <div class="small text-muted">{{ $label }}</div>
                        <a class="h5 mb-0 text-decoration-none" href="{{ route('academics.pmc.data-reconciliation.index', $label === 'OK' ? ['status' => 'ok'] : ($label === 'Warnings' ? ['status' => 'warn'] : ($label === 'Blocks' ? ['status' => 'block'] : []))) }}">{{ $value }}</a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header py-2 fw-semibold">Scheduled Run History</div>
        <div class="card-body py-2">
            <div class="border rounded p-2 mb-2">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <div class="small text-muted">Scheduler Health</div>
                        <div class="fw-semibold text-{{ $schedulerHealth['status'] === 'healthy' ? 'success' : ($schedulerHealth['status'] === 'warning' ? 'danger' : 'muted') }}">{{ $schedulerHealth['label'] }}</div>
                    </div>
                    <div>
                        <div class="small text-muted">Last Success</div>
                        <div class="small">{{ optional($schedulerHealth['last_completed_at'])->format('d M Y H:i') ?: 'Not recorded' }}</div>
                    </div>
                    <div>
                        <div class="small text-muted">Last Failure</div>
                        <a class="small text-decoration-none" href="{{ route('academics.pmc.data-reconciliation.index', ['run_status' => 'failed']) }}">{{ optional($schedulerHealth['last_failed_at'])->format('d M Y H:i') ?: 'None' }}</a>
                    </div>
                    <div>
                        <div class="small text-muted">Stale Running</div>
                        <a class="small fw-semibold text-decoration-none" href="{{ route('academics.pmc.data-reconciliation.index', ['run_status' => 'running']) }}">{{ $schedulerHealth['stale_running'] }}</a>
                    </div>
                </div>
                <div class="small text-muted mt-1">{{ $schedulerHealth['recommendation'] }}</div>
            </div>
            <div class="row g-2 mb-2">
                @foreach([
                    ['Run Total', $runSummary['total'], []],
                    ['Completed', $runSummary['completed'], ['run_status' => 'completed']],
                    ['Failed', $runSummary['failed'], ['run_status' => 'failed']],
                    ['Running', $runSummary['running'], ['run_status' => 'running']],
                    ['Manual Repairs', $runSummary['manual_repairs'], []],
                ] as [$label, $value, $params])
                    <div class="col-6 col-lg">
                        <div class="border rounded px-2 py-1">
                            <div class="small text-muted">{{ $label }}</div>
                            <a class="fw-semibold text-decoration-none" href="{{ route('academics.pmc.data-reconciliation.index', $params) }}">{{ $value }}</a>
                        </div>
                    </div>
                @endforeach
            </div>
            <form class="row g-2 align-items-end">
                <input type="hidden" name="status" value="{{ request('status') }}">
                <input type="hidden" name="group" value="{{ request('group') }}">
                <div class="col-md-3">
                    <label class="form-label small">Run Status</label>
                    <select class="form-select form-select-sm" name="run_status">
                        <option value="">All run statuses</option>
                        @foreach(['completed', 'failed', 'running'] as $status)
                            <option value="{{ $status }}" @selected(request('run_status') === $status)>{{ str($status)->headline() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-1">
                    <button class="btn btn-sm btn-outline-primary">Filter Runs</button>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('academics.pmc.data-reconciliation.index', request()->except(['run_status', 'audit_action', 'audit_actor_id', 'audit_from', 'audit_to'])) }}">All Runs</a>
                    <a class="btn btn-sm btn-outline-success" href="{{ route('academics.pmc.data-reconciliation.runs.export', ['run_status' => request('run_status')]) }}">Export Runs</a>
                </div>
                <div class="col-md-5 small text-muted">Run filter summary: {{ request('run_status') ? 'Run status=' . request('run_status') : 'All run history' }}</div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Started</th><th>Source</th><th>Status</th><th>Checks</th><th>Mismatches</th><th>Critical</th><th>Repaired</th><th>Actor</th><th>Failure / Note</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse($runs as $run)
                        <tr>
                            <td class="small">{{ optional($run->started_at)->format('d M Y H:i') }}</td>
                            <td>{{ str($run->source)->headline() }}</td>
                            <td><span class="badge text-bg-{{ $run->status === 'completed' ? 'success' : ($run->status === 'failed' ? 'danger' : 'secondary') }}">{{ str($run->status)->headline() }}</span></td>
                            <td>{{ $run->checks_count }}</td>
                            <td>{{ $run->mismatch_count }}</td>
                            <td>{{ $run->critical_count }}</td>
                            <td>{{ $run->repaired_count }}</td>
                            <td class="small text-muted">{{ $run->starter?->name }}</td>
                            <td class="small text-muted">{{ $run->failure_reason ?: data_get($run->metadata, 'note', 'None') }}</td>
                            <td>
                                @if($run->status === 'running' && $run->started_at && $run->started_at->lessThan(now()->subMinutes(30)))
                                    <form method="POST" action="{{ route('academics.pmc.data-reconciliation.runs.mark-failed', $run) }}" class="d-flex gap-1">
                                        @csrf
                                        @method('PATCH')
                                        <input class="form-control form-control-sm" name="reason" value="Stale reconciliation run closed by PMC." aria-label="Close stale run reason">
                                        <button class="btn btn-sm btn-outline-danger">Mark Failed</button>
                                    </form>
                                @else
                                    <span class="small text-muted">No action</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-muted">No scheduled reconciliation runs have been recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-top px-3 py-2">
            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                <div>
                    <div class="fw-semibold">Recent Reconciliation Audit Trail</div>
                    <div class="small text-muted">Visible audit events from refresh, repair, stale-run closure, and reconciliation subjects.</div>
                </div>
                <a class="btn btn-sm btn-outline-success" href="{{ route('academics.pmc.data-reconciliation.audit.export', ['audit_action' => request('audit_action'), 'audit_actor_id' => request('audit_actor_id'), 'audit_from' => request('audit_from'), 'audit_to' => request('audit_to')]) }}">Export Audit Trail</a>
            </div>
            @php
                $auditSummary = request('audit_action')
                    ? 'Action=' . str(request('audit_action'))->replace('_', ' ')->headline()
                    : 'All actions';
                $auditSummary .= request('audit_from') ? '; From=' . request('audit_from') : '';
                $auditSummary .= request('audit_to') ? '; To=' . request('audit_to') : '';
                $auditSummary .= request('audit_actor_id')
                    ? '; Actor=' . (optional($auditActors->firstWhere('id', (int) request('audit_actor_id')))->name ?? 'Selected actor')
                    : '';
            @endphp
            <form class="row g-2 align-items-end mb-2">
                <input type="hidden" name="status" value="{{ request('status') }}">
                <input type="hidden" name="group" value="{{ request('group') }}">
                <input type="hidden" name="run_status" value="{{ request('run_status') }}">
                <div class="col-md-4">
                    <label class="form-label small">Audit Action</label>
                    <select class="form-select form-select-sm" name="audit_action">
                        <option value="">All reconciliation audit actions</option>
                        @foreach([
                            'academic_pmc_v092_data_reconciliation_refreshed' => 'Refresh checks',
                            'academic_pmc_v093_data_reconciliation_repaired' => 'Repair checks',
                            'academic_pmc_v105_reconciliation_stale_run_closed' => 'Stale run closed',
                        ] as $action => $label)
                            <option value="{{ $action }}" @selected(request('audit_action') === $action)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">From</label>
                    <input class="form-control form-control-sm" type="date" name="audit_from" value="{{ request('audit_from') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">To</label>
                    <input class="form-control form-control-sm" type="date" name="audit_to" value="{{ request('audit_to') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Actor</label>
                    <select class="form-select form-select-sm" name="audit_actor_id">
                        <option value="">All actors</option>
                        @foreach($auditActors as $actor)
                            <option value="{{ $actor->id }}" @selected(request('audit_actor_id') === (string) $actor->id)>{{ $actor->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button class="btn btn-sm btn-outline-primary">Filter Audit</button>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('academics.pmc.data-reconciliation.index', request()->except(['audit_action', 'audit_actor_id', 'audit_from', 'audit_to'])) }}">All Audit</a>
                </div>
                <div class="col-md-2 small text-muted">Audit filter summary: {{ $auditSummary }}</div>
            </form>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Reason / Details</th><th>Subject</th></tr></thead>
                    <tbody>
                        @forelse($auditTrail as $audit)
                            <tr>
                                <td class="small text-muted">{{ optional($audit->created_at)->format('d M Y H:i') }}</td>
                                <td>{{ $audit->actor?->name ?: 'System' }}</td>
                                <td>
                                    <div class="fw-semibold">{{ str($audit->action)->replace('_', ' ')->headline() }}</div>
                                    <div class="small text-muted">{{ $audit->description }}</div>
                                </td>
                                <td class="small">{{ data_get($audit->metadata, 'reason') ?: data_get($audit->metadata, 'message') ?: data_get($audit->metadata, 'check_key') ?: 'Recorded' }}</td>
                                <td class="small text-muted">
                                    @if($audit->subject_type)
                                        {{ class_basename($audit->subject_type) }} #{{ $audit->subject_id }}
                                    @else
                                        General
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">No reconciliation audit activity has been recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <form class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Status</label>
                    <select class="form-select form-select-sm" name="status">
                        <option value="">All statuses</option>
                        @foreach(['ok', 'warn', 'block'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->headline() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Group</label>
                    <select class="form-select form-select-sm" name="group">
                        <option value="">All groups</option>
                        @foreach(['timetable', 'course_basket', 'sections_groups', 'course_delivery', 'notifications'] as $group)
                            <option value="{{ $group }}" @selected(request('group') === $group)>{{ str($group)->headline() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-1">
                    <button class="btn btn-sm btn-primary">Filter</button>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('academics.pmc.data-reconciliation.index') }}">Reset</a>
                </div>
            </form>
            <div class="d-flex justify-content-between align-items-center mt-2">
                <div class="small text-muted">Visible filter summary: {{ count(request()->query()) ? http_build_query(request()->query()) : 'All reconciliation checks' }}</div>
                <div class="d-flex gap-1">
                    <a class="btn btn-sm btn-outline-success" href="{{ route('academics.pmc.data-reconciliation.export', request()->query()) }}">Export Current View</a>
                    <form method="POST" action="{{ route('academics.pmc.data-reconciliation.refresh') }}">
                        @csrf
                        <button class="btn btn-sm btn-outline-primary">Refresh Checks</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header py-2 fw-semibold">Reconciliation Checks</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Check</th><th>Group</th><th>Status</th><th>Expected</th><th>Actual</th><th>Mismatch</th><th>Recommended Action</th><th>Checked</th><th>Repair</th></tr></thead>
                <tbody>
                    @forelse($checks as $check)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $check->title }}</div>
                                <div class="small text-muted">{{ $check->description }}</div>
                                @php($samples = data_get($check->details, 'sample_mismatches', []))
                                @if(!empty($samples))
                                    <div class="small mt-1">
                                        <span class="fw-semibold">Sample mismatches:</span>
                                        @foreach(array_slice($samples, 0, 3) as $sample)
                                            <div class="text-muted">{{ $sample['label'] ?? $sample['source'] ?? ('Record #' . ($sample['id'] ?? '')) }}</div>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td>{{ str($check->check_group)->headline() }}</td>
                            <td><span class="badge text-bg-{{ $check->status === 'ok' ? 'success' : ($check->status === 'block' ? 'danger' : 'warning') }}">{{ str($check->status)->headline() }} / {{ $check->severity }}</span></td>
                            <td>{{ $check->expected_count }}</td>
                            <td>{{ $check->actual_count }}</td>
                            <td>{{ $check->mismatch_count }}</td>
                            <td class="small">{{ $check->recommended_action }}</td>
                            <td class="small text-muted">{{ optional($check->checked_at)->format('d M Y H:i') }}<br>{{ $check->checker?->name }}</td>
                            <td>
                                @if($check->mismatch_count > 0)
                                    <form method="POST" action="{{ route('academics.pmc.data-reconciliation.repair', $check) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-primary">Repair</button>
                                    </form>
                                @else
                                    <span class="small text-muted">No action</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-muted">No reconciliation checks have been generated yet. Use Refresh Checks.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer py-2">{{ $checks->links() }}</div>
    </div>
</div>
@endsection
