@extends('layouts.admin')

@section('title', 'Manager Workspace')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="fw-bold mb-1">Manager Workspace</h3>
        <div class="text-muted small">Team workload, escalations, reminders, and review queues.</div>
    </div>
    <a href="{{ route('admission.manager-reviews.index') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-clipboard-check me-1"></i>Review Queue</a>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent fw-bold">Team KPI Rollup</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Team Member</th><th>Workload</th><th>Converted</th><th>SLA</th><th>Stale</th><th>Follow-up %</th></tr></thead>
                    <tbody>
                    @foreach($teamKpis as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row['name'] }}</td>
                            <td>{{ $row['workload'] }}</td>
                            <td>{{ $row['converted_leads'] }}</td>
                            <td>{{ $row['sla_breaches'] }}</td>
                            <td>{{ $row['stale_leads'] }}</td>
                            <td>{{ $row['followup_compliance_pct'] }}%</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-bold">Unassigned And Stale Leads</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Name</th><th>Source</th><th>Priority</th><th>Age</th><th></th></tr></thead>
                    <tbody>
                    @foreach($unassignedLeads->merge($staleLeads)->unique('id')->take(15) as $lead)
                        <tr>
                            <td class="fw-semibold">{{ $lead->name }}</td>
                            <td>{{ $lead->source_label }}</td>
                            <td>{{ ucfirst($lead->priority ?? 'normal') }}</td>
                            <td class="small text-muted">{{ optional($lead->last_activity_at)->diffForHumans() ?? 'No activity' }}</td>
                            <td><a href="{{ route('admission.leads.show', $lead) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent fw-bold">Reminder Effectiveness</div>
            <div class="card-body">
                @foreach($reminderStats as $status => $total)
                    <div class="d-flex justify-content-between border-bottom py-2"><span>{{ ucfirst($status) }}</span><strong>{{ $total }}</strong></div>
                @endforeach
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-bold">Pending Reviews</div>
            <div class="list-group list-group-flush">
                @foreach($pendingReviews->take(8) as $review)
                    <a class="list-group-item list-group-item-action" href="{{ route('admission.manager-reviews.index') }}">
                        <span class="badge bg-warning text-dark me-1">{{ str_replace('_', ' ', $review->review_type) }}</span>
                        {{ $review->finding }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
