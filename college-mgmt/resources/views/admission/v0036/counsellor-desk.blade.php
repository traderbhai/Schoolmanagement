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
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h3 class="fw-bold mb-1">Counsellor Operating Desk</h3><div class="text-muted small">My Day queues, applicant blockers, conversation timeline, and playbooks.</div></div>
    <div class="d-flex flex-wrap gap-2"><a href="{{ route('admission.call-queue.index') }}" class="btn btn-primary btn-sm">Call Queue</a><a href="{{ route('admission.counsellor-performance.index') }}" class="btn btn-outline-success btn-sm">Performance</a><a href="{{ route('admission.script-compliance.index') }}" class="btn btn-outline-dark btn-sm">Scripts</a><a href="{{ route('admission.objection-analytics.index') }}" class="btn btn-outline-warning btn-sm">Objections</a><a href="{{ route('admission.parent-journeys.index') }}" class="btn btn-outline-info btn-sm">Parents</a><a href="{{ route('admission.counsellor-playbooks.index') }}" class="btn btn-outline-primary btn-sm">Playbooks</a></div>
</div>

<div class="row g-2 mb-3">
@foreach($desk['stats'] as $label => $value)
    <div class="col-6 col-lg-3"><a class="metric-link" href="{{ route('admission.counsellor-desk.index') }}"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">{{ ucfirst(str_replace('_', ' ', $label)) }}</div><div class="fs-4 fw-bold">{{ $value }}</div></div></div></a></div>
@endforeach
</div>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent fw-bold">Next Best Calls</div>
            <div class="list-group list-group-flush">
                @forelse($desk['nextBestCalls'] as $lead)
                    <a class="list-group-item list-group-item-action d-flex justify-content-between gap-2" href="{{ route('admission.leads.show', $lead) }}">
                        <span><strong>{{ $lead->name }}</strong><div class="small text-muted">{{ $lead->phone }} · {{ $lead->program?->name }} · {{ $lead->next_action }}</div></span>
                        <span class="badge bg-{{ in_array($lead->priority, ['urgent','high']) ? 'danger' : 'secondary' }}">{{ ucfirst($lead->priority ?? 'normal') }}</span>
                    </a>
                @empty
                    <div class="list-group-item text-muted">No calls due.</div>
                @endforelse
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent fw-bold">Applicant Blockers</div>
            <div class="list-group list-group-flush">
                @forelse($desk['applicantBlockers'] as $applicant)
                    <a class="list-group-item list-group-item-action" href="{{ route('admission.applicants.show', $applicant) }}">
                        <strong>{{ $applicant->user?->name ?? $applicant->application_number }}</strong>
                        <div class="small text-muted">{{ $applicant->status_label }} · {{ $applicant->program?->name }} · {{ $applicant->next_action }}</div>
                    </a>
                @empty
                    <div class="list-group-item text-muted">No applicant blockers.</div>
                @endforelse
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-bold">Assessment Follow-ups</div>
            <div class="list-group list-group-flush">
                @forelse($desk['assessmentFollowups'] as $assignment)
                    <a class="list-group-item list-group-item-action" href="{{ route('admission.assessment-control-room.index') }}">
                        <strong>{{ $assignment->applicant?->user?->name }}</strong>
                        <div class="small text-muted">{{ $assignment->panel?->name }} · {{ ucwords(str_replace('_', ' ', $assignment->lifecycle_status)) }}</div>
                    </a>
                @empty
                    <div class="list-group-item text-muted">No assessment follow-ups.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent fw-bold">Due Reminders</div>
            <div class="list-group list-group-flush">
                @forelse($desk['reminders'] as $reminder)
                    <div class="list-group-item d-flex justify-content-between gap-2">
                        <span><strong>{{ ucfirst(str_replace('_', ' ', $reminder->reason)) }}</strong><div class="small text-muted">{{ class_basename($reminder->subject_type) }} #{{ $reminder->subject_id }} · {{ optional($reminder->due_at)->format('d M H:i') }}</div></span>
                        <form method="POST" action="{{ route('admission.reminders.send', $reminder) }}">@csrf<button class="btn btn-sm btn-outline-success">Send</button></form>
                    </div>
                @empty
                    <div class="list-group-item text-muted">No reminders due.</div>
                @endforelse
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent fw-bold">Conversation Timeline</div>
            <div class="list-group list-group-flush">
                @forelse($desk['timeline'] as $event)
                    <div class="list-group-item small"><i class="bi bi-{{ $event['icon'] }} me-1"></i><strong>{{ $event['title'] }}</strong><div class="text-muted">{{ Str::limit($event['body'], 90) }}</div><div class="text-muted">{{ optional($event['at'])->diffForHumans() }}</div></div>
                @empty
                    <div class="list-group-item text-muted">No recent conversation events.</div>
                @endforelse
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-bold">Playbooks</div>
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
