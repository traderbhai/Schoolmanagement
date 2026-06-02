@extends('layouts.admin')
@section('title','Attendance')
@section('page-title','Attendance Management')
@section('content')
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-check2-square me-2 text-primary"></i>Mark Attendance</h6>
                <p class="text-muted small">Mark attendance for a specific class/period by selecting the timetable entry and date.</p>
                <form method="GET" action="{{ route('admin.attendance.mark') }}" class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Semester</label>
                        <select name="semester_id" class="form-select form-select-sm" id="semSelect">
                            <option value="">Select semester</option>
                            @foreach($semesters as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Date</label>
                        <input type="date" name="date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Timetable Entry ID</label>
                        <input type="number" name="timetable_entry_id" class="form-control form-control-sm" placeholder="Enter timetable entry ID">
                    </div>
                    <div class="col-auto mt-1"><button class="btn btn-primary btn-sm">Mark Attendance</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-bar-chart me-2 text-success"></i>Attendance Report</h6>
                <p class="text-muted small">View attendance percentage report for a student across all subjects in a semester.</p>
                <a href="{{ route('admin.attendance.report') }}" class="btn btn-success btn-sm">View Report</a>
            </div>
        </div>
    </div>
</div>
@endsection
