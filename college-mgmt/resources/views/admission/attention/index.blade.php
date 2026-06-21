@extends('layouts.admin')
@section('title', 'Admission Attention Queues')
@section('content')
@php
    $queueLabels = [
        'unassigned_hot_leads' => 'Unassigned Hot Leads',
        'sla_breaches' => 'SLA Breaches',
        'stale_leads' => 'Stale Leads',
        'pending_manager_delegation' => 'Pending Manager Delegation',
        'duplicates' => 'Duplicate Review',
        'pending_documents' => 'Pending Documents',
        'pending_payments' => 'Pending Payments',
        'sessions_today' => 'Sessions Today',
        'offer_expiry_risk' => 'Offer Expiry Risk',
        'enrollment_ready' => 'Enrollment Ready',
    ];
    $queueGuidance = [
        'unassigned_hot_leads' => ['No unassigned hot leads match this scope.', 'Clear filters, open all visible leads, or review assignment rules to confirm new high-priority enquiries are being routed.', route('admission.leads.index', ['assigned' => 'unassigned']), 'Open Unassigned Leads'],
        'sla_breaches' => ['No SLA breaches match this scope.', 'The visible lead queue is clear for overdue SLA commitments. Use Assignment Rules or Calling Desk if SLA pressure returns.', route('admission.assignment-rules.index'), 'Review Assignment Rules'],
        'stale_leads' => ['No stale leads match this scope.', 'Leads appear here when no recent activity is logged. Open the Calling Desk to continue regular outreach.', route('admission.calling-desk.index'), 'Open Calling Desk'],
        'pending_manager_delegation' => ['No pending manager delegation items match this scope.', 'Ownership is assigned to active handlers or hidden by your current filters. Review the workbench for team workload.', route('admission.workbench'), 'Open Workbench'],
        'duplicates' => ['No duplicate-review items match this scope.', 'Potential duplicate leads appear here when email or phone records overlap. Use the lead list search if a candidate reports duplicate contact.', route('admission.leads.index'), 'Search Leads'],
        'pending_documents' => ['No pending document blockers match this scope.', 'Uploaded documents needing verification or correction will appear here. Open the document queue for the full source list.', route('admission.documents.queue'), 'Open Document Queue'],
        'pending_payments' => ['No pending payment blockers match this scope.', 'Payment proofs and rejected payment items appear here when admission fee milestones need staff action.', route('admission.payments.queue'), 'Open Payment Queue'],
        'sessions_today' => ['No assessment sessions are scheduled today in this scope.', 'Use Assessment Control Room to review upcoming panels, attendance, scores, and no-show follow-ups.', route('admission.assessment-control-room.index'), 'Open Assessment Control Room'],
        'offer_expiry_risk' => ['No issued offers are expiring in the next 3 days in this scope.', 'Offer follow-ups appear here when acceptance deadlines approach. Review offer rounds and seat holds if needed.', route('admission.offer-rounds.index'), 'Open Offers And Seats'],
        'enrollment_ready' => ['No applicants are enrollment-ready in this scope.', 'Applicants appear here after selection, verified documents, payment readiness, offer acceptance, and enrollment blockers are clear.', route('admission.applicants.index', ['status' => 'selected']), 'Open Selected Applicants'],
    ];
    $activeFilters = collect($filters ?? [])->filter(fn ($value) => filled($value));
@endphp
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start mb-3">
        <div>
            <h1 class="h4 mb-1">{{ $selectedQueue ? ($queueLabels[$selectedQueue] ?? ucwords(str_replace('_', ' ', $selectedQueue))) : 'Admission Attention Queues' }}</h1>
            <div class="text-muted">Role-scoped admission work needing immediate action. Each item opens the source record that caused the queue count.</div>
        </div>
        <div class="d-flex gap-2">
            @if($selectedQueue)
                <a href="{{ route('admission.attention.index', $filters ?? []) }}" class="btn btn-outline-secondary btn-sm">Show All Queues</a>
            @endif
            <a href="{{ route('admission.workbench') }}" class="btn btn-outline-primary btn-sm">Open Workbench</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
            <div class="row g-3 align-items-center">
                <div class="col-lg-4">
                    <div class="small text-muted text-uppercase">Displayed total</div>
                    <div class="fs-5 fw-semibold">{{ collect($queues)->sum(fn ($items) => count($items)) }} item(s)</div>
                </div>
                <div class="col-lg-8">
                    <div class="small text-muted text-uppercase">Active source filters</div>
                    <div class="d-flex flex-wrap gap-2 mt-1">
                        @if($selectedQueue)
                            <span class="badge text-bg-primary">Queue: {{ $queueLabels[$selectedQueue] ?? ucwords(str_replace('_', ' ', $selectedQueue)) }}</span>
                        @endif
                        @forelse($activeFilters as $name => $value)
                            <span class="badge text-bg-light border">{{ ucwords(str_replace('_', ' ', $name)) }}: {{ $value }}</span>
                        @empty
                            <span class="text-muted">All visible Admission records in your role scope.</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        @foreach($queues as $key => $items)
            <div class="col-xl-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">{{ $queueLabels[$key] ?? ucwords(str_replace('_', ' ', $key)) }}</span>
                        <span class="badge bg-primary">{{ count($items) }}</span>
                    </div>
                    <div class="list-group list-group-flush">
                        @forelse($items as $item)
                            <a class="list-group-item list-group-item-action" href="{{ $item['route'] }}">
                                <div class="d-flex justify-content-between gap-2">
                                    <strong>{{ $item['title'] ?: 'Admission record' }}</strong>
                                    <span class="badge bg-{{ $item['severity'] ?? 'secondary' }}">{{ $item['severity'] ?? 'info' }}</span>
                                </div>
                                <div class="small text-muted">{{ $item['reason'] ?? 'Needs staff review' }} - {{ $item['recommended_action'] ?? 'Open the source record and resolve the blocker.' }}</div>
                                <div class="small text-muted mt-1">
                                    Owner: {{ $item['owner'] ?? 'Unassigned' }}
                                    @if(!empty($item['due_at']))
                                        @php($dueAt = $item['due_at'] instanceof \DateTimeInterface ? $item['due_at']->format('d M Y') : $item['due_at'])
                                        <span class="mx-1">|</span> Due: {{ $dueAt }}
                                    @endif
                                </div>
                            </a>
                        @empty
                            @php($guidance = $queueGuidance[$key] ?? ['No items match this queue.', 'Clear filters or return to the workbench to review the full Admission operating surface.', route('admission.workbench'), 'Open Workbench'])
                            <div class="list-group-item">
                                <div class="fw-semibold">{{ $guidance[0] }}</div>
                                <div class="small text-muted mb-2">{{ $guidance[1] }}</div>
                                <a href="{{ $guidance[2] }}" class="btn btn-outline-primary btn-sm">{{ $guidance[3] }}</a>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
