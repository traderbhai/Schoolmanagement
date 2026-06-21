@extends('layouts.admin')

@section('title', 'Counsellor Desk')

@push('styles')
<style>
    .desk .card { border-radius:6px; }
    .desk .card-body { padding:.75rem; }
    .desk .list-group-item { padding:.55rem .75rem; }
    .desk .metric-link { color:inherit; text-decoration:none; }
    @media (max-width:575.98px){ .desk .btn { white-space:normal; } }
</style>
@endpush

@section('content')
<div class="desk">
<x-ui.page-header
    title="Counsellor Operating Desk"
    subtitle="Start with the highest-priority call, clear applicant blockers, then close reminders and parent follow-ups."
    action-label="Start Calling"
    :action-route="route('admission.calling-desk.index')"
    action-icon="bi-telephone-outbound"
/>

<div class="alert alert-primary border-0 shadow-sm d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 py-3">
    <div class="d-flex gap-3">
        <div class="ui-kpi-tile-icon bg-white text-primary"><i class="bi bi-compass"></i></div>
        <div>
            <div class="fw-bold">Recommended workflow for today</div>
            <div class="small">1. Start Calling &nbsp; 2. Resolve applicant blockers &nbsp; 3. Send due reminders &nbsp; 4. Use playbooks for objections and parents.</div>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admission.calling-desk.index') }}" class="btn btn-primary btn-sm">Start Calling</a>
        <a href="{{ route('admission.reminders.index') }}" class="btn btn-outline-primary btn-sm">Due Reminders</a>
        <a href="{{ route('admission.counsellor-playbooks.index') }}" class="btn btn-outline-primary btn-sm">Playbooks</a>
    </div>
</div>

@php
    $statLinks = [
        'next_calls' => route('admission.calling-desk.index'),
        'applicant_blockers' => route('admission.applicants.index', ['status' => 'under_review']),
        'assessment_followups' => route('admission.assessment-control-room.index'),
        'due_reminders' => route('admission.reminders.index'),
    ];
@endphp
<div class="row g-2 mb-3">
@foreach($desk['stats'] as $label => $value)
    <div class="col-6 col-lg-3"><a class="metric-link" href="{{ $statLinks[$label] ?? route('admission.counsellor-desk.index') }}"><div class="card border-0 shadow-sm"><div class="card-body"><div class="d-flex justify-content-between"><div class="small text-muted">{{ ucfirst(str_replace('_', ' ', $label)) }}</div><i class="bi bi-arrow-up-right small text-muted"></i></div><div class="fs-4 fw-bold">{{ $value }}</div><div class="small text-muted mt-1">Open matching work queue</div></div></div></a></div>
