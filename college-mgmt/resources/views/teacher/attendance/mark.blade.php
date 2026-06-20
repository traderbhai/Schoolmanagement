@extends('layouts.teacher')
@section('title','Mark Attendance')
@section('page-title','Mark Attendance')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('teacher.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Mark Attendance</li>
@endsection

@section('content')

@if($actionBlockedReason)
    <div class="alert alert-warning">
        <i class="bi bi-lock me-1"></i>{{ $actionBlockedReason }}
    </div>
@endif

{{-- Date + Filter Card --}}
<div class="card mb-4" style="box-shadow:var(--shadow-sm)">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('teacher.attendance.mark') }}" class="row g-3 align-items-end flex-wrap">
            @if(request('entry_id'))
                <input type="hidden" name="entry_id" value="{{ request('entry_id') }}">
            @endif
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-1" style="color:var(--clr-text-muted)">
                    <i class="bi bi-calendar3 me-1 text-primary"></i>Date
                </label>
                <input type="date" name="date" value="{{ $date }}"
                       class="form-control form-control-sm" style="min-width:180px"
                       max="{{ today()->toDateString() }}">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-search me-1"></i>Load Classes
                </button>
                @if(request('entry_id'))
                    <a href="{{ route('teacher.attendance.mark', ['date' => $date]) }}"
                       class="btn btn-outline-secondary btn-sm ms-2">
                        <i class="bi bi-arrow-left me-1"></i>All Classes
                    </a>
                @endif
            </div>
            <div class="col-auto ms-auto">
                <span class="text-muted small">
                    <i class="bi bi-clock me-1"></i>
                    {{ \Carbon\Carbon::parse($date)->format('l, d M Y') }}
                </span>
            </div>
        </form>
    </div>
</div>

@if($entry)
    {{-- Selected Entry Info Banner --}}
    <div class="card mb-4 border-0 border-start border-4 border-primary" style="box-shadow:var(--shadow-sm)">
        <div class="card-body py-3">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <span class="fw-bold text-primary fs-6">{{ $entry->subject->name }}</span>
                <span class="text-muted">·</span>
                <span class="text-muted">{{ $entry->course->name ?? $entry->course->code }}</span>
                <span class="badge badge-info">{{ $entry->day_name }}</span>
                <span class="text-muted small">
                    <i class="bi bi-clock me-1"></i>
                    {{ $entry->slot->name ?? '' }}
                    {{ $entry->slot ? '('.$entry->slot->start_time.' – '.$entry->slot->end_time.')' : '' }}
                </span>
                <span class="ms-auto badge bg-secondary"><i class="bi bi-building me-1"></i>{{ $entry->classroom->room_number ?? 'N/A' }}</span>
            </div>
        </div>
    </div>

    @if($students->isEmpty())
        <div class="card" style="box-shadow:var(--shadow-sm)">
            <div class="card-body">
                <div class="empty-state py-5">
                    <div class="empty-icon"><i class="bi bi-people" style="font-size:3rem;color:var(--clr-text-muted)"></i></div>
                    <h6 class="mt-3 text-muted">No Students</h6>
                    <p class="text-muted small mb-0">No students enrolled in this subject for the current semester.</p>
                </div>
            </div>
        </div>
    @else
    <form method="POST" action="{{ route('teacher.attendance.store') }}">
        @csrf
        <input type="hidden" name="timetable_entry_id" value="{{ $entry->id }}">
        <input type="hidden" name="date" value="{{ $date }}">

        <div class="card" style="box-shadow:var(--shadow-sm)">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="fw-semibold">
                    <i class="bi bi-check2-square me-2 text-primary"></i>
                    Attendance — <strong>{{ $students->count() }}</strong> students
                </span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-success" onclick="markAll('present')" @disabled(!$canMarkAttendance)>
                        <i class="bi bi-check-all me-1"></i>All Present
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="markAll('absent')" @disabled(!$canMarkAttendance)>
                        <i class="bi bi-x-circle me-1"></i>All Absent
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                {{-- Mobile-friendly attendance cards --}}
                <div class="d-md-none">
                    @foreach($students as $s)
                    @php $existing = $s->attendances->first(); @endphp
                    <div class="border-bottom p-3">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white flex-shrink-0"
                                 style="width:40px;height:40px;background:var(--clr-primary,#4f46e5);font-size:.9rem">
                                {{ strtoupper(substr($s->user->name, 0, 1)) }}
                            </div>
                            <div>
                                <div class="fw-semibold" style="font-size:.9rem">{{ $s->user->name }}</div>
                                <div class="text-muted" style="font-size:.75rem">{{ $s->enrollment_number }} · Roll: {{ $s->roll_number }}</div>
                            </div>
                        </div>
                        <div class="btn-group w-100" role="group">
                            @foreach(['present' => ['success','check-circle'],'absent' => ['danger','x-circle'],'late' => ['warning','clock']] as $status => [$color,$icon])
                            <input class="btn-check att-radio" type="radio"
                                   name="attendance[{{ $s->id }}]"
                                   id="mob_att_{{ $s->id }}_{{ $status }}"
                                   value="{{ $status }}"
                                   @checked(($existing && $existing->status === $status) || (!$existing && $status === 'present'))
                                   @disabled(!$canMarkAttendance)>
                            <label class="btn btn-outline-{{ $color }} btn-sm" for="mob_att_{{ $s->id }}_{{ $status }}">
                                <i class="bi bi-{{ $icon }} me-1"></i>{{ ucfirst($status) }}
                            </label>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- Desktop table --}}
                <table class="table table-hover align-middle mb-0 d-none d-md-table">
                    <thead class="table-light">
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
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white flex-shrink-0"
                                     style="width:32px;height:32px;background:var(--clr-primary,#4f46e5);font-size:.8rem">
                                    {{ strtoupper(substr($s->user->name, 0, 1)) }}
                                </div>
                                <div>
                                    <div class="fw-semibold" style="font-size:.88rem">{{ $s->user->name }}</div>
                                    <div class="text-muted" style="font-size:.73rem">{{ $s->enrollment_number }}</div>
                                </div>
                            </div>
                        </td>
                        <td><code>{{ $s->roll_number }}</code></td>
                        <td>
                            <div class="btn-group" role="group">
                                @foreach(['present' => ['success','check-circle'],'absent' => ['danger','x-circle'],'late' => ['warning','clock']] as $status => [$color,$icon])
                                <input class="btn-check att-radio" type="radio"
                                       name="attendance[{{ $s->id }}]"
                                       id="att_{{ $s->id }}_{{ $status }}"
                                       value="{{ $status }}"
                                       @checked(($existing && $existing->status === $status) || (!$existing && $status === 'present'))
                                       @disabled(!$canMarkAttendance)>
                                <label class="btn btn-outline-{{ $color }} btn-sm" for="att_{{ $s->id }}_{{ $status }}">
                                    <i class="bi bi-{{ $icon }} me-1"></i>{{ ucfirst($status) }}
                                </label>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex gap-2 flex-wrap">
                <button type="submit" class="btn btn-primary w-100 w-md-auto" @disabled(!$canMarkAttendance)>
                    <i class="bi bi-save me-2"></i>Save Attendance
                </button>
                <a href="{{ route('teacher.attendance.mark', ['date' => $date]) }}"
                   class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </form>
    @endif

