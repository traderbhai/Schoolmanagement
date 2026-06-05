@extends('layouts.app')
@section('content')
<div class="container">
    <h1>Notifications</h1>
    <table class="table">
        <thead>
            <tr><th>Title</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @foreach($notifications as $notification)
            <tr>
                <td>{{ $notification->title }}</td>
                <td>{{ $notification->is_read ? 'Read' : 'Unread' }}</td>
                <td><a href="{{ route('notifications.show', $notification) }}">View</a></td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
