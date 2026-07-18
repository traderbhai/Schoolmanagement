@extends('layouts.admin')

@section('title', 'Calendar Event Details')
@section('page-title', 'Calendar Event Details')

@section('content')
<div class="container-fluid py-4">
    <div class="card">
        <div class="card-body">
            <h5>{{ $academicCalendar->event_name }}</h5>
            <p><strong>Date:</strong> {{ $academicCalendar->event_date->format('d M Y') }}</p>
            <p><strong>Type:</strong> {{ ucfirst(str_replace('_',' ',$academicCalendar->event_type)) }}</p>
            <p><strong>Holiday:</strong> {{ $academicCalendar->is_holiday ? 'Yes' : 'No' }}</p>
            @if($academicCalendar->description)
                <p><strong>Description:</strong> {{ $academicCalendar->description }}</p>
            @endif
            <div class="mt-3">
                <a href="{{ route('academic.academic-calendars.edit', $academicCalendar) }}" class="btn btn-warning">Edit</a>
                <form action="{{ route('academic.academic-calendars.destroy', $academicCalendar) }}" method="POST" class="d-inline">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger" onclick="return confirm('Delete calendar event {{ addslashes($academicCalendar->event_name) }} on {{ $academicCalendar->event_date->format('d M Y') }}? Confirm this will not affect published timetables, holidays, exams, attendance windows, or student/faculty communication.')">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
