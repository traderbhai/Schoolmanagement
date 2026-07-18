@extends('layouts.admin')
@section('title', 'Admission Calling Desk')
@section('content')
<div class="container-fluid py-3">
<x-ui.page-header
    title="Admission Calling Desk"
    subtitle="Work one candidate at a time: call, record disposition, script coverage, next action, then move to the next queue item."
    action-label="Counsellor Desk"
    :action-route="route('admission.counsellor-desk.index')"
    action-icon="bi-speedometer2"
/>

<div class="alert alert-info border-0 shadow-sm d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 py-3">
    <div class="d-flex gap-3">
        <div class="ui-kpi-tile-icon bg-white text-info"><i class="bi bi-telephone-forward"></i></div>
        <div>
            <div class="fw-bold">Call sequence</div>
            <div class="small">1. Review profile and last action &nbsp; 2. Follow the script checklist &nbsp; 3. Save disposition/outcome &nbsp; 4. Set retry or next action.</div>
        </div>
    </div>
    <a class="btn btn-outline-info btn-sm" href="{{ route('admission.objection-analytics.index') }}">Review Objections</a>
</div>
@php
    $metricLinks = [
        'attempts_today' => route('admission.counsellor-performance.index'),
        'contact_rate' => route('admission.counsellor-performance.index'),
        'callback_due' => route('admission.reminders.index', ['reason' => 'callback_retry']),
        'parent_due' => route('admission.parent-journeys.index'),
    ];
@endphp
<div class="row g-2 mb-3">
@foreach(['attempts_today' => $attempts_today, 'contact_rate' => $contact_rate . '%', 'callback_due' => $callback_due, 'parent_due' => $parent_due] as $label => $value)
    <div class="col-6 col-lg-3"><a class="text-decoration-none text-dark" href="{{ $metricLinks[$label] ?? route('admission.calling-desk.index') }}"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="d-flex justify-content-between"><div class="small text-muted">{{ ucfirst(str_replace('_', ' ', $label)) }}</div><i class="bi bi-arrow-up-right small text-muted"></i></div><div class="fs-4 fw-bold">{{ $value }}</div></div></div></a></div>
