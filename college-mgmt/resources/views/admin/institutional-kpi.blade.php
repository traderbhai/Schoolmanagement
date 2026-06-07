@extends('layouts.admin')
@section('title', 'Institutional KPI Dashboard')
@section('page-title', 'Institutional KPI Dashboard')

@section('content')

{{-- Section 1: KPI Cards --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card kpi-blue">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="kpi-label">Active Students</div>
                    <div class="kpi-value mt-1">{{ $totalStudents }}</div>
                    <div class="kpi-trend mt-2"><i class="bi bi-mortarboard me-1"></i> {{ $totalGraduated }} graduated</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-people-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card kpi-green">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="kpi-label">Faculty</div>
                    <div class="kpi-value mt-1">{{ $totalFaculty }}</div>
                    <div class="kpi-trend mt-2"><i class="bi bi-person-badge me-1"></i> active members</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-person-badge-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card kpi-amber">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="kpi-label">Programs</div>
                    <div class="kpi-value mt-1">{{ $totalPrograms }}</div>
                    <div class="kpi-trend mt-2"><i class="bi bi-journal-bookmark me-1"></i> active programmes</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-journal-bookmark-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card kpi-purple">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="kpi-label">Student:Faculty Ratio</div>
                    <div class="kpi-value mt-1">{{ $studentFacultyRatio }}:1</div>
                    <div class="kpi-trend mt-2"><i class="bi bi-bar-chart me-1"></i> students per faculty</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-bar-chart-fill"></i></div>
            </div>
        </div>
    </div>
</div>
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card kpi-cyan">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="kpi-label">Attendance %</div>
                    <div class="kpi-value mt-1">{{ $attendancePct }}%</div>
                    <div class="kpi-trend mt-2"><i class="bi bi-check2-square me-1"></i> overall attendance</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-check2-square"></i></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card kpi-green">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="kpi-label">Pass Rate %</div>
                    <div class="kpi-value mt-1">{{ $passRate }}%</div>
                    <div class="kpi-trend mt-2"><i class="bi bi-patch-check me-1"></i> exam pass rate</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-patch-check-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card kpi-amber">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="kpi-label">Placement Rate %</div>
                    <div class="kpi-value mt-1">{{ $placementRate }}%</div>
                    <div class="kpi-trend mt-2"><i class="bi bi-briefcase me-1"></i> {{ $placed }} placed</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-briefcase-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card kpi-blue">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="kpi-label">Collection Rate %</div>
                    <div class="kpi-value mt-1">{{ $collectionRate }}%</div>
                    <div class="kpi-trend mt-2"><i class="bi bi-cash-coin me-1"></i> fee collected</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-cash-coin"></i></div>
            </div>
        </div>
    </div>
</div>

{{-- Section 2: Admission Funnel --}}
<div class="card mb-4">
    <div class="card-header">
        <span class="fw-600"><i class="bi bi-funnel me-2 text-primary"></i>Admission Funnel</span>
    </div>
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            @php
                $stages = [
                    ['label'=>'Leads','value'=>$totalLeads,'icon'=>'bi-telephone','color'=>'primary'],
                    ['label'=>'Applicants','value'=>$totalApplicants,'icon'=>'bi-file-earmark-person','color'=>'info'],
                    ['label'=>'Enrolled','value'=>$totalEnrolled,'icon'=>'bi-mortarboard','color'=>'success'],
                    ['label'=>'Placed','value'=>$placed,'icon'=>'bi-briefcase','color'=>'warning'],
                ];
            @endphp
            @foreach($stages as $i => $stage)
                <div class="text-center flex-fill">
                    <div class="rounded-3 p-3 border border-{{ $stage['color'] }} bg-{{ $stage['color'] }} bg-opacity-10">
                        <i class="bi {{ $stage['icon'] }} fs-3 text-{{ $stage['color'] }}"></i>
                        <div class="fw-bold fs-4 mt-1">{{ number_format($stage['value']) }}</div>
                        <div class="text-muted small">{{ $stage['label'] }}</div>
                        @if($i > 0 && $stages[$i-1]['value'] > 0)
                            <div class="badge bg-{{ $stage['color'] }} mt-1">
                                {{ round(($stage['value']/$stages[$i-1]['value'])*100,1) }}% conversion
                            </div>
                        @endif
                    </div>
                </div>
                @if($i < count($stages)-1)
                    <div class="text-muted fs-4 d-none d-md-block"><i class="bi bi-arrow-right"></i></div>
                @endif
            @endforeach
        </div>
    </div>
</div>

{{-- Section 3: Program Health --}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-600"><i class="bi bi-graph-up me-2 text-success"></i>Program Health</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Program</th>
                    <th>Active Students</th>
                    <th style="min-width:200px">Pass Rate</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($programs as $prog)
            @php
                $pr = $prog->pass_rate;
                $barColor = $pr === null ? 'secondary' : ($pr < 60 ? 'danger' : ($pr < 75 ? 'warning' : 'success'));
            @endphp
            <tr>
                <td class="fw-semibold">{{ $prog->name }}</td>
                <td>{{ $prog->students_count }}</td>
                <td>
                    @if($pr !== null)
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height:8px">
                                <div class="progress-bar bg-{{ $barColor }}" style="width:{{ min($pr,100) }}%"></div>
                            </div>
                            <span class="badge bg-{{ $barColor }}">{{ $pr }}%</span>
                        </div>
                    @else
                        <span class="text-muted small">N/A</span>
                    @endif
                </td>
                <td>
                    <a href="{{ route('admin.programs.show', $prog) }}" class="btn btn-sm btn-outline-primary">View</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="4" class="text-center text-muted py-3">No active programs found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Section 4: Batch Enrollment Trend --}}
<div class="card mb-4">
    <div class="card-header">
        <span class="fw-600"><i class="bi bi-layers me-2 text-info"></i>Batch Enrollment Trend (Last 5 Batches)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Batch</th>
                    <th>Program</th>
                    <th>Active Students</th>
                    <th>Start Year</th>
                </tr>
            </thead>
            <tbody>
            @forelse($batchTrend as $batch)
            <tr>
                <td class="fw-semibold">{{ $batch->name }}</td>
                <td>{{ $batch->program->name ?? '—' }}</td>
                <td>
                    <span class="badge bg-primary bg-opacity-10 text-primary fw-semibold">{{ $batch->students_count }}</span>
                </td>
                <td>{{ $batch->start_year ?? '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="text-center text-muted py-3">No batch data available.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Section 5 & 6: Quality Indicators + Financial Summary --}}
<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <span class="fw-600"><i class="bi bi-shield-check me-2 text-warning"></i>Quality Indicators</span>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-danger bg-opacity-10 mb-3">
                    <div>
                        <div class="fw-semibold">Open Grievances</div>
                        <div class="text-muted small">Pending resolution</div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-danger fs-6">{{ $openGrievances }}</span>
                        <a href="{{ route('hod.grievances.index') }}" class="btn btn-sm btn-outline-danger">View</a>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-warning bg-opacity-10">
                    <div>
                        <div class="fw-semibold">Curriculum Changes</div>
                        <div class="text-muted small">Pending approval</div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-warning text-dark fs-6">{{ $pendingCurriculumChanges }}</span>
                        <a href="{{ route('academic.curriculum-changes.index') }}" class="btn btn-sm btn-outline-warning">View</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">
                <span class="fw-600"><i class="bi bi-cash-stack me-2 text-success"></i>Financial Summary</span>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-sm-4">
                        <div class="text-center p-3 rounded-3 bg-light">
                            <div class="text-muted small">Total Demanded</div>
                            <div class="fw-bold fs-5 text-primary">₹{{ number_format($totalDemanded) }}</div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="text-center p-3 rounded-3 bg-light">
                            <div class="text-muted small">Collected</div>
                            <div class="fw-bold fs-5 text-success">₹{{ number_format($totalCollected) }}</div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="text-center p-3 rounded-3 bg-light">
                            <div class="text-muted small">Outstanding</div>
                            <div class="fw-bold fs-5 text-danger">₹{{ number_format($outstanding) }}</div>
                        </div>
                    </div>
                </div>
                <div class="mt-2">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small text-muted">Collection Rate</span>
                        <span class="small fw-semibold">{{ $collectionRate }}%</span>
                    </div>
                    <div class="progress" style="height:12px;border-radius:6px">
                        @php $barCol = $collectionRate >= 75 ? 'success' : ($collectionRate >= 50 ? 'warning' : 'danger'); @endphp
                        <div class="progress-bar bg-{{ $barCol }}" style="width:{{ min($collectionRate,100) }}%"></div>
                    </div>
                </div>
                @if($avgSalary)
                <div class="mt-3 text-muted small">
                    <i class="bi bi-briefcase me-1"></i> Avg. Placement Salary: <strong>₹{{ number_format($avgSalary) }}/yr</strong>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection
