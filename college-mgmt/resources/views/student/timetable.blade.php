@extends('layouts.student')

@section('title', 'My Timetable')

@section('page-title', 'My Timetable')

@section('content')
<div class="container py-4">
    <div class="mb-4">
        <h2 class="fw-bold mb-0">My Timetable</h2>
        <p class="text-muted mb-0">Schedule for your enrolled subjects this term</p>
    </div>

    @if(!$student)
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>No student profile found. Please contact the academic office.
        </div>
    @elseif($entries->isEmpty())
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-1"></i>No timetable entries found for your enrolled subjects.
            <a href="{{ route('student.subjects.index') }}">Register for subjects</a> first.
        </div>
    @else
        @foreach($days as $day)
            @if($entries->has($day))
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-primary text-white py-2">
                    <h6 class="mb-0 fw-semibold">{{ $day }}</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Time</th>
                                    <th>Subject</th>
                                    <th>Teacher</th>
                                    <th>Classroom</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($entries[$day] as $entry)
                                <tr>
                                    <td class="font-monospace small fw-semibold">
                                        {{ $entry->slot ? \Carbon\Carbon::parse($entry->slot->start_time)->format('h:i A') : '—' }}
                                        &mdash;
                                        {{ $entry->slot ? \Carbon\Carbon::parse($entry->slot->end_time)->format('h:i A') : '—' }}
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $entry->subject->name ?? '—' }}</div>
                                        <div class="small text-muted">{{ $entry->subject->code ?? '' }}</div>
                                    </td>
                                    <td>{{ $entry->teacher->user->name ?? '—' }}</td>
                                    <td>{{ $entry->classroom->name ?? '—' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
        @endforeach
    @endif
</div>
@endsection