@endforeach
</div>
<div class="row g-3">
    <div class="col-xl-7">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-bold">Active Call</span>
                <span class="small text-muted">Save outcome before moving on</span>
            </div>
            <div class="card-body">
                @if($active)
                    @php($isLead = $active instanceof \App\Models\Lead)
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <div>
                            <h5 class="mb-1">{{ $isLead ? $active->name : ($active->user?->name ?? $active->application_number) }}</h5>
                            <div class="small text-muted">
                                {{ $isLead ? ($active->phone ?: 'Phone not recorded') : ($active->personal_data['phone'] ?? 'Phone not recorded') }} |
                                {{ $active->program?->name ?? 'Program not assigned' }} |
                                {{ ucfirst($active->priority ?? 'normal') }}
                            </div>
                            <div class="mt-2">{{ $active->next_action ?: 'Call and confirm next step.' }}</div>
                        </div>
                        <div class="text-end"><span class="{{ $active->status_badge ?? 'badge bg-secondary' }}">{{ $active->status_label ?? ucfirst($active->status) }}</span></div>
                    </div>
                    <div class="alert alert-light border py-2 small mt-3 mb-2">
                        <i class="bi bi-info-circle me-1"></i>
                        Use <strong>Disposition</strong> for call result, <strong>Outcome</strong> for admission intent, and <strong>Next Action</strong> for the next owner-visible task.
                    </div>
                    <form method="POST" action="{{ route('admission.calling-desk.outcome') }}" class="row g-2 mt-3" onsubmit="return confirm('Save this call outcome? Confirm disposition, admission intent, script coverage, retry time, next action, and candidate timeline impact before updating the calling desk.')">
                        @csrf
                        <input type="hidden" name="subject_type" value="{{ $isLead ? 'lead' : 'applicant' }}">
                        <input type="hidden" name="subject_id" value="{{ $active->id }}">
                        <input type="hidden" name="script_template_id" value="{{ $script?->id }}">
                        <div class="col-md-3"><label class="form-label small">Disposition</label><select aria-label="Disposition" name="disposition" class="form-select form-select-sm"><option>connected</option><option>not_reachable</option><option>busy</option><option>wrong_number</option><option>no_answer</option></select></div>
                        <div class="col-md-3"><label class="form-label small">Outcome</label><select aria-label="Outcome" name="outcome" class="form-select form-select-sm"><option value="interested">Interested</option><option value="callback">Callback</option><option value="not_interested">Not Interested</option><option value="parent_pending">Parent Pending</option><option value="escalated">Escalated</option></select></div>
                        <div class="col-md-3"><label class="form-label small">Retry Due</label><input aria-label="Retry Due At" type="datetime-local" name="retry_due_at" class="form-control form-control-sm"></div>
                        <div class="col-md-3"><label class="form-label small">Duration Seconds</label><input aria-label="Duration Seconds" type="number" name="duration_seconds" value="180" class="form-control form-control-sm"></div>
                        @if($script)
                            <div class="col-12">
                                <div class="card bg-light border">
                                    <div class="card-body p-2">
                                        <div class="small fw-bold mb-1"><i class="bi bi-card-checklist me-1"></i>{{ $script->name }}</div>
                                        <div class="row g-1">@foreach($script->steps ?? [] as $idx => $step)<div class="col-md-6 col-xl-4"><label class="form-label small mb-0">{{ $step }}</label><select aria-label="Script Results" name="script_results[]" class="form-select form-select-sm"><option value="covered">Covered</option><option value="missed">Missed</option><option value="na">Not applicable</option></select></div>@endforeach</div>
                                    </div>
                                </div>
                            </div>
                        @endif
                        <div class="col-md-8"><label class="form-label small">Notes</label><input aria-label="Notes" name="notes" class="form-control form-control-sm" value="Discussed program fit, parent decision, and next action."></div>
                        <div class="col-md-4"><label class="form-label small">Next Action</label><input aria-label="Next Action" name="next_action" class="form-control form-control-sm" value="Send checklist and schedule follow-up"></div>
                        <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
                            <button type="submit" class="btn btn-sm btn-primary">Save call outcome</button>
                            <span class="small text-muted">This updates the timeline, script compliance, retry queue, and next action.</span>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('admission.call-attempts.skip') }}" class="mt-2" onsubmit="return confirm('Skip this calling-desk record for now? Confirm the reason, retry ownership, and queue impact before moving to the next candidate.')">
                        @csrf
                        <input type="hidden" name="subject_type" value="{{ $isLead ? 'lead' : 'applicant' }}">
                        <input type="hidden" name="subject_id" value="{{ $active->id }}">
                        <input type="hidden" name="reason" value="Temporarily skipped during calling desk session">
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Skip current call record</button>
                    </form>
                @else
                    <x-ui.empty-state
                        icon="bi-telephone-x"
                        title="No eligible calls in your scope"
                        message="Your assigned callbacks, retries, hot leads, and parent follow-ups are clear for now."
                    />
                @endif
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-bold">Next Call Queue</span>
                <span class="small text-muted">Why each item is next</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0" aria-label="Next call queue">
                    <thead>
                        <tr><th scope="col">Candidate</th><th scope="col">Type</th><th scope="col">Score</th><th scope="col">Recommended action</th><th scope="col" class="text-end">Open</th></tr>
                    </thead>
                    <tbody>
                        @forelse($queue->take(12) as $item)
                            @php($record = $item->record)
                            @php($isLeadRecord = $record instanceof \App\Models\Lead)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $isLeadRecord ? $record->name : ($record->user?->name ?? $record->application_number ?? 'Admission record') }}</div>
                                    <div class="small text-muted">{{ $record->program?->name ?? 'Program not assigned' }}</div>
                                </td>
                                <td>{{ ucfirst(str_replace('_', ' ', $item->type)) }}</td>
                                <td>{{ $item->queue_score }}</td>
                                <td class="small">{{ $item->recommended_action ?: 'Review profile and set next action' }}</td>
                                <td class="text-end">
                                    @if($isLeadRecord)
                                        <a href="{{ route('admission.leads.show', $record) }}" class="btn btn-sm btn-outline-primary">Open record</a>
                                    @else
                                        <a href="{{ route('admission.applicants.show', $record) }}" class="btn btn-sm btn-outline-primary">Open record</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    <div class="fw-semibold text-dark">No eligible next-call records</div>
                                    <div class="small">Your scoped callbacks, no-response retries, hot leads, and parent follow-ups are clear. Check reminders or lead filters if new calls are expected.</div>
                                    <div class="mt-3 d-flex flex-wrap justify-content-center gap-2">
                                        <a href="{{ route('admission.reminders.index', ['reason' => 'callback_retry']) }}" class="btn btn-sm btn-outline-primary">Open Callback Reminders</a>
                                        <a href="{{ route('admission.leads.index') }}" class="btn btn-sm btn-outline-secondary">Open Leads</a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card border-0 shadow-sm"><div class="card-header bg-white d-flex justify-content-between align-items-center"><span class="fw-bold">Recent Objections</span><span class="small text-muted">Use before calling similar leads</span></div><div class="table-responsive"><table class="table table-sm mb-0" aria-label="Recent objections"><thead><tr><th scope="col">Subject</th><th scope="col">Stage</th><th scope="col">Status</th></tr></thead><tbody>@forelse($objections as $objection)<tr><td>@php($subject = $objection->subject)<div class="fw-semibold">{{ $subject instanceof \App\Models\Lead ? $subject->name : ($subject?->user?->name ?? $subject?->application_number ?? 'Admission record') }}</div><div class="small text-muted">{{ $objection->type?->name ?? class_basename($objection->subject_type) }}</div></td><td>{{ $objection->stage }}</td><td>{{ $objection->status }}</td></tr>@empty<tr><td colspan="3" class="text-muted text-center py-3"><div class="fw-semibold text-dark">No objection trends in this scope</div><div class="small">Objection patterns appear after calls are logged with structured objections.</div></td></tr>@endforelse</tbody></table></div></div>
    </div>
</div>
</div>
@endsection
