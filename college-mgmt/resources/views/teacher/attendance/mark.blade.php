@extends('layouts.teacher')
@section('title','Mark Attendance')
@section('page-title','Mark Attendance')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('teacher.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Mark Attendance</li>
@endsection

@section('content')
{{-- Date Picker --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('teacher.attendance.mark') }}" class="d-flex align-items-center gap-3 flex-wrap">
            @if(request('entry_id'))
                <input type="hidden" name="entry_id" value="{{ request('entry_id') }}">
            @endif
            <label class="fw-semibold mb-0"><i class="bi bi-calendar3 me-1 text-primary"></i>Select Date:</label>
            <input type="date" name="date" value="{{ $date }}" class="form-control form-control-sm" style="width:180px" max="{{ today()->toDateString() }}">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Load</button>
            @if(request('entry_id'))
                <a href="{{ route('teacher.attendance.mark', ['date' => $date]) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Back to Classes
                </a>
            @endif
            <span class="text-muted small ms-auto">
                <i class="bi bi-clock me-1"></i>
                {{ \Carbon\Carbon::parse($date)->format('l, d M Y') }}
            </span>
        </form>
    </div>
</div>

@if($entry)
    {{-- Attendance Form for Selected Entry --}}
    <div class="card mb-3" style="border-left:4px solid #3b82f6;">
        <div class="card-body py-2">
            <div class="row align-items-center">
                <div class="col">
                    <span class="fw-bold text-primary fs-6">{{ $entry->subject->name }}</span>
                    <span class="text-muted mx-2">·</span>
                    <span class="text-muted">{{ $entry->course->name ?? $entry->course->code }}</span>
                    <span class="text-muted mx-2">·</span>
                    <span class="badge bg-info text-dark">{{ $entry->day_name }}</span>
                    <span class="text-muted mx-2">·</span>
                    <span class="text-muted small">{{ $entry->slot->name ?? '' }} {{ $entry->slot ? '('.$entry->slot->start_time.'-'.$entry->slot->end_time.')' : '' }}</span>
                </div>
                <div class="col-auto">
                    <span class="badge bg-secondary"><i class="bi bi-building me-1"></i>{{ $entry->classroom->room_number ?? 'N/A' }}</span>
                </div>
            </div>
        </div>
    </div>

    @if($students->isEmpty())
        <div class="card">
            <div class="card-body text-center py-5 text-muted">
                <i class="bi bi-people display-5 d-block mb-2"></i>
                No students enrolled in this subject for the current semester.
            </div>
        </div>
    @else
    <form method="POST" action="{{ route('teacher.attendance.store') }}">
        @csrf
        <input type="hidden" name="timetable_entry_id" value="{{ $entry->id }}">
        <input type="hidden" name="date" value="{{ $date }}">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-check2-square me-2 text-primary"></i>Attendance — <strong>{{ $students->count() }}</strong> students</span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-success" onclick="markAll('present')">
                        <i class="bi bi-check-all me-1"></i>All Present
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="markAll('absent')">
                        <i class="bi bi-x-circle me-1"></i>All Absent
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width:50px">#</th>
                            <th>Student</th>
                            <th>Roll No.</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($students as $s)
                    @php $existing = $s->attendances->first(); @endphp
                    <tr>
                        <td class="text-muted">{{ $loop->iteration }}</td>
                        <td>
                            <div class="fw-semibold">{{ $s->user->name }}</div>
                            <div class="text-muted small">{{ $s->enrollment_number }}</div>
                        </td>
                        <td><code>{{ $s->roll_number }}</code></td>
                        <td>
                            <div class="d-flex gap-1 flex-wrap">
                                @foreach(['present' => ['success','check-circle'],'absent' => ['danger','x-circle'],'late' => ['warning','clock']] as $status => [$color,$icon])
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input att-radio" type="radio"
                                        name="attendance[{{ $s->id }}]"
                                        id="att_{{ $s->id }}_{{ $status }}"
                                        value="{{ $status }}"
                                        @checked(($existing && $existing->status === $status) || (!$existing && $status === 'present'))>
                                    <label class="form-check-label" for="att_{{ $s->id }}_{{ $status }}">
                                        <span class="badge bg-{{ $color }} {{ $color === 'warning' ? 'text-dark' : '' }}">
                                            <i class="bi bi-{{ $icon }} me-1"></i>{{ ucfirst($status) }}
                                        </span>
                                    </label>
                                </div>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-2"></i>Save Attendance
                </button>
                <a href="{{ route('teacher.attendance.mark', ['date' => $date]) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </form>
    @endif

@elseif($entries->isNotEmpty())
    {{-- List of classes for the selected day --}}
    <div class="mb-3">
        <h6 class="text-muted"><i class="bi bi-list-ul me-2"></i>Your classes on {{ \Carbon\Carbon::parse($date)->format('l, d M Y') }} — select one to mark attendance</h6>
    </div>
    <div class="row g-3">
        @foreach($entries as $e)
        <div class="col-md-6 col-lg-4">
            <div class="card h-100" style="border-left:4px solid #6366f1;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="card-title mb-0 text-primary fw-bold">{{ $e->subject->name }}</h6>
                        <span class="badge bg-light text-dark border">{{ $e->slot->name ?? 'Slot' }}</span>
                    </div>
                    <div class="text-muted small mb-1">
                        <i class="bi bi-book me-1"></i>{{ $e->course->name ?? $e->course->code }}
                    </div>
                    <div class="text-muted small mb-1">
                        <i class="bi bi-clock me-1"></i>
                        {{ $e->slot ? $e->slot->start_time.' – '.$e->slot->end_time : 'N/A' }}
                    </div>
                    <div class="text-muted small mb-3">
                        <i class="bi bi-building me-1"></i>Room: {{ $e->classroom->room_number ?? 'N/A' }}
                    </div>
                    <a href="{{ route('teacher.attendance.mark', ['date' => $date, 'entry_id' => $e->id]) }}"
                       class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-check2-square me-1"></i>Mark Attendance
                    </a>
                </div>
            </div>
        </div>
        @endforeach
    </div>

@else
    {{-- No classes scheduled --}}
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-calendar-x display-4 text-muted d-block mb-3"></i>
            <h5 class="text-muted">No Classes Scheduled</h5>
            <p class="text-muted mb-0">
                You have no classes scheduled for <strong>{{ \Carbon\Carbon::parse($date)->format('l, d M Y') }}</strong>.
            </p>
        </div>
    </div>
@endif

@endsection

@push('scripts')
<script>
function markAll(status) {
    document.querySelectorAll(`input.att-radio[value="${status}"]`).forEach(r => r.checked = true);
}
</script>
@endpush
