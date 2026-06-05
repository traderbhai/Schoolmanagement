@extends('layouts.app')
@section('content')
<div class="container">
    <h1>{{ $notification->title }}</h1>
    <p>{{ $notification->message }}</p>
    <p>Status: {{ $notification->is_read ? 'Read' : 'Unread' }}</p>
    @if($notification->action_url)
        <a href="{{ $notification->action_url }}" class="btn btn-primary">Action</a>
    @endif
</div>
@endsection
