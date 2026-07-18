@extends('layouts.admin')

@section('title', 'Admission Reminders')

@push('styles')
<style>
    .admission-compact .card { border-radius: 6px; }
    .admission-compact .card-body { padding: .75rem; }
    .admission-compact .table > :not(caption) > * > * { padding: .45rem .6rem; }
</style>
@endpush

@section('content')
<div class="admission-compact">
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h3 class="fw-bold mb-1">Reminder And Cadence Engine</h3><div class="text-muted small">{{ $reminders->total() }} reminders after filters. Scheduled reminders create queued communication logs; send, complete, and pause actions are audited.</div></div>
</div>

<div class="alert alert-info border-0 shadow-sm small mb-3">
    <div class="fw-semibold mb-1">Reminder operating workflow</div>
    <div class="d-flex flex-wrap gap-2 mb-2">
        <span class="badge text-bg-light border">1. Filter due reminders</span>
        <span class="badge text-bg-light border">2. Open the lead or applicant context</span>
        <span class="badge text-bg-light border">3. Queue approved communication</span>
        <span class="badge text-bg-light border">4. Complete only after action is recorded</span>
        <span class="badge text-bg-light border">5. Pause cadences with a reason</span>
    </div>
    <div class="text-muted">This queue is scoped to your Admission hierarchy. Actions here update communication logs, staff work queues, and the related lead or applicant timeline.</div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label small mb-1">Status</label><select aria-label="Status" name="status" class="form-select form-select-sm"><option value="">All Status</option>@foreach(['scheduled','queued','paused','escalated'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label small mb-1">Reason</label><input aria-label="document_blocker" name="reason" value="{{ request('reason') }}" class="form-control form-control-sm" placeholder="document_blocker"></div>
            <div class="col-md-2"><label class="form-label small mb-1">Due Date</label><input aria-label="Date" type="date" name="date" value="{{ request('date') }}" class="form-control form-control-sm"></div>
            <div class="col-md-2"><label class="form-label small mb-1">Rows</label><select aria-label="Per Page" name="per_page" class="form-select form-select-sm">@foreach([10,25,50,100] as $size)<option value="{{ $size }}" @selected(request('per_page', 25) == $size)>{{ $size }}</option>@endforeach</select></div>
            <div class="col-md-2 d-flex gap-1"><button class="btn btn-primary btn-sm flex-fill">Apply filters</button><a href="{{ route('admission.reminders.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a></div>
        </form>
        @if(request()->hasAny(['status', 'reason', 'date']))
            <div class="small text-muted mt-2">
                Active filters:
                @if(request('status')) <span class="badge text-bg-light border">Status: {{ ucfirst(request('status')) }}</span> @endif
                @if(request('reason')) <span class="badge text-bg-light border">Reason: {{ str_replace('_', ' ', request('reason')) }}</span> @endif
                @if(request('date')) <span class="badge text-bg-light border">Due: {{ request('date') }}</span> @endif
            </div>
        @endif
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-bold">Reminder Queue</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th scope="col">Target</th><th scope="col">Reason</th><th scope="col">Channel</th><th scope="col">Due</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
                    <tbody>
                    @foreach($reminders as $reminder)
                        @php
                            $subject = $reminder->subject;
                            $targetName = 'Record not linked';
                            $targetRoute = null;

                            if ($subject instanceof \App\Models\Lead) {
                                $targetName = $subject->name ?: 'Lead name missing';
                                $targetRoute = route('admission.leads.show', $subject);
                            } elseif ($subject instanceof \App\Models\Applicant) {
                                $targetName = $subject->user?->name ?: ($subject->name ?? 'Applicant name missing');
                                $targetRoute = route('admission.applicants.show', $subject);
                            }
                        @endphp
                        <tr>
                            <td>
                                @if($targetRoute)
                                    <a href="{{ $targetRoute }}" class="fw-semibold text-decoration-none">{{ $targetName }}</a>
                                @else
                                    <span class="fw-semibold text-muted">{{ $targetName }}</span>
                                @endif
                                <div class="small text-muted">{{ class_basename($reminder->subject_type) }} #{{ $reminder->subject_id }}</div>
                            </td>
                            <td>
                                {{ ucfirst(str_replace('_', ' ', $reminder->reason ?: 'follow up')) }}
                                <div class="small text-muted">{{ $reminder->notes ?: 'No reminder notes recorded' }}</div>
                            </td>
                            <td>{{ strtoupper($reminder->channel ?: 'internal') }}</td>
                            <td>{{ optional($reminder->due_at)->format('d M Y H:i') ?? 'Due date not set' }}</td>
                            <td><span class="badge bg-secondary">{{ ucfirst($reminder->status) }}</span></td>
                            <td class="d-flex gap-1 flex-wrap">
                <form method="POST" action="{{ route('admission.reminders.send', $reminder) }}" onsubmit="return confirm('Queue this reminder through the communication hub? Confirm recipient, channel, approved template, consent state, and provider readiness before sending.')">@csrf<button class="btn btn-sm btn-success">Send reminder</button></form>
                                <form method="POST" action="{{ route('admission.reminders.complete', $reminder) }}" onsubmit="return confirm('Mark this reminder as completed after the student or lead action has been recorded? Confirm the follow-up outcome, next action, and audit trail before closing it.')">@csrf<button class="btn btn-sm btn-outline-secondary">Done</button></form>
                                <form method="POST" action="{{ route('admission.reminders.pause', $reminder) }}" onsubmit="return confirm('Pause this reminder cadence for this record? Confirm reason, next follow-up owner, delayed communication impact, and applicant/lead risk before pausing.')">@csrf<button class="btn btn-sm btn-link text-warning text-decoration-none px-1">Pause</button></form>
                            </td>
                        </tr>
                    @endforeach
                    @if($reminders->isEmpty())
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <div class="fw-semibold text-body mb-1">No reminders match this scoped queue.</div>
                                <div class="small mb-3">Clear filters, review the follow-up calendar, or create a reminder from a lead, applicant, Calling Desk, or document/payment blocker workflow.</div>
                                <div class="d-flex justify-content-center flex-wrap gap-2">
                                    <a href="{{ route('admission.reminders.index') }}" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
                                    <a href="{{ route('admission.leads.follow-ups.calendar') }}" class="btn btn-sm btn-outline-primary">Follow-up Calendar</a>
                                    <a href="{{ route('admission.calling-desk.index') }}" class="btn btn-sm btn-outline-primary">Calling Desk</a>
                                </div>
                            </td>
                        </tr>
                    @endif
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2 py-2">
                <div class="small text-muted">Showing {{ $reminders->firstItem() ?? 0 }}-{{ $reminders->lastItem() ?? 0 }} of {{ $reminders->total() }}</div>
                {{ $reminders->links() }}
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent fw-bold">Create Cadence Rule</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admission.reminders.cadence') }}" class="vstack gap-2" onsubmit="return confirm('Create this reminder cadence rule for future matching records? Confirm target type, reason, channel, template, timing, and duplicate-send controls before activation.')">
                    @csrf
                    <input aria-label="Rule name" class="form-control form-control-sm" name="name" placeholder="Rule name" required>
                    <select aria-label="Target Type" class="form-select form-select-sm" name="target_type"><option value="lead">Lead</option><option value="applicant">Applicant</option></select>
                    <input aria-label="Reason" class="form-control form-control-sm" name="reason" value="no_response_follow_up" required>
                    <select aria-label="Channel" class="form-select form-select-sm" name="channel"><option value="email">Email</option><option value="sms">SMS</option><option value="whatsapp">WhatsApp</option><option value="internal">Internal</option></select>
                    <select aria-label="Template" class="form-select form-select-sm" name="template_id"><option value="">Template</option>@foreach($templates as $template)<option value="{{ $template->id }}">{{ $template->name }}</option>@endforeach</select>
                    <div class="row g-2">
                        <div class="col"><label class="form-label small mb-1">First delay hours</label><input aria-label="Initial Delay Hours" class="form-control form-control-sm" name="initial_delay_hours" type="number" value="24"></div>
                        <div class="col"><label class="form-label small mb-1">Repeat interval hours</label><input aria-label="Interval Hours" class="form-control form-control-sm" name="interval_hours" type="number" value="24"></div>
                    </div>
                    <button class="btn btn-primary btn-sm">Save Cadence</button>
                </form>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-bold">Active Cadences</div>
            <div class="list-group list-group-flush">
                @foreach($cadenceRules as $rule)
                    <div class="list-group-item"><div class="fw-semibold">{{ $rule->name }}</div><div class="small text-muted">{{ $rule->target_type }} - {{ $rule->reason }} - {{ $rule->channel }}</div></div>
                @endforeach
                @if($cadenceRules->isEmpty())
                    <div class="list-group-item text-muted small">
                        <div class="fw-semibold text-body mb-1">No cadence rules are active yet.</div>
                        Create a cadence when a repeated reminder pattern is approved, such as document blockers, payment follow-ups, assessment confirmations, or no-response retries.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
</div>
@endsection
