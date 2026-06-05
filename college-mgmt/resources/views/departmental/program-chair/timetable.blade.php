@extends('layouts.admin')
@section('title', 'Program Chair — Timetable')

@section('content')
<h4 class="mb-4"><i class="bi bi-grid-3x3-gap me-2 text-primary"></i>Program Timetable (Read-Only)</h4>

@foreach($days as $day)
    @if(isset($entries[$day]) && $entries[$day]->count())
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-light fw-semibold">{{ $day }}</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Time</th><th>Subject</th><th>Teacher</th><th>Room</th><th>Batch</th></tr>
                    </thead>
                    <tbody>
                    @foreach($entries[$day] as $e)
                        <tr>
                            <td>{{ $e->timetableSlot?->start_time }} – {{ $e->timetableSlot?->end_time }}</td>
                            <td>{{ $e->subject?->name ?? '—' }}</td>
                            <td>{{ $e->teacher?->user?->name ?? '—' }}</td>
                            <td>{{ $e->classroom?->name ?? '—' }}</td>
                            <td>{{ $e->batch?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
@endforeach

@if($entries->isEmpty())
    <p class="text-muted">No timetable entries found.</p>
@endif
@endsection
