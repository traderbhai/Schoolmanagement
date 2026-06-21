@extends('layouts.student')
@section('title', 'Career Events')
@section('page-title', 'Career Events')

@section('content')
<div class="container-fluid py-3" style="max-width:960px">

    {{-- Upcoming Events --}}
    <h6 class="fw-semibold mb-3">Upcoming Events</h6>
    @if($upcoming->isEmpty())
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body text-center py-4">
            <i class="bi bi-calendar-event fs-1 d-block mb-2 text-muted"></i>
            <div class="fw-semibold text-dark mb-1">No upcoming career events are published right now</div>
            <div class="text-muted small mx-auto" style="max-width:620px">
                CMC publishes workshops, mock interviews, company visits, and career fairs after the date,
                registration deadline, and seats are confirmed. Check this page again later; your registered
                and attended events will appear here once available.
            </div>
        </div>
    </div>
    @else
    <div class="row g-3 mb-4">
        @foreach($upcoming as $event)
        @php $registered = in_array($event->id, $myRegistrations); @endphp
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="badge bg-primary-subtle text-primary">{{ ucwords(str_replace('_',' ',$event->event_type)) }}</span>
                        @if($registered)
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>Registered</span>
                                @if($canManageCareerEventRegistrations && $event->isOpen())
                                    <form method="POST" action="{{ route('student.career-events.cancel', $event) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Cancel</button>
                                    </form>
                                @endif
                            </div>
                        @elseif($canManageCareerEventRegistrations && $event->isOpen())
                            <form method="POST" action="{{ route('student.career-events.register', $event) }}">
                                @csrf
                                <button class="btn btn-sm btn-outline-primary">Register</button>
                            </form>
                        @elseif(!$canManageCareerEventRegistrations)
                            <span class="badge bg-secondary">Active students only</span>
                        @else
                            <span class="badge bg-secondary">Closed</span>
                        @endif
                    </div>
                    <h6 class="fw-semibold mb-1">{{ $event->title }}</h6>
                    <div class="text-muted small mb-2">
                        <i class="bi bi-calendar3 me-1"></i>{{ \Carbon\Carbon::parse($event->event_date)->format('d M Y') }}
                        @if($event->venue) <span class="mx-1">|</span><i class="bi bi-geo-alt me-1"></i>{{ $event->venue }} @endif
                    </div>
                    @if($event->description)
                    <p class="small text-muted mb-1">{{ Str::limit($event->description, 120) }}</p>
                    @endif
                    <div class="d-flex gap-3 mt-2" style="font-size:.78rem;color:#777">
                        @if($event->seats)
                        <span><i class="bi bi-people me-1"></i>{{ $event->registrations_count }}/{{ $event->seats }} seats</span>
                        @endif
                        @if($event->registration_deadline)
                        <span><i class="bi bi-clock me-1"></i>Deadline: {{ \Carbon\Carbon::parse($event->registration_deadline)->format('d M Y') }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Past Events --}}
    @if($past->isNotEmpty())
    <h6 class="fw-semibold mb-3 text-muted">Past Events</h6>
    <div class="card border-0 shadow-sm">
        <div class="list-group list-group-flush">
            @foreach($past as $event)
            @php $registered = in_array($event->id, $myRegistrations); @endphp
            <div class="list-group-item px-4 py-2 d-flex justify-content-between align-items-center">
                <div>
                    <span class="fw-semibold small">{{ $event->title }}</span>
                    <span class="text-muted small ms-2">{{ \Carbon\Carbon::parse($event->event_date)->format('d M Y') }}</span>
                </div>
                @if($registered)
                    <span class="badge bg-secondary">Attended</span>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
