@extends('layouts.admin')
@section('title','Attendance Report')
@section('page-title','Attendance Report')
@section('content')
<div class="card mb-4">
    <div class="card-body">
        <form class="row g-3 align-items-end" method="GET">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Student *</label>
                <select name="student_id" class="form-select">
                    <option value="">Select student</option>
                    @foreach($students as $s)<option value="{{ $s->id }}" @selected(request('student_id')==$s->id)>{{ $s->user->name }} ({{ $s->enrollment_number }})</option>@endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Semester *</label>
                <select name="semester_id" class="form-select">
                    <option value="">Select semester</option>
                    @foreach($semesters as $s)<option value="{{ $s->id }}" @selected(request('semester_id')==$s->id)>{{ $s->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-primary">Generate Report</button></div>
        </form>
    </div>
</div>

@if($report)
<div class="row g-3">
    @foreach($report as $subjectName => $records)
    @php
        $total   = $records->count();
        $present = $records->whereIn('status', ['present','late'])->count();
        $pct     = $total > 0 ? round(($present / $total) * 100) : 0;
        $color   = $pct >= 75 ? 'success' : ($pct >= 60 ? 'warning' : 'danger');
    @endphp
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h6 class="fw-bold mb-0">{{ $subjectName }}</h6>
                        <small class="text-muted">{{ $total }} classes held</small>
                    </div>
                    <span class="badge bg-{{ $color }} fs-6">{{ $pct }}%</span>
                </div>
                <div class="progress mb-2" style="height:8px">
                    <div class="progress-bar bg-{{ $color }}" style="width:{{ $pct }}%"></div>
                </div>
                <div class="d-flex gap-3 small text-muted">
                    <span><i class="bi bi-check-circle text-success me-1"></i>Present: {{ $present }}</span>
                    <span><i class="bi bi-x-circle text-danger me-1"></i>Absent: {{ $records->where('status','absent')->count() }}</span>
                    <span><i class="bi bi-clock text-warning me-1"></i>Late: {{ $records->where('status','late')->count() }}</span>
                </div>
                @if($pct < 75)
                <div class="alert alert-warning py-1 px-2 mt-2 mb-0 small"><i class="bi bi-exclamation-triangle me-1"></i>Below 75% attendance threshold!</div>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>
@elseif(request('student_id'))
<div class="alert alert-info">No attendance records found for the selected criteria.</div>
@endif
@endsection
