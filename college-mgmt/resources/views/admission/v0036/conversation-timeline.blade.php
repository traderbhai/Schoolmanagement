@extends('layouts.admin')

@section('title', 'Conversation Timeline')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h3 class="fw-bold mb-1">Conversation Timeline</h3><div class="text-muted small">{{ ucfirst($subjectType) }} #{{ $subject->id }}</div></div>
    <a class="btn btn-outline-primary btn-sm" href="{{ route('admission.counsellor-desk.index') }}">Counsellor Desk</a>
</div>
<div class="alert alert-info border-0 shadow-sm small">
    <div class="fw-semibold mb-1">Timeline reading order</div>
    <div class="d-flex flex-wrap gap-2">
        <span class="badge text-bg-light border">Calls</span>
        <span class="badge text-bg-light border">Counselling notes</span>
        <span class="badge text-bg-light border">Reminders</span>
        <span class="badge text-bg-light border">Messages</span>
        <span class="badge text-bg-light border">Assessment/payment/document events</span>
    </div>
    <div class="text-muted mt-2">Use the latest event plus open blockers to decide whether to call, remind, request a document, escalate, or wait for applicant action.</div>
</div>
<div class="card border-0 shadow-sm">
    <div class="list-group list-group-flush">
        @forelse($events as $event)
            <div class="list-group-item">
                <div class="d-flex justify-content-between gap-2">
                    <strong><i class="bi bi-{{ $event['icon'] }} me-1"></i>{{ $event['title'] }}</strong>
                    <span class="small text-muted">{{ optional($event['at'])->format('d M Y H:i') }}</span>
                </div>
                <div class="small text-muted">{{ $event['type'] }}</div>
                @if($event['body'])<div>{{ $event['body'] }}</div>@endif
            </div>
        @empty
            <div class="list-group-item text-muted">No timeline events yet. Start from the Counsellor Desk, log the first call or reminder, and this page will become the shared history.</div>
        @endforelse
    </div>
</div>
@endsection
