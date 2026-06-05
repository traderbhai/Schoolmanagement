@extends('layouts.app')
@section('content')
<div class="container">
    <h1>{{ $academicCalendar->event_name }}</h1>
    <p>Date: {{ $academicCalendar->event_date }}</p>
    <p>Type: {{ $academicCalendar->event_type }}</p>
    <p>Holiday: {{ $academicCalendar->is_holiday ? 'Yes' : 'No' }}</p>
</div>
@endsection
