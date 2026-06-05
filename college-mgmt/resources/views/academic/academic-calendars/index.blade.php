@extends('layouts.app')
@section('content')
<div class="container">
    <h1>Academic Calendar</h1>
    <table class="table">
        <thead>
            <tr><th>Event Name</th><th>Date</th><th>Type</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @foreach($events as $event)
            <tr>
                <td>{{ $event->event_name }}</td>
                <td>{{ $event->event_date }}</td>
                <td>{{ $event->event_type }}</td>
                <td><a href="{{ route('academic.academic-calendars.show', $event) }}">View</a></td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
