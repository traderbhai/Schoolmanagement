@extends('layouts.admin')

@section('title', 'Notifications')
@section('page-title', 'Notifications')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between mb-3">
        <h5>Your Notifications</h5>
        <button onclick="markAllRead()" class="btn btn-outline-secondary btn-sm">Mark All Read</button>
    </div>
    <div class="card">
        <div class="card-body p-0">
            @forelse($notifications as $notification)
                <div class="d-flex align-items-start p-3 border-bottom {{ $notification->is_read ? '' : 'bg-light' }}">
                    <div class="flex-grow-1">
                        <a href="{{ route('notifications.show', $notification) }}" class="text-decoration-none text-dark">
                            <strong>{{ $notification->title }}</strong>
                        </a>
                        <p class="mb-0 text-muted small">{{ $notification->message }}</p>
                        <small class="text-muted">{{ $notification->created_at->diffForHumans() }}</small>
                    </div>
                    @if(!$notification->is_read)
                        <span class="badge bg-primary ms-2">New</span>
                    @endif
                </div>
            @empty
                <div class="p-4 text-center text-muted">No notifications</div>
            @endforelse
        </div>
    </div>
    {{ $notifications->links() }}
</div>
@endsection

@push('scripts')
<script>
function markAllRead() {
    fetch('{{ route('notifications.mark-all-read') }}', {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content}
    }).then(() => location.reload());
}
</script>
@endpush
