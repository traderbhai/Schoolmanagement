@extends('layouts.admin')

@section('title', 'Notification')
@section('page-title', 'Notification')

@section('content')
<div class="container-fluid py-4">
    <div class="card">
        <div class="card-body">
            <h5>{{ $notification->title }}</h5>
            <p class="text-muted">{{ $notification->created_at->format('d M Y H:i') }}</p>
            <p>{{ $notification->message }}</p>
            @if($notification->action_url)
                <a href="{{ $notification->action_url }}" class="btn btn-primary">View Details</a>
            @endif
            <a href="{{ route('notifications.index') }}" class="btn btn-secondary">Back</a>
        </div>
    </div>
</div>
@endsection
