@extends('layouts.admin')

@section('title', 'Manager Workspace')

@push('styles')
<style>
    .admission-compact .card { border-radius: 6px; }
    .admission-compact .card-body { padding: .75rem; }
    .admission-compact .table > :not(caption) > * > * { padding: .45rem .6rem; }
    .admission-compact .metric-row { color:inherit; text-decoration:none; }
    .admission-compact .metric-row:hover { background:#f8f9fa; }
</style>
@endpush

@section('content')
<div class="admission-compact">
<x-ui.page-header
    title="Manager Workspace"
    subtitle="Use this page to balance counsellor workload, find stale/unassigned leads, audit reminders, and close review queues."
    action-label="Review Queue"
    :action-route="route('admission.manager-reviews.index')"
    action-icon="bi-clipboard-check"
/>

<div class="alert alert-info border-0 shadow-sm d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 py-3 mb-3">
    <div class="d-flex gap-3">
        <div class="ui-kpi-tile-icon bg-white text-info"><i class="bi bi-diagram-3"></i></div>
        <div>
            <div class="fw-bold">Manager operating loop</div>
            <div class="small">1. Check team KPI rollup &nbsp; 2. Reassign stale/unassigned leads &nbsp; 3. Audit reminders &nbsp; 4. Close pending reviews or escalate.</div>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admission.leads.index', ['assigned' => 'unassigned']) }}" class="btn btn-outline-info btn-sm">Unassigned Leads</a>
        <a href="{{ route('admission.reminders.index') }}" class="btn btn-outline-info btn-sm">Reminder Queue</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent fw-bold d-flex justify-content-between align-items-center">
                <span>Team KPI Rollup</span>
                <span class="small text-muted fw-normal">Click counts to open matching records</span>
                <a href="{{ route('admission.reports.index') }}" class="btn btn-sm btn-outline-primary py-0">Reports</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th scope="col">Team Member</th><th scope="col">Workload</th><th scope="col">Converted</th><th scope="col">SLA</th><th scope="col">Stale</th><th scope="col">Follow-up %</th></tr></thead>
                    <tbody>
                    @forelse($teamKpis as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row['name'] }}</td>
                            <td><a href="{{ route('admission.applicants.index', ['counsellor_id' => $row['user_id']]) }}">{{ $row['workload'] }}</a></td>
                            <td><a href="{{ route('admission.leads.index', ['status' => 'converted', 'counsellor_id' => $row['user_id']]) }}">{{ $row['converted_leads'] }}</a></td>
                            <td><a href="{{ route('admission.attention.index', ['counsellor_id' => $row['user_id']]) }}">{{ $row['sla_breaches'] }}</a></td>
                            <td><a href="{{ route('admission.leads.index', ['counsellor_id' => $row['user_id'], 'sort' => 'last_contacted_at', 'direction' => 'asc']) }}">{{ $row['stale_leads'] }}</a></td>
                            <td>{{ $row['followup_compliance_pct'] }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <div class="fw-semibold text-dark">No team members in your current scope</div>
                                <div class="small">Assign admission members to this manager or adjust hierarchy scope to start tracking workload.</div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-bold d-flex justify-content-between align-items-center">
                <span>Unassigned And Stale Leads</span>
                <span class="small text-muted fw-normal">Assign owner or update next action</span>
                <a href="{{ route('admission.leads.index', ['per_page' => 25, 'sort' => 'last_contacted_at', 'direction' => 'asc']) }}" class="btn btn-sm btn-outline-primary py-0">View lead queue</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th scope="col">Name</th><th scope="col">Source</th><th scope="col">Priority</th><th scope="col">Age</th><th aria-label="Actions" scope="col"></th></tr></thead>
                    <tbody>
                    @forelse($unassignedLeads->merge($staleLeads)->unique('id')->take(15) as $lead)
                        <tr>
                            <td class="fw-semibold">{{ $lead->name }}</td>
                            <td>{{ $lead->source_label ?: 'Source not captured' }}</td>
                            <td>{{ ucfirst($lead->priority ?? 'normal') }}</td>
                            <td class="small text-muted">{{ optional($lead->last_activity_at)->diffForHumans() ?? 'No activity' }}</td>
                            <td><a href="{{ route('admission.leads.show', $lead) }}" class="btn btn-sm btn-outline-primary">Open lead</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                <div class="fw-semibold text-dark">No unassigned or stale leads</div>
                                <div class="small">Your visible lead queue is clear for unassigned ownership and stale activity.</div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center"><span class="fw-bold">Reminder Effectiveness</span><span class="small text-muted">Check failed or overdue cadences</span></div>
            <div class="card-body">
                @forelse($reminderStats as $status => $total)
                    <a href="{{ route('admission.reminders.index', ['status' => $status]) }}" class="metric-row d-flex justify-content-between border-bottom py-2">
                        <span>{{ ucfirst(str_replace('_', ' ', $status ?: 'unknown')) }}</span><strong>{{ $total }}</strong>
                    </a>
                @empty
                    <div class="text-center text-muted py-3">
                        <div class="fw-semibold text-dark">No reminder activity in this scope</div>
                        <div class="small">Scheduled, sent, failed, and escalated reminders will appear here once created.</div>
                    </div>
                @endforelse
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center"><span class="fw-bold">Pending Reviews</span><span class="small text-muted">Coach, audit, or escalate</span></div>
            <div class="list-group list-group-flush">
                @forelse($pendingReviews->take(8) as $review)
                    <a class="list-group-item list-group-item-action" href="{{ route('admission.manager-reviews.index') }}">
                        <span class="badge bg-warning text-dark me-1">{{ str_replace('_', ' ', $review->review_type) }}</span>
                        {{ $review->finding }}
                    </a>
                @empty
                    <div class="list-group-item text-center text-muted py-4">
                        <div class="fw-semibold text-dark">No pending manager reviews</div>
                        <div class="small">Coaching, audit, and escalation reviews are clear for your current scope.</div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
</div>
@endsection