@elseif($entries->isNotEmpty())
    {{-- Class selection for the day --}}
    <div class="mb-3">
        <h6 style="color:var(--clr-text-muted)">
            <i class="bi bi-list-ul me-2"></i>
            Your classes on {{ \Carbon\Carbon::parse($date)->format('l, d M Y') }} — select one to mark attendance
        </h6>
    </div>
    <div class="row g-3">
        @foreach($entries as $e)
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-0 border-start border-4 border-primary" style="box-shadow:var(--shadow-sm)">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="card-title mb-0 fw-bold text-primary" style="font-size:.9rem">{{ $e->subject->name }}</h6>
                        <span class="badge bg-light text-dark border">{{ $e->slot->name ?? 'Slot' }}</span>
                    </div>
                    <div class="text-muted small mb-1"><i class="bi bi-book me-1"></i>{{ $e->course->name ?? $e->course->code }}</div>
                    <div class="text-muted small mb-1"><i class="bi bi-clock me-1"></i>{{ $e->slot ? $e->slot->start_time.' – '.$e->slot->end_time : 'N/A' }}</div>
                    <div class="text-muted small mb-3"><i class="bi bi-building me-1"></i>Room: {{ $e->classroom->room_number ?? 'N/A' }}</div>
                    <a href="{{ route('teacher.attendance.mark', ['date' => $date, 'entry_id' => $e->id]) }}"
                       class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-check2-square me-1"></i>{{ $canMarkAttendance ? 'Mark Attendance' : 'View Attendance' }}
                    </a>
                </div>
            </div>
        </div>
        @endforeach
    </div>

@else
    <div class="card" style="box-shadow:var(--shadow-sm)">
        <div class="card-body">
            <div class="empty-state py-5">
                <div class="empty-icon"><i class="bi bi-calendar-x" style="font-size:3rem;color:var(--clr-text-muted)"></i></div>
                <h5 class="mt-3 text-muted">No Classes Scheduled</h5>
                <p class="text-muted small mb-0">
                    You have no classes scheduled for <strong>{{ \Carbon\Carbon::parse($date)->format('l, d M Y') }}</strong>.
                </p>
            </div>
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
