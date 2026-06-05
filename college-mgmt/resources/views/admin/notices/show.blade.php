@extends('layouts.admin')
@section('title', $notice->title)
@section('page-title', 'Notice Detail')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.notices.index') }}">Notices</a></li>
    <li class="breadcrumb-item active">View</li>
@endsection

@section('content')
<div class="card" style="max-width:760px">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-megaphone me-2 text-primary"></i>Notice Detail</span>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.notices.edit', $notice) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit</a>
            <a href="{{ route('admin.notices.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
        </div>
    </div>
    <div class="card-body">
        <h4 class="fw-bold mb-3">{{ $notice->title }}</h4>

        <div class="d-flex flex-wrap gap-2 mb-4 align-items-center">
            {{-- Audience badge --}}
            <span class="badge {{ match($notice->audience){'all'=>'bg-primary','students'=>'badge-active','teachers'=>'badge-paid','admin'=>'bg-secondary',default=>'bg-secondary'} }}">
                <i class="bi bi-people me-1"></i>{{ ucfirst($notice->audience) }}
            </span>

            {{-- Priority badge --}}
            @php
                $priorityClass = match($notice->priority ?? 'normal') {
                    'important' => 'bg-warning text-dark',
                    'urgent'    => 'bg-danger',
                    default     => 'bg-secondary',
                };
            @endphp
            <span class="badge {{ $priorityClass }}">
                <i class="bi bi-flag me-1"></i>{{ ucfirst($notice->priority ?? 'normal') }}
            </span>

            {{-- Status --}}
            <span class="badge {{ $notice->is_published ? 'badge-active' : 'bg-secondary' }}">
                {{ $notice->is_published ? 'Published' : 'Draft' }}
            </span>

            {{-- Publish date --}}
            <span class="text-muted" style="font-size:.82rem">
                <i class="bi bi-calendar me-1"></i>
                Published: {{ $notice->publish_date->format('d M Y') }}
                @if($notice->published_at)
                    ({{ $notice->published_at->format('d M Y H:i') }})
                @endif
            </span>

            {{-- Expiry --}}
            @if($notice->expiry_date)
            <span class="text-muted" style="font-size:.82rem">
                <i class="bi bi-calendar-x me-1"></i>Expires: {{ $notice->expiry_date->format('d M Y') }}
            </span>
            @endif
        </div>

        <hr>

        <div class="mt-3" style="line-height:1.8;white-space:pre-wrap;">{!! nl2br(e($notice->content)) !!}</div>

        <div class="mt-4 pt-3 border-top text-muted" style="font-size:.8rem">
            Posted by <strong>{{ $notice->user->name }}</strong> &middot; {{ $notice->created_at->format('d M Y, H:i') }}
        </div>
    </div>
</div>
@endsection
