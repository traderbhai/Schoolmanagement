@extends('layouts.admin')
@section('title', 'Institutional Reports')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Institutional Reports</h4>
            <span class="text-muted small">Academic Year {{ $currentYear }} — Institution-wide KPIs</span>
        </div>
        <a href="{{ route('director.dashboard') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Dashboard
        </a>
    </div>

    {{-- KPI Summary Row --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-3">
                    <div class="text-muted small mb-1">Total Students</div>
                    <div class="fw-bold" style="font-size:1.8rem;color:#1e40af">{{ $totalStudents }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-3">
                    <div class="text-muted small mb-1">Placement Rate ({{ $currentYear }})</div>
                    <div class="fw-bold" style="font-size:1.8rem;color:#16a34a">{{ $placementRate }}%</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-3">
                    <div class="text-muted small mb-1">Overall Attendance</div>
                    <div class="fw-bold" style="font-size:1.8rem;color:{{ $overallAttendancePct >= 75 ? '#16a34a' : '#dc2626' }}">{{ $overallAttendancePct }}%</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-3">
                    <div class="text-muted small mb-1">Fee Collected ({{ $currentYear }})</div>
                    <div class="fw-bold" style="font-size:1.5rem;color:#7c3aed">
                        ₹{{ number_format($feeCollectedThisYear / 100000, 1) }}L
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        {{-- Placement Summary --}}
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h6 class="fw-semibold mb-0"><i class="bi bi-briefcase me-2 text-success"></i>Placement Summary</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-7 text-muted">Total Students</dt>
                        <dd class="col-5 fw-bold text-end">{{ $totalStudents }}</dd>

                        <dt class="col-7 text-muted">Placed This Year</dt>
                        <dd class="col-5 fw-bold text-success text-end">{{ $placedThisYear }}</dd>

                        <dt class="col-7 text-muted">Total Placed (All Time)</dt>
                        <dd class="col-5 fw-bold text-end">{{ $totalPlaced }}</dd>

                        <dt class="col-7 text-muted">Active Drives</dt>
                        <dd class="col-5 fw-bold text-primary text-end">{{ $activeDrives }}</dd>
                    </dl>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">Placement Rate</span>
                            <span class="fw-semibold text-success">{{ $placementRate }}%</span>
                        </div>
                        <div class="progress" style="height:10px;border-radius:6px">
                            <div class="progress-bar bg-success" style="width:{{ min($placementRate, 100) }}%;border-radius:6px"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Fee Collection --}}
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h6 class="fw-semibold mb-0"><i class="bi bi-cash-stack me-2 text-purple" style="color:#7c3aed"></i>Fee Collection</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-7 text-muted">Collected ({{ $currentYear }})</dt>
                        <dd class="col-5 fw-bold text-end">₹{{ number_format($feeCollectedThisYear) }}</dd>

                        <dt class="col-7 text-muted">Collected ({{ $currentYear - 1 }})</dt>
                        <dd class="col-5 fw-bold text-muted text-end">₹{{ number_format($feeCollectedLastYear) }}</dd>
                    </dl>
                    @if($feeCollectedLastYear > 0)
                    @php
                        $feeGrowth = round((($feeCollectedThisYear - $feeCollectedLastYear) / $feeCollectedLastYear) * 100, 1);
                    @endphp
                    <div class="mt-3 p-2 rounded" style="background:#f8fafc;border:1px solid #e2e8f0">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-graph-up-arrow {{ $feeGrowth >= 0 ? 'text-success' : 'text-danger' }}"></i>
                            <span class="small fw-semibold {{ $feeGrowth >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ $feeGrowth >= 0 ? '+' : '' }}{{ $feeGrowth }}% vs last year
                            </span>
                        </div>
                    </div>
                    @endif
                    <div class="mt-2">
                        <a href="{{ route('accounts.reports') }}" class="btn btn-sm btn-outline-secondary w-100 text-start">
                            <i class="bi bi-arrow-right me-1"></i>Full Fee Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Enrollment by Program --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent border-0 pt-3 pb-0 d-flex justify-content-between align-items-center">
            <h6 class="fw-semibold mb-0"><i class="bi bi-people me-2 text-primary"></i>Enrollment by Program</h6>
            <a href="{{ route('director.programs') }}" class="btn btn-sm btn-outline-secondary">View programs</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" class="ps-3">Program</th>
                            <th scope="col">Code</th>
                            <th scope="col">Enrolled Students</th>
                            <th scope="col">Share</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($enrollmentByProgram as $prog)
                        @php
                            $pct = $totalStudents > 0 ? round(($prog->students_count / $totalStudents) * 100, 1) : 0;
                        @endphp
                        <tr>
                            <td class="ps-3 fw-medium small">{{ $prog->name }}</td>
                            <td><span class="badge bg-secondary-subtle text-secondary" style="font-size:.7rem">{{ $prog->code }}</span></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="fw-bold">{{ $prog->students_count }}</span>
                                    <div class="progress flex-grow-1" style="height:6px;border-radius:4px;max-width:120px">
                                        <div class="progress-bar bg-primary" style="width:{{ $pct }}%;border-radius:4px"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-muted small">{{ $pct }}%</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">No programs found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Flags & Quick Links --}}
    <div class="row g-4">
        @if($lowEnrollmentPrograms->isNotEmpty())
        <div class="col-md-6">
            <div class="card border-warning shadow-sm">
                <div class="card-header bg-warning-subtle border-0 pt-3 pb-0">
                    <h6 class="fw-semibold mb-0 text-warning-emphasis">
                        <i class="bi bi-exclamation-triangle me-2"></i>Programs with Low Enrollment
                    </h6>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-2">Programs with fewer than 5 enrolled students:</p>
                    <ul class="list-unstyled mb-0">
                        @foreach($lowEnrollmentPrograms as $prog)
                        <li class="d-flex justify-content-between align-items-center py-1 border-bottom">
                            <span class="small">{{ $prog->name }}</span>
                            <span class="badge bg-warning text-dark">{{ $prog->students_count }} students</span>
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
        @endif

        <div class="{{ $lowEnrollmentPrograms->isNotEmpty() ? 'col-md-6' : 'col-md-12' }}">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h6 class="fw-semibold mb-0"><i class="bi bi-grid me-2 text-secondary"></i>Quick Links to Reports</h6>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <a href="{{ route('accounts.reports') }}" class="btn btn-outline-secondary text-start btn-sm w-100">
                                <i class="bi bi-cash-stack me-2"></i>Fee & Revenue
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="{{ route('cmc.analytics') }}" class="btn btn-outline-secondary text-start btn-sm w-100">
                                <i class="bi bi-briefcase me-2"></i>Placement Analytics
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="{{ route('admin.attendance.report') }}" class="btn btn-outline-secondary text-start btn-sm w-100">
                                <i class="bi bi-calendar-check me-2"></i>Attendance
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="{{ route('exam-cell.results') }}" class="btn btn-outline-secondary text-start btn-sm w-100">
                                <i class="bi bi-journal-text me-2"></i>Exam Results
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="{{ route('admin.faculty.workload') }}" class="btn btn-outline-secondary text-start btn-sm w-100">
                                <i class="bi bi-person-workspace me-2"></i>Faculty Workload
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="{{ route('admission.reports.index') }}" class="btn btn-outline-secondary text-start btn-sm w-100">
                                <i class="bi bi-person-plus me-2"></i>Admissions
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
