@extends('layouts.admin')
@section('title', 'Attendance')
@section('page-title', 'Attendance Management')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Attendance</li>
@endsection

@section('content')

<div class="row g-3 mb-4">
    {{-- Mark Attendance --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <span class="fw-semibold"><i class="bi bi-check2-square me-2 text-primary"></i>Mark Attendance</span>
            </div>
            <div class="card-body">
                <p class="text-muted" style="font-size:.84rem">Select a semester, course and date, then load available class periods for that day.</p>

                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Semester</label>
                        <select id="sem-select" class="form-select form-select-sm">
                            <option value="">— Select Semester —</option>
                            @foreach($semesters as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}@if($s->academicYear) ({{ $s->academicYear->year }})@endif</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Course</label>
                        <select id="course-select" class="form-select form-select-sm">
                            <option value="">— Select Course —</option>
                            @foreach($courses as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date</label>
                        <input type="date" id="date-input" class="form-control form-control-sm" value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button id="load-btn" class="btn btn-primary btn-sm w-100">
                            <span id="load-spinner" class="spinner-border spinner-border-sm me-1 d-none" role="status"></span>
                            <i class="bi bi-search me-1"></i>Load Classes
                        </button>
                    </div>
                </div>

                <div id="entry-row" class="row g-3 d-none">
                    <div class="col-md-5">
                        <label class="form-label">Class Period</label>
                        <select id="entry-select" class="form-select form-select-sm">
                            <option value="">— Select Class —</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button id="go-btn" class="btn btn-success btn-sm w-100">
                            <i class="bi bi-check2-square me-1"></i>Mark Attendance
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Attendance Report --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <span class="fw-semibold"><i class="bi bi-bar-chart me-2 text-success"></i>Attendance Report</span>
            </div>
            <div class="card-body">
                <p class="text-muted" style="font-size:.84rem">View attendance percentage for a student across all subjects in a semester.</p>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Student</label>
                        <select id="report-student" class="form-select form-select-sm">
                            <option value="">— Select Student —</option>
                            @foreach($students as $st)
                                <option value="{{ $st->id }}">{{ $st->user->name ?? 'Student #'.$st->id }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Semester</label>
                        <select id="report-semester" class="form-select form-select-sm">
                            <option value="">— Select Semester —</option>
                            @foreach($semesters as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}@if($s->academicYear) ({{ $s->academicYear->year }})@endif</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button id="report-btn" class="btn btn-success btn-sm w-100">
                            <i class="bi bi-bar-chart me-1"></i>View Report
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
const semSelect    = document.getElementById('sem-select');
const courseSelect = document.getElementById('course-select');
const dateInput    = document.getElementById('date-input');
const loadBtn      = document.getElementById('load-btn');
const loadSpinner  = document.getElementById('load-spinner');
const entryRow     = document.getElementById('entry-row');
const entrySelect  = document.getElementById('entry-select');
const goBtn        = document.getElementById('go-btn');

loadBtn.addEventListener('click', async () => {
    const semId    = semSelect.value;
    const courseId = courseSelect.value;
    const date     = dateInput.value;

    if (!semId || !courseId || !date) {
        alert('Please select semester, course and date.');
        return;
    }

    loadBtn.disabled = true;
    loadSpinner.classList.remove('d-none');

    try {
        const res     = await fetch(`{{ route('admin.attendance.entries') }}?semester_id=${semId}&course_id=${courseId}&date=${date}`);
        const entries = await res.json();

        entrySelect.innerHTML = '<option value="">— Select Class —</option>';

        if (entries.length === 0) {
            entrySelect.innerHTML = '<option value="">No classes scheduled on this day</option>';
        } else {
            entries.forEach(e => {
                const opt       = document.createElement('option');
                opt.value       = e.id;
                opt.textContent = e.label;
                entrySelect.appendChild(opt);
            });
        }

        entryRow.classList.remove('d-none');
    } catch (err) {
        alert('Failed to load classes. Please try again.');
        console.error(err);
    } finally {
        loadBtn.disabled = false;
        loadSpinner.classList.add('d-none');
    }
});

goBtn.addEventListener('click', () => {
    const entryId = entrySelect.value;
    const date    = dateInput.value;
    if (!entryId) { alert('Please select a class.'); return; }
    window.location.href = `{{ route('admin.attendance.mark') }}?timetable_entry_id=${entryId}&date=${date}`;
});

document.getElementById('report-btn').addEventListener('click', () => {
    const studentId  = document.getElementById('report-student').value;
    const semesterId = document.getElementById('report-semester').value;
    if (!studentId || !semesterId) { alert('Please select a student and semester.'); return; }
    window.location.href = `{{ route('admin.attendance.report') }}?student_id=${studentId}&semester_id=${semesterId}`;
});
</script>
@endpush
@endsection
