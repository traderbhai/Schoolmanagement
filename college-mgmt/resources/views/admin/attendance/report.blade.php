@extends('layouts.admin')
@section('title', 'Attendance Report')
@section('page-title', 'Attendance Report')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.attendance.index') }}">Attendance</a></li>
    <li class="breadcrumb-item active">Report</li>
@endsection

@section('content')

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center fw-semibold">
        <span>Generate Report</span>
        <a href="{{ route('admin.attendance.export') }}" class="btn btn-sm btn-outline-success"><i class="bi bi-download me-1"></i>Export CSV</a>
    </div>
    <div class="card-body">
        <form class="row g-3 align-items-end" method="GET">
            <div class="col-md-4">
                <label class="form-label">Student <span class="text-danger">*</span></label>
                <select name="student_id" class="form-select form-select-sm">
                    <option value="">Select student…</option>
                    @foreach($students as $s)
                        <option value="{{ $s->id }}" @selected(request('student_id')==$s->id)>{{ $s->user->name }} ({{ $s->enrollment_number }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Semester <span class="text-danger">*</span></label>
                <select name="semester_id" class="form-select form-select-sm">
                    <option value="">Select semester…</option>
                    @foreach($semesters as $s)
                        <option value="{{ $s->id }}" @selected(request('semester_id')==$s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Generate Report</button>
            </div>
        </form>
    </div>
</div>

@if($report)

{{-- Summary stats --}}
@php
    $overallPct = 0;
    $totalClasses = 0;
    $totalPresent = 0;
    foreach($report as $records) {
        $totalClasses += $records->count();
        $totalPresent += $records->whereIn('status',['present','late'])->count();
    }
    $overallPct = $totalClasses > 0 ? round(($totalPresent/$totalClasses)*100) : 0;
@endphp

<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="kpi-card {{ $overallPct >= 75 ? 'kpi-green' : ($overallPct >= 60 ? 'kpi-amber' : 'kpi-red') }}">
            <div class="kpi-label">Overall Attendance</div>
            <div class="kpi-value mt-1">{{ $overallPct }}%</div>
            <div class="kpi-label mt-1">{{ $totalPresent }}/{{ $totalClasses }} classes</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="kpi-card kpi-blue">
            <div class="kpi-label">Subjects Tracked</div>
            <div class="kpi-value mt-1">{{ count($report) }}</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="kpi-card {{ $overallPct >= 75 ? 'kpi-green' : 'kpi-red' }}">
            <div class="kpi-label">Attendance Status</div>
            <div class="kpi-value mt-1">{{ $overallPct >= 75 ? 'Good' : 'Low' }}</div>
            <div class="kpi-label mt-1">{{ $overallPct < 75 ? 'Below 75% threshold' : 'Meeting requirement' }}</div>
        </div>
    </div>
</div>

<div class="row g-3">
    @foreach($report as $subjectName => $records)
    @php
        $total   = $records->count();
        $present = $records->whereIn('status', ['present','late'])->count();
        $pct     = $total > 0 ? round(($present / $total) * 100) : 0;
        $color   = $pct >= 75 ? 'success' : ($pct >= 60 ? 'warning' : 'danger');
        $kpiColor = $pct >= 75 ? 'kpi-green' : ($pct >= 60 ? 'kpi-amber' : 'kpi-red');
    @endphp
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h6 class="fw-bold mb-0">{{ $subjectName }}</h6>
                        <div class="text-muted" style="font-size:.78rem">{{ $total }} classes held</div>
                    </div>
                    <span class="badge bg-{{ $color }} fs-6">{{ $pct }}%</span>
                </div>
                <div class="progress mb-3" style="height:8px;border-radius:4px">
                    <div class="progress-bar bg-{{ $color }}" style="width:{{ $pct }}%;border-radius:4px"></div>
                </div>
                <div class="d-flex gap-3" style="font-size:.82rem">
                    <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Present: {{ $present }}</span>
                    <span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>Absent: {{ $records->where('status','absent')->count() }}</span>
                    <span class="text-warning"><i class="bi bi-clock-fill me-1"></i>Late: {{ $records->where('status','late')->count() }}</span>
                </div>
                @if($pct < 75)
                <div class="alert alert-warning py-1 px-2 mt-2 mb-0" style="font-size:.8rem">
                    <i class="bi bi-exclamation-triangle me-1"></i>Below 75% attendance threshold!
                </div>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>

@elseif(request('student_id'))
<div class="card">
    <div class="card-body">
        <div class="empty-state py-4">
            <div class="empty-icon"><i class="bi bi-search"></i></div>
            <div class="mt-2 fw-semibold">No records found</div>
            <div class="text-muted" style="font-size:.85rem">No attendance records found for the selected criteria.</div>
        </div>
    </div>
</div>
@endif
@endsection
