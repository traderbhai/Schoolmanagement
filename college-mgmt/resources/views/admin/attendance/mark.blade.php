@extends('layouts.admin')
@section('title', 'Mark Attendance')
@section('page-title', 'Mark Attendance')
@section('content')

{{-- Context Bar --}}
<div class="card mb-4">
    <div class="card-body py-3">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-book text-primary fs-5"></i>
                <div>
                    <div class="text-xs text-muted" style="font-size:.74rem">Subject</div>
                    <div class="fw-semibold" style="font-size:.875rem">{{ $entry->subject->name }}</div>
                </div>
            </div>
            <div class="vr d-none d-md-block" style="height:36px"></div>
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-journal-bookmark text-primary fs-5"></i>
                <div>
                    <div class="text-xs text-muted" style="font-size:.74rem">Course</div>
                    <div class="fw-semibold" style="font-size:.875rem">{{ $entry->course->name }}</div>
                </div>
            </div>
            <div class="vr d-none d-md-block" style="height:36px"></div>
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-calendar-event text-primary fs-5"></i>
                <div>
                    <div class="text-xs text-muted" style="font-size:.74rem">Date</div>
                    <div class="fw-semibold" style="font-size:.875rem">{{ \Carbon\Carbon::parse($request->date)->format('d M Y') }}</div>
                </div>
            </div>
            <div class="vr d-none d-md-block" style="height:36px"></div>
            <span class="badge bg-primary">{{ $entry->day_name }}</span>
            <div class="ms-auto">
                <a href="{{ route('admin.attendance.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
            </div>
        </div>
    </div>
</div>

<form method="POST" action="{{ route('admin.attendance.store') }}">
    @csrf
    <input type="hidden" name="timetable_entry_id" value="{{ $entry->id }}">
    <input type="hidden" name="date" value="{{ $request->date }}">

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <span class="fw-semibold">Students ({{ $students->count() }})</span>
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
                <thead class="table-light">
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Student</th>
                        <th scope="col">Enrollment No.</th>
                        <th scope="col">Attendance Status</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($students as $s)
                @php $existing = $s->attendances->first(); @endphp
                <tr>
                    <td class="text-muted">{{ $loop->iteration }}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div style="width:32px;height:32px;border-radius:50%;background:var(--clr-primary);color:white;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:.78rem;flex-shrink:0">{{ strtoupper(substr($s->user->name,0,1)) }}</div>
                            <span class="fw-semibold" style="font-size:.875rem">{{ $s->user->name }}</span>
                        </div>
                    </td>
                    <td><code>{{ $s->enrollment_number }}</code></td>
                    <td>
                        <div class="d-flex gap-2 flex-wrap">
                            @foreach(['present'=>['success','check-circle-fill'],'absent'=>['danger','x-circle-fill'],'late'=>['warning','clock-fill'],'excused'=>['secondary','shield-check']] as $status=>[$color,$icon])
                            <div class="form-check form-check-inline mb-0">
                                <input aria-label="{{ ucfirst($status) }} attendance for {{ $s->user->name }}" class="form-check-input" type="radio"
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
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Save Attendance</button>
            <a href="{{ route('admin.attendance.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </div>
</form>

@push('scripts')
<script>
function markAll(status) {
    document.querySelectorAll(`input[type=radio][value=${status}]`).forEach(r => r.checked = true);
}
</script>
@endpush
@endsection
