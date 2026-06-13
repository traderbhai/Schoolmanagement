@extends('layouts.admin')

@section('title', 'Counsellor Workspace')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="fw-bold mb-1">Counsellor Workspace</h3>
        <div class="text-muted small">Assigned work, follow-ups, reminders, calls, and blockers.</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admission.reminders.index') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-bell me-1"></i>Reminders</a>
        <a href="{{ route('admission.call-queue.index') }}" class="btn btn-primary btn-sm"><i class="bi bi-telephone me-1"></i>Call Queue</a>
    </div>
</div>

<div class="row g-3 mb-4">
    @foreach([
        ['Workload', $kpi['workload'] ?? 0, 'primary'],
        ['SLA Breaches', $kpi['sla_breaches'] ?? 0, 'danger'],
        ['Stale Leads', $kpi['stale_leads'] ?? 0, 'warning'],
        ['Calls Today', $callProductivity['calls_completed'] ?? 0, 'success'],
    ] as [$label, $value, $color])
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">{{ $label }}</div>
                <div class="fs-3 fw-bold text-{{ $color }}">{{ $value }}</div>
            </div></div>
        </div>
    @endforeach
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent fw-bold">Assigned Leads</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Name</th><th>Program</th><th>Priority</th><th>Next Action</th><th></th></tr></thead>
                    <tbody>
                    @foreach($assignedLeads as $lead)
                        <tr>
                            <td class="fw-semibold">{{ $lead->name }}</td>
                            <td>{{ $lead->program->name ?? 'N/A' }}</td>
                            <td><span class="badge bg-{{ in_array($lead->priority, ['urgent','high']) ? 'danger' : 'secondary' }}">{{ ucfirst($lead->priority ?? 'normal') }}</span></td>
                            <td class="small">{{ $lead->next_action }}</td>
                            <td><a class="btn btn-sm btn-outline-primary" href="{{ route('admission.leads.show', $lead) }}">Open</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-bold">Assigned Applicants</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Applicant</th><th>Status</th><th>Program</th><th>Next Action</th><th></th></tr></thead>
                    <tbody>
                    @foreach($assignedApplicants as $applicant)
                        <tr>
                            <td class="fw-semibold">{{ $applicant->user->name ?? $applicant->application_number }}</td>
                            <td><span class="{{ $applicant->status_badge }}">{{ $applicant->status_label }}</span></td>
                            <td>{{ $applicant->program->name ?? 'N/A' }}</td>
                            <td class="small">{{ $applicant->next_action }}</td>
                            <td><a class="btn btn-sm btn-outline-primary" href="{{ route('admission.applicants.show', $applicant) }}">Open</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent fw-bold">Due Reminders</div>
            <div class="list-group list-group-flush">
                @foreach($reminders as $reminder)
                    <div class="list-group-item d-flex justify-content-between gap-3">
                        <div>
                            <div class="fw-semibold">{{ ucfirst(str_replace('_', ' ', $reminder->reason)) }}</div>
                            <div class="small text-muted">{{ class_basename($reminder->subject_type) }} #{{ $reminder->subject_id }} - {{ optional($reminder->due_at)->format('d M Y H:i') }}</div>
                        </div>
                        <form method="POST" action="{{ route('admission.reminders.send', $reminder) }}">@csrf<button class="btn btn-sm btn-outline-success">Send</button></form>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-bold">Immediate Attention</div>
            <div class="list-group list-group-flush">
                @foreach(collect($attentionQueues)->flatten(1)->take(10) as $item)
                    <a class="list-group-item list-group-item-action" href="{{ $item['route'] }}">
                        <span class="badge bg-{{ $item['severity'] }} me-2">{{ $item['reason'] }}</span>
                        <span class="fw-semibold">{{ $item['title'] }}</span>
                        <div class="small text-muted">{{ $item['recommended_action'] }}</div>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
