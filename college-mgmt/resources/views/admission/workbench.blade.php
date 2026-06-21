@extends('layouts.admin')
@section('title', 'Admission Workbench')
@section('content')
<div class="container-fluid py-4">
    <x-ui.page-header
        title="Admission Workbench"
        subtitle="Operational queue board for leads, applicants, documents, payments, sessions, offers, and enrollment readiness."
        action-label="Attention"
        :action-route="route('admission.attention.index')"
        action-icon="bi-exclamation-triangle"
    />

    <div class="alert alert-warning border-0 shadow-sm d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3 py-3 mb-4">
        <div class="d-flex gap-3">
            <div class="ui-kpi-tile-icon bg-white text-warning"><i class="bi bi-list-task"></i></div>
            <div>
                <div class="fw-bold">Workbench operating order</div>
                <div class="small">1. Apply program/counsellor filters &nbsp; 2. Clear overdue follow-ups/unassigned leads &nbsp; 3. Verify documents/payments &nbsp; 4. Move enrollment-ready applicants.</div>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admission.assignment-rules.index') }}" class="btn btn-outline-warning btn-sm">Assignment Rules</a>
            <a href="{{ route('admission.process-templates.index') }}" class="btn btn-outline-warning btn-sm">Process Templates</a>
        </div>
    </div>

    <form method="GET" class="card mb-4">
        <div class="card-body row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Program</label>
                <select name="program_id" class="form-select">
                    <option value="">All programs</option>
                    @foreach($programs as $program)
                        <option value="{{ $program->id }}" @selected($programId == $program->id)>{{ $program->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Counsellor</label>
                <select name="counsellor_id" class="form-select">
                    <option value="">All counsellors</option>
                    @foreach($counsellors as $counsellor)
                        <option value="{{ $counsellor->id }}" @selected($counsellorId == $counsellor->id)>{{ $counsellor->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Priority</label>
                <select name="priority" class="form-select">
                    <option value="">All</option>
                    @foreach(['urgent', 'high', 'normal', 'low'] as $level)
                        <option value="{{ $level }}" @selected($priority === $level)>{{ ucfirst($level) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i> Apply</button>
            </div>
        </div>
    </form>

    <div class="row g-3 mb-4">
        @isset($kpiSummary)
            @foreach([
                ['label' => 'Workload', 'count' => $kpiSummary['workload'] ?? 0, 'icon' => 'briefcase'],
                ['label' => 'SLA Breaches', 'count' => $kpiSummary['sla_breaches'] ?? 0, 'icon' => 'clock-history'],
                ['label' => 'Lead Conv. %', 'count' => ($kpiSummary['application_conversion_pct'] ?? 0) . '%', 'icon' => 'graph-up'],
                ['label' => 'Follow-up %', 'count' => ($kpiSummary['followup_compliance_pct'] ?? 0) . '%', 'icon' => 'telephone-outbound'],
            ] as $card)
            <div class="col-sm-6 col-xl-3">
                <div class="card h-100 border-primary-subtle">
                    <div class="card-body">
                        <div class="text-muted small">{{ $card['label'] }}</div>
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="display-6 fw-semibold">{{ $card['count'] }}</div>
                            <i class="bi bi-{{ $card['icon'] }} fs-3 text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        @endisset
    </div>

    @isset($attentionQueues)
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center"><span class="fw-semibold">Immediate Attention</span><span class="small text-muted">Each card opens the queue that caused the count</span></div>
        <div class="card-body">
            <div class="row g-3">
                @foreach($attentionQueues as $queueName => $items)
                    <div class="col-md-3">
                        <a href="{{ route('admission.attention.index') }}" class="text-decoration-none">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small">{{ ucwords(str_replace('_', ' ', $queueName)) }}</div>
                                <div class="h4 mb-0">{{ count($items) }}</div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endisset

    <div class="row g-3 mb-4">
        @foreach([
            ['label' => 'Overdue Follow-ups', 'count' => $overdueFollowUps->count(), 'icon' => 'alarm'],
            ['label' => 'Unassigned Leads', 'count' => $leads->whereNull('assigned_to')->count(), 'icon' => 'person-plus'],
            ['label' => 'Pending Documents', 'count' => $pendingDocuments->count(), 'icon' => 'folder-check'],
            ['label' => 'Pending Payments', 'count' => $pendingPayments->count(), 'icon' => 'credit-card'],
            ['label' => 'Sessions Today', 'count' => $sessionsToday->count(), 'icon' => 'calendar-event'],
            ['label' => 'Offer Expiry Risk', 'count' => $offerExpiryRisk->count(), 'icon' => 'hourglass-split'],
        ] as $card)
            <div class="col-sm-6 col-xl-2">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ $card['label'] }}</div>
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="display-6 fw-semibold">{{ $card['count'] }}</div>
                            <i class="bi bi-{{ $card['icon'] }} fs-3 text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-xl-6">
            <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center"><span class="fw-semibold">Hot Leads And Assignment</span><span class="small text-muted">Open, assign, or set next action</span></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Lead</th><th>Program</th><th>Owner</th><th>Next Action</th><th></th></tr></thead>
                        <tbody>
                            @forelse($leads->take(12) as $lead)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $lead->name }}</div>
                                        <div class="text-muted small">{{ $lead->email ?: $lead->phone }}</div>
                                    </td>
                                    <td>{{ $lead->program?->name ?? '-' }}</td>
                                    <td>{{ $lead->assignedTo?->name ?? 'Unassigned' }}</td>
                                    <td>{{ $lead->next_action ?? '-' }}</td>
                                    <td><a href="{{ route('admission.leads.show', $lead) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">No leads in scope.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center"><span class="fw-semibold">Enrollment-Ready Applicants</span><span class="small text-muted">Verify blockers before enrolling</span></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Applicant</th><th>Program</th><th>Priority</th><th>Next Action</th><th></th></tr></thead>
                        <tbody>
                            @forelse($enrollmentReady as $applicant)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $applicant->user?->name }}</div>
                                        <div class="text-muted small">{{ $applicant->application_number }}</div>
                                    </td>
                                    <td>{{ $applicant->program?->name }}</td>
                                    <td>{{ ucfirst($applicant->priority ?? 'normal') }}</td>
                                    <td>{{ $applicant->next_action ?? 'Create enrollment confirmation' }}</td>
                                    <td><a href="{{ route('admission.enrollment.create', $applicant) }}" class="btn btn-sm btn-success">Enroll</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">No enrollment-ready applicants found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center"><span class="fw-semibold">Document Queue</span><span class="small text-muted">Preview and verify</span></div>
                <div class="list-group list-group-flush">
                    @forelse($pendingDocuments->take(8) as $document)
                        <a class="list-group-item list-group-item-action" href="{{ route('admission.documents.preview', $document) }}">
                            <div class="fw-semibold">{{ $document->applicant?->user?->name }}</div>
                            <div class="small text-muted">{{ $document->requiredDocument?->name }}</div>
                        </a>
                    @empty
                        <div class="list-group-item text-muted">No pending documents.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center"><span class="fw-semibold">Payment Queue</span><span class="small text-muted">Verify proof or gateway state</span></div>
                <div class="list-group list-group-flush">
                    @forelse($pendingPayments->take(8) as $payment)
                        <a class="list-group-item list-group-item-action" href="{{ route('admission.applicants.payments', $payment->applicant) }}">
                            <div class="fw-semibold">{{ $payment->applicant?->user?->name }} - {{ $payment->formatted_amount }}</div>
                            <div class="small text-muted">{{ $payment->installment?->name }} {{ $payment->gateway_status ? '(' . $payment->gateway_status . ')' : '' }}</div>
                        </a>
                    @empty
                        <div class="list-group-item text-muted">No pending payments.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center"><span class="fw-semibold">Calendar And Offer Risk</span><span class="small text-muted">Sessions today and expiring offers</span></div>
                <div class="list-group list-group-flush">
                    @forelse($sessionsToday as $session)
                        <a class="list-group-item list-group-item-action" href="{{ route('admission.sessions.show', $session) }}">
                            <div class="fw-semibold">{{ $session->session_name }}</div>
                            <div class="small text-muted">{{ $session->program?->name }} at {{ $session->start_time }}</div>
                        </a>
                    @empty
                        <div class="list-group-item text-muted">No sessions today.</div>
                    @endforelse
                    @foreach($offerExpiryRisk->take(5) as $offer)
                        <a class="list-group-item list-group-item-action text-warning" href="{{ route('admission.offer-letters.show', $offer) }}">
                            {{ $offer->applicant?->user?->name }} offer expires {{ $offer->acceptance_deadline?->format('d M Y') }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
