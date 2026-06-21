@extends($layout ?? 'layouts.admin')

@section('title', 'Notifications')
@section('page-title', 'Notifications')

@section('content')
<div class="container-fluid py-3" style="max-width:1040px">
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <div class="text-uppercase text-muted fw-semibold mb-1" style="font-size:.72rem;letter-spacing:.04em">Notification Inbox</div>
                <h5 class="fw-bold mb-1">Your institute messages and action alerts</h5>
                <p class="text-muted mb-0">
                    Review official updates, workflow reminders, document/payment alerts, and action links sent to your account.
                    Open a message to mark it as read automatically.
                </p>
                <div class="mt-2 d-flex flex-wrap gap-2">
                    <span class="badge text-bg-light">Owner: Your account</span>
                    <span class="badge text-bg-light">Source: workflow notifications, approvals, payments, documents, timetable, and institute messages assigned to you</span>
                </div>
            </div>
            <div class="text-end small text-muted">
                <div><strong>{{ $unreadCount }}</strong> unread</div>
                <div><strong>{{ $notifications->total() }}</strong> matching messages</div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('notifications.index') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small text-muted mb-1">Search inbox</label>
                    <input type="search" name="search" class="form-control form-control-sm" value="{{ $filters['search'] ?? '' }}" placeholder="Title or message">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Read status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All messages</option>
                        <option value="unread" @selected(($filters['status'] ?? '') === 'unread')>Unread only</option>
                        <option value="read" @selected(($filters['status'] ?? '') === 'read')>Read only</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Type</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="">All types</option>
                        @foreach($typeOptions as $option)
                            <option value="{{ $option }}" @selected(($filters['type'] ?? '') === $option)>{{ ucfirst(str_replace('_', ' ', $option)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill">Apply</button>
                    <a href="{{ route('notifications.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                </div>
            </form>
            <div class="text-muted small mt-2">
                Showing {{ $notifications->total() }} notification(s)
                @if(($filters['search'] ?? '') !== '') for "{{ $filters['search'] }}" @endif
                @if(($filters['status'] ?? '') !== '') with status "{{ $filters['status'] }}" @endif
                @if(($filters['type'] ?? '') !== '') and type "{{ str_replace('_', ' ', $filters['type']) }}" @endif.
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
            <div>
                <div class="fw-semibold">Inbox</div>
                <div class="text-muted small">Newest matching messages appear first. This list only contains notifications owned by your account.</div>
            </div>
            <button
                type="button"
                onclick="markAllRead()"
                class="btn btn-outline-secondary btn-sm"
                {{ $unreadCount === 0 ? 'disabled' : '' }}
            >
                <i class="bi bi-check2-all me-1"></i>Mark All Read
            </button>
        </div>

        @forelse($notifications as $notification)
            <div class="list-group list-group-flush">
                <div class="list-group-item d-flex align-items-start gap-3 py-3 {{ $notification->is_read ? '' : 'bg-light' }}">
                    <div class="rounded-circle d-flex align-items-center justify-content-center {{ $notification->is_read ? 'bg-secondary-subtle text-secondary' : 'bg-primary-subtle text-primary' }}" style="width:36px;height:36px;flex:0 0 36px;">
                        <i class="bi {{ $notification->is_read ? 'bi-envelope-open' : 'bi-envelope-fill' }}"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <a href="{{ route('notifications.show', $notification) }}" class="fw-semibold text-decoration-none text-dark">
                                {{ $notification->title }}
                            </a>
                            @if(!$notification->is_read)
                                <span class="badge bg-primary">New</span>
                            @endif
                            @if($notification->type)
                                <span class="badge bg-secondary-subtle text-secondary">{{ ucfirst($notification->type) }}</span>
                            @endif
                        </div>
                        <div class="text-muted small mb-1">{{ \Illuminate\Support\Str::limit($notification->message, 180) }}</div>
                        <div class="d-flex flex-wrap align-items-center gap-2 small text-muted">
                            <span><i class="bi bi-clock me-1"></i>{{ $notification->created_at->diffForHumans() }}</span>
                            <span>|</span>
                            <span>Source: {{ ucfirst(str_replace('_', ' ', $notification->type ?: 'general')) }}</span>
                            @if($notification->action_url)
                                <span>|</span>
                                <span>Includes action link</span>
                            @endif
                        </div>
                    </div>
                    <a href="{{ route('notifications.show', $notification) }}" class="btn btn-sm btn-outline-primary flex-shrink-0">
                        Open
                    </a>
                </div>
            </div>
        @empty
            <div class="card-body text-center py-5">
                <i class="bi bi-bell fs-1 d-block mb-2 text-muted"></i>
                <div class="fw-semibold text-dark mb-1">No notifications match the current inbox filters</div>
                <div class="text-muted small mx-auto" style="max-width:640px">
                    Official messages will appear here when an institute office sends an update, reminder,
                    approval decision, payment/document alert, timetable change, or workflow action for your account.
                    Clear filters to review older read messages, other notification types, or all owned messages.
                </div>
                <a href="{{ route('notifications.index') }}" class="btn btn-sm btn-outline-secondary mt-3">Clear Filters</a>
            </div>
        @endforelse

        @if($notifications->hasPages())
            <div class="card-footer bg-white py-2">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
function markAllRead() {
    var tokenMeta = document.querySelector('meta[name=csrf-token]');
    if (!tokenMeta) return;

    fetch('{{ route('notifications.mark-all-read') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': tokenMeta.content,
            'X-Requested-With': 'XMLHttpRequest'
        }
    }).then(function () {
        location.reload();
    });
}
</script>
@endpush
