@extends('layouts.admin')

@section('title', 'Counsellor Workspace')

@push('styles')
<style>
    .admission-compact .card { border-radius: 6px; }
    .admission-compact .card-body { padding: .75rem; }
    .admission-compact .table > :not(caption) > * > * { padding: .45rem .6rem; }
    .admission-compact .metric-link { display:block; color:inherit; text-decoration:none; }
    .admission-compact .metric-link:hover .card { border-color:#0d6efd; box-shadow:0 .125rem .45rem rgba(13,110,253,.18); }
</style>
@endpush

@section('content')
<div class="admission-compact">
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="fw-bold mb-1">Counsellor Workspace</h3>
        <div class="text-muted small">Assigned work, follow-ups, reminders, calls, and blockers.</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admission.counsellor-desk.index') }}" class="btn btn-success btn-sm"><i class="bi bi-person-workspace me-1"></i>My Day Desk</a>
        <a href="{{ route('admission.reminders.index') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-bell me-1"></i>Reminders</a>
        <a href="{{ route('admission.call-queue.index') }}" class="btn btn-primary btn-sm"><i class="bi bi-telephone me-1"></i>Call Queue</a>
    </div>
</div>

<div class="row g-3 mb-4">
    @foreach([
        ['Workload', $kpi['workload'] ?? 0, 'primary', route('admission.applicants.index', ['counsellor_id' => auth()->id()])],
        ['SLA Breaches', $kpi['sla_breaches'] ?? 0, 'danger', route('admission.attention.index')],
        ['Stale Leads', $kpi['stale_leads'] ?? 0, 'warning', route('admission.leads.index', ['sort' => 'last_contacted_at', 'direction' => 'asc'])],
        ['Calls Today', $callProductivity['calls_completed'] ?? 0, 'success', route('admission.call-queue.index')],
    ] as [$label, $value, $color, $url])
        <div class="col-6 col-lg-3">
            <a href="{{ $url }}" class="metric-link"><div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="d-flex justify-content-between"><div class="text-muted small">{{ $label }}</div><i class="bi bi-arrow-up-right small text-muted"></i></div>
                <div class="fs-4 fw-bold text-{{ $color }}">{{ $value }}</div>
            </div></div></a>
        </div>
    @endforeach
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent fw-bold d-flex justify-content-between align-items-center">
                <span>Assigned Leads</span>
                <a href="{{ route('admission.leads.index', ['counsellor_id' => auth()->id(), 'per_page' => 25]) }}" class="btn btn-sm btn-outline-primary py-0">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Name</th><th>Program</th><th>Priority</th><th>Next Action</th><th></th></tr></thead>
                    <tbody>
                    @forelse($assignedLeads as $lead)
                        <tr>
                            <td class="fw-semibold">{{ $lead->name }}</td>
                            <td>{{ $lead->program->name ?? 'Program not assigned' }}</td>
                            <td><span class="badge bg-{{ in_array($lead->priority, ['urgent','high']) ? 'danger' : 'secondary' }}">{{ ucfirst($lead->priority ?? 'normal') }}</span></td>
                            <td class="small">{{ $lead->next_action ?: 'Next action not set' }}</td>
                            <td><a class="btn btn-sm btn-outline-primary" href="{{ route('admission.leads.show', $lead) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-3">
                                <div class="fw-semibold text-dark">No assigned leads in your queue</div>
                                <div class="small text-muted">New leads appear here after an Admission Manager or Head assigns them to you. Use the Call Queue for due callbacks or ask your manager to assign unowned leads.</div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-bold d-flex justify-content-between align-items-center">
                <span>Assigned Applicants</span>
                <a href="{{ route('admission.applicants.index', ['per_page' => 20]) }}" class="btn btn-sm btn-outline-primary py-0">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Applicant</th><th>Status</th><th>Program</th><th>Next Action</th><th></th></tr></thead>
                    <tbody>
                    @forelse($assignedApplicants as $applicant)
                        <tr>
                            <td class="fw-semibold">{{ $applicant->user->name ?? $applicant->application_number }}</td>
                            <td><span class="{{ $applicant->status_badge }}">{{ $applicant->status_label }}</span></td>
                            <td>{{ $applicant->program->name ?? 'Program not assigned' }}</td>
                            <td class="small">{{ $applicant->next_action ?: 'Next action not set' }}</td>
                            <td><a class="btn btn-sm btn-outline-primary" href="{{ route('admission.applicants.show', $applicant) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-3">
                                <div class="fw-semibold text-dark">No assigned applicants in your queue</div>
                                <div class="small text-muted">Applicant records appear here after ownership is assigned. Check the Workbench for unassigned applicants, document blockers, or payment queues if your team expects new work.</div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent fw-bold d-flex justify-content-between align-items-center">
                <span>Due Reminders</span>
                <a href="{{ route('admission.reminders.index') }}" class="btn btn-sm btn-outline-primary py-0">All</a>
            </div>
            <div class="list-group list-group-flush">
                @forelse($reminders as $reminder)
                    <div class="list-group-item d-flex justify-content-between gap-3">
                        <div>
                            <div class="fw-semibold">{{ ucfirst(str_replace('_', ' ', $reminder->reason)) }}</div>
                            <div class="small text-muted">{{ class_basename($reminder->subject_type) }} #{{ $reminder->subject_id }} - {{ optional($reminder->due_at)->format('d M Y H:i') }}</div>
                        </div>
                        <form method="POST" action="{{ route('admission.reminders.send', $reminder) }}">@csrf<button class="btn btn-sm btn-outline-success">Send</button></form>
                    </div>
                @empty
                    <div class="list-group-item py-3">
                        <div class="fw-semibold text-dark">No reminders are due now</div>
                        <div class="small text-muted">Scheduled reminder work is clear for your current scope. New reminders appear after callbacks, document blockers, payments, sessions, or offer deadlines are scheduled.</div>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-bold">Immediate Attention</div>
            <div class="list-group list-group-flush">
                @forelse(collect($attentionQueues)->flatten(1)->take(10) as $item)
                    <a class="list-group-item list-group-item-action" href="{{ $item['route'] }}">
                        <span class="badge bg-{{ $item['severity'] }} me-2">{{ $item['reason'] }}</span>
                        <span class="fw-semibold">{{ $item['title'] }}</span>
                        <div class="small text-muted">{{ $item['recommended_action'] }}</div>
                    </a>
                @empty
                    <div class="list-group-item py-3">
                        <div class="fw-semibold text-dark">No immediate attention items</div>
                        <div class="small text-muted">There are no overdue, blocked, stale, or high-priority items in your visible scope right now. Continue from Call Queue, Reminders, or My Day Desk.</div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
</div>
@endsection