@endforeach
</div>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center"><span class="fw-bold">Next Best Calls</span><span class="small text-muted">Call top to bottom</span></div>
            <div class="list-group list-group-flush">
                @forelse($desk['nextBestCalls'] as $lead)
                    <a class="list-group-item list-group-item-action d-flex justify-content-between gap-2" href="{{ route('admission.leads.show', $lead) }}">
                        <span>
                            <strong>{{ $lead->name }}</strong>
                            <div class="small text-muted">
                                {{ $lead->phone ?: 'Phone not recorded' }} |
                                {{ $lead->program?->name ?? 'Program not assigned' }} |
                                {{ $lead->next_action ?: 'Next action not set' }}
                            </div>
                        </span>
                        <span class="badge bg-{{ in_array($lead->priority, ['urgent','high']) ? 'danger' : 'secondary' }}">{{ ucfirst($lead->priority ?? 'normal') }}</span>
                    </a>
                @empty
                    <div class="list-group-item text-muted">
                        <div class="fw-semibold text-dark">No calls due in your scope</div>
                        <div class="small">Assigned callbacks, hot leads, and no-response follow-ups are clear. Use Calling Desk if you need to pull the next eligible record.</div>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center"><span class="fw-bold">Applicant Blockers</span><span class="small text-muted">Fix before enrollment</span></div>
            <div class="list-group list-group-flush">
                @forelse($desk['applicantBlockers'] as $applicant)
                    <a class="list-group-item list-group-item-action" href="{{ route('admission.applicants.show', $applicant) }}">
                        <strong>{{ $applicant->user?->name ?? $applicant->application_number }}</strong>
                        <div class="small text-muted">
                            {{ $applicant->status_label }} |
                            {{ $applicant->program?->name ?? 'Program not assigned' }} |
                            {{ $applicant->next_action ?: 'Next action not set' }}
                        </div>
                    </a>
                @empty
                    <div class="list-group-item text-muted">
                        <div class="fw-semibold text-dark">No applicant blockers in your scope</div>
                        <div class="small">Document, payment, and review blockers appear here after assigned applicants need counsellor follow-up.</div>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center"><span class="fw-bold">Assessment Follow-ups</span><span class="small text-muted">Confirm attendance and scores</span></div>
            <div class="list-group list-group-flush">
                @forelse($desk['assessmentFollowups'] as $assignment)
                    <a class="list-group-item list-group-item-action" href="{{ route('admission.assessment-control-room.index') }}">
                        <strong>{{ $assignment->applicant?->user?->name ?? $assignment->applicant?->application_number ?? 'Applicant not linked' }}</strong>
                        <div class="small text-muted">{{ $assignment->panel?->name ?? 'Panel not assigned' }} | {{ ucwords(str_replace('_', ' ', $assignment->lifecycle_status)) }}</div>
                    </a>
                @empty
                    <div class="list-group-item text-muted">
                        <div class="fw-semibold text-dark">No assessment follow-ups in your scope</div>
                        <div class="small">Invited, rescheduled, and no-show candidates appear here when your assigned applicants need assessment follow-up.</div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center"><span class="fw-bold">Due Reminders</span><span class="small text-muted">Queue messages, do not send manually</span></div>
            <div class="list-group list-group-flush">
                @forelse($desk['reminders'] as $reminder)
                    <div class="list-group-item d-flex justify-content-between gap-2">
                        <span><strong>{{ ucfirst(str_replace('_', ' ', $reminder->reason)) }}</strong><div class="small text-muted">{{ class_basename($reminder->subject_type) }} #{{ $reminder->subject_id }} | {{ optional($reminder->due_at)->format('d M H:i') }}</div></span>
                        <form method="POST" action="{{ route('admission.reminders.send', $reminder) }}" onsubmit="return confirm('Queue this reminder through the communication hub?')">@csrf<button class="btn btn-sm btn-outline-success">Send</button></form>
                    </div>
                @empty
                    <div class="list-group-item text-muted">No reminders due.</div>
                @endforelse
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center"><span class="fw-bold">Conversation Timeline</span><span class="small text-muted">Recent calls, reminders, notes</span></div>
            <div class="list-group list-group-flush">
                @forelse($desk['timeline'] as $event)
                    <div class="list-group-item small"><i class="bi bi-{{ $event['icon'] }} me-1"></i><strong>{{ $event['title'] }}</strong><div class="text-muted">{{ Str::limit($event['body'], 90) }}</div><div class="text-muted">{{ optional($event['at'])->diffForHumans() }}</div></div>
                @empty
                    <div class="list-group-item text-muted">No recent conversation events.</div>
                @endforelse
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center"><span class="fw-bold">Playbooks</span><span class="small text-muted">Use during objections</span></div>
            <div class="list-group list-group-flush">
                @forelse($desk['playbooks'] as $playbook)
                    <div class="list-group-item">
                        <strong>{{ $playbook->name }}</strong>
                        @foreach($playbook->steps->take(3) as $step)
                            <div class="small text-muted">{{ $step->sort_order }}. {{ $step->title }} - {{ $step->suggested_action }}</div>
                        @endforeach
                    </div>
                @empty
                    <div class="list-group-item text-muted">No playbooks available.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
</div>
@endsection
