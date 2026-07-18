@extends('layouts.admin')

@section('title', 'Academic Calendar')
@section('page-title', 'Academic Calendar')

@section('content')
<div class="container-fluid py-4">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <div class="mb-3">
        <a href="{{ route('academic.academic-calendars.create') }}" class="btn btn-primary">Add Event</a>
    </div>
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr><th scope="col">Event Name</th><th scope="col">Date</th><th scope="col">Type</th><th scope="col">Holiday</th><th scope="col">Actions</th></tr>
                </thead>
                <tbody>
                    @foreach($events as $event)
                    <tr>
                        <td>{{ $event->event_name }}</td>
                        <td>{{ $event->event_date->format('d M Y') }}</td>
                        <td>{{ ucfirst(str_replace('_',' ',$event->event_type)) }}</td>
                        <td>{{ $event->is_holiday ? 'Yes' : 'No' }}</td>
                        <td><a href="{{ route('academic.academic-calendars.show', $event) }}" class="btn btn-sm btn-primary">View</a></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $events->links() }}
        </div>
    </div>
</div>
@endsection
