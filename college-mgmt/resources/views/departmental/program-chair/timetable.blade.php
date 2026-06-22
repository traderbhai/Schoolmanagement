@extends('layouts.admin')
@section('title', 'Program Chair - Timetable')

@section('content')
<h4 class="mb-4"><i class="bi bi-grid-3x3-gap me-2 text-primary"></i>Program Timetable (Read-Only)</h4>

@foreach($days as $day)
    @if(isset($entries[$day]) && $entries[$day]->count())
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-light fw-semibold">{{ $day }}</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Time</th>
                            <th>Subject</th>
                            <th>Group / Batch</th>
                            <th>Session</th>
                            <th>Teacher</th>
                            <th>Room</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($entries[$day] as $e)
                        <tr>
                            <td class="text-nowrap">
                                {{ $e->start_time ?? 'Time pending' }}
                                @if($e->end_time)
                                    - {{ $e->end_time }}
                                @endif
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $e->subject_name }}</div>
                                @if($e->subject_code)
                                    <div class="text-muted small">{{ $e->subject_code }}</div>
                                @endif
                            </td>
                            <td>
                                <div>{{ $e->group_name ?? $e->batch_name ?? 'Group not linked' }}</div>
                                @if($e->group_name && $e->batch_name)
                                    <div class="text-muted small">{{ $e->batch_name }}</div>
                                @endif
                            </td>
                            <td>
                                @if($e->source === 'canonical_pmc_official_session')
                                    <span class="badge bg-info text-dark">Official PMC</span>
                                @else
                                    <span class="badge bg-secondary">Legacy</span>
                                @endif
                                @if($e->session_type)
                                    <span class="badge bg-light text-dark border">{{ ucfirst($e->session_type) }}</span>
                                @endif
                                @if(($e->duration_slots ?? 1) > 1)
                                    <span class="badge bg-light text-dark border">{{ $e->duration_slots }} slots</span>
                                @endif
                            </td>
                            <td>{{ $e->teacher_name }}</td>
                            <td>{{ $e->room_name }}</td>
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
    <p class="text-muted">No official timetable sessions found.</p>
@endif
@endsection
