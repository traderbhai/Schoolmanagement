@extends('layouts.student')
@section('title', $notice->title)
@section('page-title', 'Notice Detail')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('student.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('student.notices') }}">Notices</a></li>
    <li class="breadcrumb-item active">Detail</li>
@endsection

@section('content')
<div class="card" style="max-width:760px">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-megaphone me-2 text-primary"></i>Notice</span>
        <a href="{{ route('student.notices') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
    <div class="card-body">
        <h4 class="fw-bold mb-3">{{ $notice->title }}</h4>

        <div class="d-flex flex-wrap gap-2 mb-4 align-items-center">
            <span class="badge {{ match($notice->audience){'all'=>'bg-primary','students'=>'badge-active','teachers'=>'badge-paid','admin'=>'bg-secondary',default=>'bg-secondary'} }}">
                <i class="bi bi-people me-1"></i>{{ ucfirst($notice->audience) }}
            </span>
            @if(($notice->priority ?? 'normal') !== 'normal')
            <span class="badge {{ $notice->priority === 'urgent' ? 'bg-danger' : 'bg-warning text-dark' }}">
                <i class="bi bi-flag me-1"></i>{{ ucfirst($notice->priority) }}
            </span>
            @endif
            <span class="text-muted" style="font-size:.82rem">
                <i class="bi bi-calendar me-1"></i>{{ $notice->publish_date->format('d M Y') }}
            </span>
            @if($notice->expiry_date)
            <span class="text-muted" style="font-size:.82rem">
                <i class="bi bi-calendar-x me-1"></i>Expires: {{ $notice->expiry_date->format('d M Y') }}
            </span>
            @endif
        </div>

        <hr>
        <div class="mt-3" style="line-height:1.8;">{!! nl2br(e($notice->content)) !!}</div>
    </div>
</div>
@endsection
