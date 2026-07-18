@extends('layouts.student')
@section('title', 'My Attendance')
@section('page-title', 'My Attendance')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('student.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">My Attendance</li>
@endsection

@section('content')

{{-- Semester Filter --}}
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('student.attendance') }}" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-1" style="color:var(--clr-text-muted)">Semester</label>
                <select aria-label="Semester" name="semester_id" class="form-select form-select-sm" style="min-width:220px" onchange="this.form.submit()">
                    @foreach($semesters as $sem)
                        <option value="{{ $sem->id }}" @selected($sem->id == $semesterId)>
                            {{ $sem->name }}
                            @if($sem->academicYear) &mdash; {{ $sem->academicYear->name }} @endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
            </div>
        </form>
    </div>
</div>

@php
    $totalClasses  = collect($report)->sum('total');
    $totalPresent  = collect($report)->sum('present');
    $overallPct    = $totalClasses > 0 ? round(($totalPresent / $totalClasses) * 100, 1) : 0;
    $lowCount      = collect($report)->where('low', true)->count();
@endphp

{{-- KPI Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="kpi-card kpi-blue">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="kpi-label">Total Classes</div>
                    <div class="kpi-value">{{ $totalClasses }}</div>
                    <div class="kpi-trend"><i class="bi bi-calendar2-week me-1"></i>This semester</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-calendar2-check-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="kpi-card {{ $overallPct >= 75 ? 'kpi-green' : ($overallPct >= 60 ? 'kpi-amber' : 'kpi-red') }}">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="kpi-label">Overall Attendance</div>
                    <div class="kpi-value">{{ $overallPct }}%</div>
                    <div class="kpi-trend">
                        <i class="bi bi-{{ $overallPct >= 75 ? 'check-circle' : 'exclamation-triangle' }} me-1"></i>
                        {{ $overallPct >= 75 ? 'Above threshold' : 'Below 75% required' }}
                    </div>
                </div>
                <div class="kpi-icon"><i class="bi bi-person-check-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="kpi-card {{ $lowCount > 0 ? 'kpi-red' : 'kpi-green' }}">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="kpi-label">Subjects Below 75%</div>
                    <div class="kpi-value">{{ $lowCount }}</div>
                    <div class="kpi-trend">
                        <i class="bi bi-{{ $lowCount > 0 ? 'exclamation-triangle-fill' : 'check2-circle' }} me-1"></i>
                        {{ $lowCount > 0 ? 'Action needed' : 'All subjects clear' }}
                    </div>
                </div>
                <div class="kpi-icon"><i class="bi bi-{{ $lowCount > 0 ? 'exclamation-triangle-fill' : 'shield-check' }}"></i></div>
            </div>
        </div>
    </div>
</div>

{{-- Below-75% Warning Alert --}}
@if($lowCount > 0)
<div class="alert alert-danger d-flex align-items-start gap-3 mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill fs-4 flex-shrink-0 mt-1"></i>
    <div>
        <strong class="d-block mb-1">Attendance Warning</strong>
        You are below the required <strong>75%</strong> attendance threshold in
        <strong>{{ $lowCount }} subject{{ $lowCount > 1 ? 's' : '' }}</strong>.
        You are at risk of being detained. Please attend all upcoming classes regularly.
    </div>
</div>
@endif

{{-- Subject Attendance Cards --}}
@if(count($report) > 0)
<div class="row g-3">
    @foreach($report as $row)
    @php
        $color   = $row['pct'] >= 75 ? 'success' : ($row['pct'] >= 60 ? 'warning' : 'danger');
        $bsColor = $row['pct'] >= 75 ? '#10b981' : ($row['pct'] >= 60 ? '#f59e0b' : '#ef4444');
        $borderCls = $row['pct'] >= 75 ? 'border-success' : ($row['pct'] >= 60 ? 'border-warning' : 'border-danger');
        $badgeCls  = $row['pct'] >= 75 ? 'bg-success' : ($row['pct'] >= 60 ? 'bg-warning text-dark' : 'bg-danger');
    @endphp
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 border-start border-3 {{ $borderCls }}" style="box-shadow:var(--shadow-sm)">
            <div class="card-body">
                {{-- Subject Header --}}
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h6 class="card-title mb-0 fw-bold" style="font-size:.88rem;line-height:1.3">
                        {{ $row['subject'] }}
                    </h6>
                    <span class="badge {{ $badgeCls }} ms-2 flex-shrink-0" style="font-size:.78rem">
                        {{ $row['pct'] }}%
                    </span>
                </div>

                {{-- Progress Bar --}}
                <div class="progress mb-1" style="height:10px;border-radius:6px;background:#e9ecef">
                    <div class="progress-bar bg-{{ $color }}"
                         role="progressbar"
                         style="width:{{ min($row['pct'],100) }}%;border-radius:6px"
                         aria-valuenow="{{ $row['pct'] }}"
                         aria-valuemin="0"
                         aria-valuemax="100">
                    </div>
                </div>

                {{-- 75% threshold marker --}}
                <div class="position-relative mb-3" style="height:16px">
                    <div style="position:absolute;left:75%;top:-10px;border-left:2px dashed #94a3b8;height:18px;opacity:.5"></div>
                    <small class="text-muted" style="position:absolute;left:calc(75% + 3px);top:-10px;font-size:.6rem;line-height:1">75%</small>
                </div>

                {{-- Stats Row --}}
                <div class="row g-0 text-center" style="font-size:.75rem">
                    <div class="col border-end">
                        <div class="fw-bold text-success">{{ $row['present'] }}</div>
                        <div class="text-muted">Present</div>
                    </div>
                    <div class="col border-end">
                        <div class="fw-bold text-danger">{{ $row['absent'] }}</div>
                        <div class="text-muted">Absent</div>
                    </div>
                    <div class="col border-end">
                        <div class="fw-bold text-warning">{{ $row['late'] }}</div>
                        <div class="text-muted">Late</div>
                    </div>
                    <div class="col">
                        <div class="fw-bold text-secondary">{{ $row['total'] }}</div>
                        <div class="text-muted">Total</div>
                    </div>
                </div>

                @if($row['low'])
                <div class="mt-3 d-flex align-items-center gap-1 p-2 rounded" style="background:#fef2f2;font-size:.75rem">
                    <i class="bi bi-exclamation-circle-fill text-danger"></i>
                    <span class="text-danger fw-semibold">Below required attendance threshold</span>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>

@else
{{-- Empty State --}}
<div class="card">
    <div class="card-body">
        <div class="empty-state py-5">
            <div class="empty-icon"><i class="bi bi-calendar-x" style="font-size:3rem;color:var(--clr-text-muted)"></i></div>
            <h5 class="mt-3" style="color:var(--clr-text-muted)">No Attendance Records</h5>
            <p class="mb-0" style="color:var(--clr-text-muted);font-size:.88rem">
                @if($semesterId)
                    No attendance data found for the selected semester.
                @else
                    Please select a semester to view attendance records.
                @endif
            </p>
        </div>
    </div>
</div>
@endif

@endsection
