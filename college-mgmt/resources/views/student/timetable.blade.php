@extends('layouts.student')

@section('title', 'My Timetable')

@section('page-title', 'My Timetable')

@push('styles')
<style>
    .student-timetable-page {
        max-width: 100%;
        overflow-x: hidden;
    }

    .student-timetable-page .alert,
    .student-timetable-page .card,
    .student-timetable-page .border {
        max-width: 100%;
    }

    .student-timetable-page .alert {
        overflow-wrap: anywhere;
    }

    @media (max-width: 575.98px) {
        .student-timetable-page.container {
            padding-left: .75rem;
            padding-right: .75rem;
        }

        .student-timetable-page .alert {
            padding: .9rem 1rem;
            width: calc(100vw - 24px);
            max-width: 366px;
        }

        .student-timetable-page .card {
            width: 100%;
        }
    }
</style>
@endpush

@section('content')
<div class="container py-4 student-timetable-page">
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
            <div class="fw-semibold mb-1"><i class="bi bi-info-circle me-1"></i>No published timetable is available for your enrolled subjects yet</div>
            <div class="small">
                If your subject basket is still pending, review subject registration first. If subjects are already allocated, wait for the PMC/academic office to publish the official timetable or contact the academic office for a section/group allocation check.
            </div>
            <a class="btn btn-sm btn-outline-primary mt-2" href="{{ route('student.subjects.index') }}">Review subject registration</a>
        </div>
    @else
        @foreach($days as $day)
            @if($entries->has($day))
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-primary text-white py-2">
                    <h6 class="mb-0 fw-semibold">{{ $day }}</h6>
                </div>
                <div class="card-body p-0">
                    <div class="d-md-none p-3">
                        <div class="vstack gap-2">
                            @foreach($entries[$day] as $entry)
                                <div class="border rounded-2 p-3 bg-white">
                                    <div class="d-flex justify-content-between gap-2 align-items-start mb-2">
                                        <div class="fw-semibold">{{ $entry->subject?->name ?? $entry->courseGroup?->subject?->name ?? 'Subject not set' }}</div>
                                        <div class="font-monospace small text-nowrap">
                                            {{ $entry->slot ? \Carbon\Carbon::parse($entry->slot->start_time)->format('h:i A') : 'Not set' }}
                                        </div>
                                    </div>
                                    <div class="small text-muted mb-2">
                                        {{ $entry->subject?->code ?? $entry->courseGroup?->subject?->code ?? '' }}
                                        @if($entry->courseGroup)
                                            <span class="ms-1">| {{ $entry->courseGroup->name }}</span>
                                        @endif
                                    </div>
                                    <dl class="row small mb-0 g-2">
                                        <dt class="col-4 text-muted fw-semibold">Time</dt>
                                        <dd class="col-8 mb-0">
                                            {{ $entry->slot ? \Carbon\Carbon::parse($entry->slot->start_time)->format('h:i A') : 'Not set' }}
                                            &ndash;
                                            {{ $entry->slot ? \Carbon\Carbon::parse($entry->slot->end_time)->format('h:i A') : 'Not set' }}
                                        </dd>
                                        <dt class="col-4 text-muted fw-semibold">Faculty</dt>
                                        <dd class="col-8 mb-0">{{ $entry->teacher->user->name ?? 'Teacher not assigned' }}</dd>
                                        <dt class="col-4 text-muted fw-semibold">Room</dt>
                                        <dd class="col-8 mb-0">{{ $entry->classroom->name ?? 'Room not assigned' }}</dd>
                                    </dl>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-sm mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col">Time</th>
                                    <th scope="col">Subject</th>
                                    <th scope="col">Teacher</th>
                                    <th scope="col">Classroom</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($entries[$day] as $entry)
                                <tr>
                                    <td class="font-monospace small fw-semibold">
                                        {{ $entry->slot ? \Carbon\Carbon::parse($entry->slot->start_time)->format('h:i A') : 'Not set' }}
                                        &mdash;
                                        {{ $entry->slot ? \Carbon\Carbon::parse($entry->slot->end_time)->format('h:i A') : 'Not set' }}
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $entry->subject?->name ?? $entry->courseGroup?->subject?->name ?? 'Subject not set' }}</div>
                                        <div class="small text-muted">
                                            {{ $entry->subject?->code ?? $entry->courseGroup?->subject?->code ?? '' }}
                                            @if($entry->courseGroup)
                                                <span class="ms-1">| {{ $entry->courseGroup->name }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>{{ $entry->teacher->user->name ?? 'Teacher not assigned' }}</td>
                                    <td>{{ $entry->classroom->name ?? 'Room not assigned' }}</td>
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
