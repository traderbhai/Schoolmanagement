@extends($layout ?? 'layouts.admin')

@section('title', 'Notification')
@section('page-title', 'Notification')

@section('content')
<div class="container-fluid py-3" style="max-width:860px">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center gap-2 py-3">
            <div class="fw-semibold">
                <i class="bi bi-bell me-2 text-primary"></i>Notification Detail
            </div>
            <a href="{{ route('notifications.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to Inbox
            </a>
        </div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="badge text-bg-light">Owner: Your account</span>
                <span class="badge text-bg-light">Source: {{ ucfirst(str_replace('_', ' ', $notification->type ?: 'general notification')) }}</span>
                <span class="badge text-bg-light">Read status updates when this page opens</span>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                @if($notification->type)
                    <span class="badge bg-secondary-subtle text-secondary">{{ ucfirst($notification->type) }}</span>
                @endif
                <span class="text-muted small">
                    <i class="bi bi-calendar me-1"></i>{{ $notification->created_at->format('d M Y H:i') }}
                </span>
                @if($notification->is_read)
                    <span class="badge bg-success-subtle text-success">Read</span>
                @endif
            </div>

            <h5 class="fw-bold mb-3">{{ $notification->title }}</h5>
            <div class="text-muted" style="line-height:1.7;">{{ $notification->message }}</div>

            <div class="d-flex flex-wrap gap-2 mt-4">
                @if($notification->action_url)
                    <a href="{{ $notification->action_url }}" class="btn btn-primary btn-sm">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Open Related Page
                    </a>
                    <span class="text-muted small align-self-center">The related page is supplied by the source workflow that created this notification.</span>
                @endif
                <a href="{{ route('notifications.index') }}" class="btn btn-outline-secondary btn-sm">Back to Inbox</a>
            </div>
        </div>
    </div>
</div>
@endsection
