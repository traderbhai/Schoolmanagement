@extends('layouts.admin')
@section('title','Mark Attendance')
@section('page-title','Mark Attendance')
@section('content')
<div class="card mb-3">
    <div class="card-body py-2">
        <span class="fw-semibold">{{ $entry->subject->name }}</span>
        &nbsp;·&nbsp; <span class="text-muted">{{ $entry->course->name }}</span>
        &nbsp;·&nbsp; <span class="badge bg-primary">{{ $entry->day_name }}</span>
        &nbsp;·&nbsp; <span class="text-muted">{{ $request->date }}</span>
    </div>
</div>
<form method="POST" action="{{ route('admin.attendance.store') }}">
    @csrf
    <input type="hidden" name="timetable_entry_id" value="{{ $entry->id }}">
    <input type="hidden" name="date" value="{{ $request->date }}">
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Enrollment No.</th>
                        <th>
                            <div class="d-flex gap-2 align-items-center">
                                Status
                                <button type="button" class="btn btn-xs btn-outline-success" style="font-size:.72rem;padding:2px 8px" onclick="markAll('present')">All Present</button>
                                <button type="button" class="btn btn-xs btn-outline-danger" style="font-size:.72rem;padding:2px 8px" onclick="markAll('absent')">All Absent</button>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                @foreach($students as $s)
                @php $existing = $s->attendances->first(); @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td class="fw-semibold">{{ $s->user->name }}</td>
                    <td><code>{{ $s->enrollment_number }}</code></td>
                    <td>
                        <div class="d-flex gap-2 attendance-btns" data-student="{{ $s->id }}">
                            @foreach(['present'=>'success','absent'=>'danger','late'=>'warning','excused'=>'secondary'] as $status=>$color)
                            <div class="form-check form-check-inline mb-0">
                                <input class="form-check-input" type="radio" name="attendance[{{ $s->id }}]" id="att_{{ $s->id }}_{{ $status }}" value="{{ $status }}" @checked(($existing && $existing->status === $status) || (!$existing && $status === 'present'))>
                                <label class="form-check-label small" for="att_{{ $s->id }}_{{ $status }}">
                                    <span class="badge bg-{{ $color }} text-{{ $color === 'warning' ? 'dark' : 'white' }}">{{ ucfirst($status) }}</span>
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
            <button type="submit" class="btn btn-primary">Save Attendance</button>
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
