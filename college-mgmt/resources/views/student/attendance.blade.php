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
                <label class="form-label small fw-semibold mb-1 text-muted">Semester</label>
                <select name="semester_id" class="form-select form-select-sm" style="min-width:220px" onchange="this.form.submit()">
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

{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card" style="background: linear-gradient(135deg,#3b82f6,#1d4ed8)">
            <div class="d-flex align-items-center gap-3">
                <div class="fs-2"><i class="bi bi-calendar2-check"></i></div>
                <div>
                    <div class="fs-3 fw-bold">{{ $totalClasses }}</div>
                    <div class="small opacity-75">Total Classes</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card" style="background: linear-gradient(135deg,{{ $overallPct >= 75 ? '#10b981,#047857' : ($overallPct >= 60 ? '#f59e0b,#b45309' : '#ef4444,#b91c1c') }})">
            <div class="d-flex align-items-center gap-3">
                <div class="fs-2"><i class="bi bi-person-check"></i></div>
                <div>
                    <div class="fs-3 fw-bold">{{ $overallPct }}%</div>
                    <div class="small opacity-75">Overall Attendance</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card" style="background: linear-gradient(135deg,{{ $lowCount > 0 ? '#f97316,#c2410c' : '#6366f1,#4338ca' }})">
            <div class="d-flex align-items-center gap-3">
                <div class="fs-2"><i class="bi bi-exclamation-triangle{{ $lowCount > 0 ? '-fill' : '' }}"></i></div>
                <div>
                    <div class="fs-3 fw-bold">{{ $lowCount }}</div>
                    <div class="small opacity-75">Subjects Below 75%</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Below-75% Warning --}}
@if($lowCount > 0)
<div class="alert alert-warning d-flex align-items-center gap-2 mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill fs-5 text-warning"></i>
    <div>
        <strong>Attendance Warning:</strong> You are below the required <strong>75%</strong> attendance in
        <strong>{{ $lowCount }} subject{{ $lowCount > 1 ? 's' : '' }}</strong>.
        Risk of being detained. Please attend classes regularly.
    </div>
</div>
@endif

{{-- Subject Cards --}}
@if(count($report) > 0)
<div class="row g-3">
    @foreach($report as $row)
    @php
        $color = $row['pct'] >= 75 ? 'success' : ($row['pct'] >= 60 ? 'warning' : 'danger');
        $bgClass = $row['pct'] >= 75 ? 'border-success' : ($row['pct'] >= 60 ? 'border-warning' : 'border-danger');
        $badge = $row['pct'] >= 75 ? 'bg-success' : ($row['pct'] >= 60 ? 'bg-warning text-dark' : 'bg-danger');
    @endphp
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 border-start border-3 {{ $bgClass }}">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="card-title mb-0 fw-bold" style="font-size:.9rem">{{ $row['subject'] }}</h6>
                    <span class="badge {{ $badge }} ms-2">{{ $row['pct'] }}%</span>
                </div>

                <div class="progress mb-2" style="height:10px; border-radius:6px">
                    <div class="progress-bar bg-{{ $color }}"
                         role="progressbar"
                         style="width:{{ $row['pct'] }}%"
                         aria-valuenow="{{ $row['pct'] }}"
                         aria-valuemin="0"
                         aria-valuemax="100">
                    </div>
                </div>

                {{-- 75% threshold marker --}}
                <div class="position-relative mb-3" style="height:4px">
                    <div style="position:absolute;left:75%;top:-14px;border-left:2px dashed #94a3b8;height:20px;opacity:.6"></div>
                    <small class="text-muted" style="position:absolute;left:calc(75% + 3px);top:-14px;font-size:.65rem">75%</small>
                </div>

                <div class="row g-0 text-center mt-2" style="font-size:.78rem">
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
                <div class="mt-2 text-center">
                    <small class="text-danger fw-semibold"><i class="bi bi-exclamation-circle me-1"></i>Below required attendance</small>
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
    <div class="card-body text-center py-5">
        <i class="bi bi-calendar-x text-muted" style="font-size:3rem"></i>
        <h5 class="mt-3 text-muted">No Attendance Records</h5>
        <p class="text-muted small mb-0">
            @if($semesterId)
                No attendance data found for the selected semester.
            @else
                Please select a semester to view attendance records.
            @endif
        </p>
    </div>
</div>
@endif

@endsection
