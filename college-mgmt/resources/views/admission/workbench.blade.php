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
                ['label' => 'Workload', 'count' => $kpiSummary['workload'] ?? 0, 'icon' => 'briefcase', 'url' => route('admission.leads.index', request()->only(['program_id', 'counsellor_id', 'priority'])), 'hint' => 'Open matching lead workload'],
                ['label' => 'SLA Breaches', 'count' => $kpiSummary['sla_breaches'] ?? 0, 'icon' => 'clock-history', 'url' => route('admission.attention.index', ['queue' => 'sla_breaches'] + request()->only(['program_id', 'counsellor_id', 'priority'])), 'hint' => 'Open breached SLA queue'],
                ['label' => 'Lead Conv. %', 'count' => ($kpiSummary['application_conversion_pct'] ?? 0) . '%', 'icon' => 'graph-up', 'url' => route('admission.reports.index'), 'hint' => 'Open conversion reports'],
                ['label' => 'Follow-up %', 'count' => ($kpiSummary['followup_compliance_pct'] ?? 0) . '%', 'icon' => 'telephone-outbound', 'url' => route('admission.reminders.index', request()->only(['program_id', 'counsellor_id'])), 'hint' => 'Open follow-up reminders'],
            ] as $card)
            <div class="col-sm-6 col-xl-3">
                <a href="{{ $card['url'] }}" class="text-decoration-none text-reset">
                    <div class="card h-100 border-primary-subtle">
                        <div class="card-body">
                            <div class="d-flex justify-content-between gap-2">
                                <div>
                                    <div class="text-muted small">{{ $card['label'] }}</div>
                                    <div class="display-6 fw-semibold">{{ $card['count'] }}</div>
                                    <div class="small text-muted">{{ $card['hint'] }}</div>
                                </div>
                                <i class="bi bi-{{ $card['icon'] }} fs-3 text-primary"></i>
                            </div>
                        </div>
                    </div>
                </a>
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
                        <a href="{{ route('admission.attention.index', ['queue' => $queueName] + request()->only(['program_id', 'counsellor_id', 'priority'])) }}" class="text-decoration-none">
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
                                        <div class="fw-semibold">{{ $lead->name ?: 'Lead name missing' }}</div>
                                        <div class="text-muted small">{{ $lead->email ?: ($lead->phone ?: 'No email or phone recorded') }}</div>
                                    </td>
                                    <td>{{ $lead->program?->name ?? 'Program not selected' }}</td>
                                    <td>{{ $lead->assignedTo?->name ?? 'Unassigned' }}</td>
                                    <td>{{ $lead->next_action ?? 'Next action not set' }}</td>
                                    <td><a href="{{ route('admission.leads.show', $lead) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <div class="fw-semibold text-body mb-1">No leads match this workbench scope.</div>
                                        <div class="small mb-3">Clear filters, review unassigned leads, or confirm whether new enquiries are entering through web forms, walk-ins, partner submissions, or imports.</div>
                                        <div class="d-flex justify-content-center flex-wrap gap-2">
                                            <a href="{{ route('admission.workbench') }}" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
                                            <a href="{{ route('admission.leads.index', ['assigned' => 'unassigned']) }}" class="btn btn-sm btn-outline-primary">Unassigned Leads</a>
                                            <a href="{{ route('admission.walk-ins.index') }}" class="btn btn-sm btn-outline-primary">Walk-ins</a>
                                        </div>
                                    </td>
                                </tr>
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
                                        <div class="fw-semibold">{{ $applicant->user?->name ?? 'Applicant name missing' }}</div>
                                        <div class="text-muted small">{{ $applicant->application_number ?? 'Application number missing' }}</div>
                                    </td>
                                    <td>{{ $applicant->program?->name ?? 'Program not assigned' }}</td>
                                    <td>{{ ucfirst($applicant->priority ?? 'normal') }}</td>
                                    <td>{{ $applicant->next_action ?? 'Create enrollment confirmation' }}</td>
                                    <td><a href="{{ route('admission.enrollment.create', $applicant) }}" class="btn btn-sm btn-success">Enroll</a></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <div class="fw-semibold text-body mb-1">No enrollment-ready applicants in this scope.</div>
                                        <div class="small mb-3">Applicants appear here only after selection, required documents, payment readiness, offer acceptance, and enrollment blockers are clear.</div>
                                        <a href="{{ route('admission.applicants.index', ['status' => 'selected']) }}" class="btn btn-sm btn-outline-primary">Open Selected Applicants</a>
                                    </td>
                                </tr>
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
                            <div class="fw-semibold">{{ $document->applicant?->user?->name ?? 'Applicant name missing' }}</div>
                            <div class="small text-muted">{{ $document->requiredDocument?->name ?? 'Document requirement not linked' }}</div>
                        </a>
                    @empty
                        <div class="list-group-item text-muted small">
                            <div class="fw-semibold text-body mb-1">No pending documents in this scope.</div>
                            Document blockers appear here after applicants upload files that need staff verification.
                            <div class="mt-2"><a href="{{ route('admission.documents.queue') }}" class="btn btn-sm btn-outline-primary">Open Document Queue</a></div>
                        </div>
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
                            <div class="fw-semibold">{{ $payment->applicant?->user?->name ?? 'Applicant name missing' }} - {{ $payment->formatted_amount ?? 'Amount not recorded' }}</div>
                            <div class="small text-muted">{{ $payment->installment?->name ?? 'Installment not linked' }} {{ $payment->gateway_status ? '(' . $payment->gateway_status . ')' : '' }}</div>
                        </a>
                    @empty
                        <div class="list-group-item text-muted small">
                            <div class="fw-semibold text-body mb-1">No pending payments in this scope.</div>
                            Payment proof and gateway-review items appear here after applicants submit payable admission milestones.
                            <div class="mt-2"><a href="{{ route('admission.payments.queue') }}" class="btn btn-sm btn-outline-primary">Open Payment Queue</a></div>
                        </div>
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
                            <div class="fw-semibold">{{ $session->session_name ?: 'Session name missing' }}</div>
                            <div class="small text-muted">{{ $session->program?->name ?? 'Program not linked' }} at {{ $session->start_time ?? 'Time not announced' }}</div>
                        </a>
                    @empty
                        <div class="list-group-item text-muted small">
                            <div class="fw-semibold text-body mb-1">No assessment sessions scheduled today.</div>
                            Today's assessment sessions appear after panel, slot, and candidate assignment are published.
                            <div class="mt-2"><a href="{{ route('admission.assessment-control-room.index') }}" class="btn btn-sm btn-outline-primary">Assessment Control Room</a></div>
                        </div>
                    @endforelse
                    @foreach($offerExpiryRisk->take(5) as $offer)
                        <a class="list-group-item list-group-item-action text-warning" href="{{ route('admission.offer-letters.show', $offer) }}">
                            {{ $offer->applicant?->user?->name ?? 'Applicant name missing' }} offer expires {{ $offer->acceptance_deadline?->format('d M Y') ?? 'Deadline not set' }}
                        </a>
                    @endforeach
                    @if($offerExpiryRisk->isEmpty())
                        <div class="list-group-item text-muted small">
                            <div class="fw-semibold text-body mb-1">No offer expiry risk in the next 3 days.</div>
                            Offer-risk items appear after issued offers have acceptance deadlines near expiry.
                            <div class="mt-2"><a href="{{ route('admission.offer-rounds.index') }}" class="btn btn-sm btn-outline-primary">Offer And Seat Control</a></div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
