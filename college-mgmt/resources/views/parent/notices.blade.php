@extends('layouts.parent')
@section('title', 'Notices')
@section('page-title', 'Notices')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('parent.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Notices</li>
@endsection

@section('content')
<div class="mb-4">
    <h5 class="fw-bold mb-0">Notice Board</h5>
    <div class="text-muted" style="font-size:.82rem">Latest announcements from the institution</div>
</div>

@forelse($notices as $notice)
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
            <h6 class="fw-bold mb-0">{{ $notice->title }}</h6>
            <div class="d-flex gap-1 flex-shrink-0">
                <span class="badge {{ match($notice->audience){'all'=>'bg-primary','students'=>'badge-active','teachers'=>'badge-paid','admin'=>'bg-secondary',default=>'bg-secondary'} }}">
                    {{ ucfirst($notice->audience) }}
                </span>
                @if(($notice->priority ?? 'normal') !== 'normal')
                <span class="badge {{ $notice->priority === 'urgent' ? 'bg-danger' : 'bg-warning text-dark' }}">
                    {{ ucfirst($notice->priority) }}
                </span>
                @endif
            </div>
        </div>
        <p class="text-muted mb-1" style="font-size:.85rem">{{ Str::limit(strip_tags($notice->content), 200) }}</p>
        <div class="text-muted" style="font-size:.78rem"><i class="bi bi-calendar me-1"></i>{{ $notice->publish_date->format('d M Y') }}</div>
    </div>
</div>
@empty
<div class="empty-state py-5 text-center">
    <div class="empty-icon"><i class="bi bi-megaphone fs-1 text-muted"></i></div>
    <div class="mt-2 fw-semibold">No notices at the moment</div>
</div>
@endforelse

@if($notices->hasPages())
<div class="mt-3">{{ $notices->links() }}</div>
@endif
@endsection
