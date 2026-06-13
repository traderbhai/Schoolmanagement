@extends('layouts.admin')

@section('title', 'Conversation Timeline')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h3 class="fw-bold mb-1">Conversation Timeline</h3><div class="text-muted small">{{ ucfirst($subjectType) }} #{{ $subject->id }}</div></div>
    <a class="btn btn-outline-primary btn-sm" href="{{ route('admission.counsellor-desk.index') }}">Counsellor Desk</a>
</div>
<div class="card border-0 shadow-sm">
    <div class="list-group list-group-flush">
        @foreach($events as $event)
            <div class="list-group-item">
                <div class="d-flex justify-content-between gap-2">
                    <strong><i class="bi bi-{{ $event['icon'] }} me-1"></i>{{ $event['title'] }}</strong>
                    <span class="small text-muted">{{ optional($event['at'])->format('d M Y H:i') }}</span>
                </div>
                <div class="small text-muted">{{ $event['type'] }}</div>
                @if($event['body'])<div>{{ $event['body'] }}</div>@endif
            </div>
        @endforeach
    </div>
</div>
@endsection
