@extends('layouts.admin')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@push('styles')
<style>
.quick-action-card { transition: box-shadow .18s, transform .18s, border-color .18s; border-color: var(--clr-border); color: var(--clr-text); }
.quick-action-card:hover { box-shadow: var(--shadow-md); transform: translateY(-1px); border-color: var(--clr-primary); }
.text-xs { font-size: .76rem; }
.text-sm { font-size: .875rem; }
.fw-600 { font-weight: 600; }
</style>
@endpush

@section('content')

@include('admin.partials.setup-sequence')

<div class="alert alert-info border-0 shadow-sm py-2 mb-3">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
        <div>
            <div class="fw-semibold">Admin operating sequence</div>
            <div class="small text-muted">Start with institute health, then use quick actions for setup, governance, finance, academics, and operations.</div>
            <div class="small text-muted mt-1">
                <span class="badge text-bg-light me-1">Owner: Admin / Director</span>
                <span class="badge text-bg-light">Source: institute master data, attendance, fees, notices, exams, and audit logs</span>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-1">
            <span class="badge text-bg-light">1. Check institute KPIs</span>
            <span class="badge text-bg-light">2. Review attendance/fees</span>
            <span class="badge text-bg-light">3. Open quick action</span>
            <span class="badge text-bg-light">4. Verify audit/security</span>
            <span class="badge text-bg-light">5. Export or report</span>
        </div>
    </div>
</div>

{{-- A) KPI Cards Row --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <a href="{{ route('admin.students.index') }}" class="text-decoration-none d-block h-100" aria-label="Open active students source list">
        <div class="kpi-card kpi-blue h-100">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="kpi-label">Active Students</div>
                    <div class="kpi-value mt-1">{{ $stats['students'] }}</div>
                    <div class="kpi-trend up mt-2"><i class="bi bi-arrow-up-short"></i> enrolled this year</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-people-fill"></i></div>
            </div>
        </div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a href="{{ route('admin.teachers.index') }}" class="text-decoration-none d-block h-100" aria-label="Open teachers source list">
        <div class="kpi-card kpi-green h-100">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="kpi-label">Teachers</div>
                    <div class="kpi-value mt-1">{{ $stats['teachers'] }}</div>
                    <div class="kpi-trend up mt-2"><i class="bi bi-arrow-up-short"></i> faculty members</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-person-badge-fill"></i></div>
            </div>
        </div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a href="{{ route('admin.departments.index') }}" class="text-decoration-none d-block h-100" aria-label="Open departments source list">
        <div class="kpi-card kpi-amber h-100">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="kpi-label">Departments</div>
                    <div class="kpi-value mt-1">{{ $stats['departments'] }}</div>
                    <div class="kpi-trend up mt-2"><i class="bi bi-arrow-up-short"></i> academic units</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-building-fill"></i></div>
            </div>
        </div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a href="{{ route('admin.programs.index') }}" class="text-decoration-none d-block h-100" aria-label="Open programs source list">
        <div class="kpi-card kpi-purple h-100">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="kpi-label">Courses</div>
                    <div class="kpi-value mt-1">{{ $stats['courses'] }}</div>
                    <div class="kpi-trend up mt-2"><i class="bi bi-arrow-up-short"></i> programmes offered</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-journal-bookmark-fill"></i></div>
            </div>
        </div>
        </a>
    </div>
</div>

{{-- A2) Extra KPI Row: Attendance, Fees --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <a href="{{ route('admin.attendance.index') }}" class="text-decoration-none d-block h-100" aria-label="Open attendance source list">
        <div class="kpi-card kpi-cyan h-100">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="kpi-label">Today's Attendance</div>
                    <div class="kpi-value mt-1">{{ $todayAttendanceRate }}%</div>
                    <div class="kpi-trend mt-2"><i class="bi bi-check2-square me-1"></i> present today</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-check2-square"></i></div>
            </div>
        </div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a href="{{ route('admin.fees.index') }}" class="text-decoration-none d-block h-100" aria-label="Open fees source list">
        <div class="kpi-card kpi-green h-100">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="kpi-label">Fee Collected</div>
                    <div class="kpi-value mt-1">Rs. {{ number_format($totalFeeCollected) }}</div>
                    <div class="kpi-trend mt-2"><i class="bi bi-arrow-up-short"></i> of Rs. {{ number_format($totalFeeDue) }} due</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-cash-coin"></i></div>
            </div>
            <div class="mt-2">
                <div class="progress" style="height:4px;border-radius:2px;background:rgba(255,255,255,.3)">
                    <div class="progress-bar bg-white" style="width:{{ $feeCollectionRate }}%"></div>
                </div>
                <div class="text-xs mt-1 opacity-85">{{ $feeCollectionRate }}% collection rate</div>
            </div>
        </div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a href="{{ route('admin.notices.index') }}" class="text-decoration-none d-block h-100" aria-label="Open notices source list">
        <div class="kpi-card kpi-amber h-100">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="kpi-label">Active Notices</div>
                    <div class="kpi-value mt-1">{{ $pendingNotices }}</div>
                    <div class="kpi-trend mt-2"><i class="bi bi-megaphone me-1"></i> published</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-megaphone-fill"></i></div>
            </div>
        </div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a href="{{ route('admin.exams.index') }}" class="text-decoration-none d-block h-100" aria-label="Open exams source list">
        <div class="kpi-card kpi-purple h-100">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="kpi-label">Upcoming Exams</div>
                    <div class="kpi-value mt-1">{{ $upcomingExams->count() }}</div>
                    <div class="kpi-trend mt-2"><i class="bi bi-calendar-event me-1"></i> next 14 days</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-file-earmark-text-fill"></i></div>
            </div>
        </div>
        </a>
    </div>
</div>

{{-- B) Today's Snapshot --}}
<div class="card mb-4">
    <div class="card-body py-3">
        <div class="d-flex align-items-center gap-4 flex-wrap">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-calendar-check text-primary fs-5"></i>
                <div>
                    <div class="text-xs text-muted">Today</div>
                    <div class="fw-600 text-sm">{{ now()->format('l, d M Y') }}</div>
                </div>
            </div>
            <div class="vr d-none d-md-block" style="height:36px"></div>
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-grid-3x3-gap text-primary fs-5"></i>
                <div>
                    <div class="text-xs text-muted">Today's Classes</div>
                    <div class="fw-600 text-sm">{{ $recentEntries->where('day_of_week', now()->dayOfWeekIso)->count() }} periods</div>
                </div>
            </div>
            <div class="vr d-none d-md-block" style="height:36px"></div>
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-megaphone text-warning fs-5"></i>
                <div>
                    <div class="text-xs text-muted">Notices Published</div>
                    <div class="fw-600 text-sm">{{ $pendingNotices }} active</div>
                </div>
            </div>
            <div class="vr d-none d-md-block" style="height:36px"></div>
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-mortarboard text-success fs-5"></i>
                <div>
                    <div class="text-xs text-muted">Current Semester</div>
                    <div class="fw-600 text-sm">{{ $currentSemester ? $currentSemester->name : 'Semester not configured' }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- C) Charts Row --}}
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span class="fw-600 text-sm"><i class="bi bi-check2-square me-2 text-primary"></i>Attendance Trend - Last 14 Days</span>
                <div class="d-flex align-items-center gap-2">
                    <span class="d-flex align-items-center gap-1 text-xs text-muted"><span style="width:12px;height:3px;background:#4f46e5;border-radius:2px;display:inline-block"></span> % Present</span>
                </div>
            </div>
            <div class="card-body">
                <canvas id="attendanceChart" height="220"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <span class="fw-600 text-sm"><i class="bi bi-cash-coin me-2 text-success"></i>Fee Collection - Last 6 Months</span>
            </div>
            <div class="card-body">
                <canvas id="feeChart" height="220"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    {{-- Enrollment by Department doughnut --}}
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <span class="fw-600 text-sm"><i class="bi bi-building me-2 text-warning"></i>Students by Department</span>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="deptChart" height="200"></canvas>
            </div>
        </div>
    </div>

    {{-- D) Quick Action Cards --}}
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">
                <span class="fw-600 text-sm"><i class="bi bi-lightning me-2 text-danger"></i>Quick Actions</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <a href="{{ route('admin.students.create') }}" class="card text-decoration-none quick-action-card">
                            <div class="card-body d-flex align-items-center gap-3 py-3">
                                <div class="kpi-icon kpi-blue"><i class="bi bi-person-plus-fill"></i></div>
                                <div>
                                    <div class="fw-600 text-sm">Add Student</div>
                                    <div class="text-xs text-muted">Enroll a new student</div>
                                </div>
                                <i class="bi bi-arrow-right ms-auto text-muted"></i>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6">
                        <a href="{{ route('admin.attendance.index') }}" class="card text-decoration-none quick-action-card">
                            <div class="card-body d-flex align-items-center gap-3 py-3">
                                <div class="kpi-icon kpi-green"><i class="bi bi-check2-square"></i></div>
                                <div>
                                    <div class="fw-600 text-sm">Mark Attendance</div>
                                    <div class="text-xs text-muted">Record today's attendance</div>
                                </div>
                                <i class="bi bi-arrow-right ms-auto text-muted"></i>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6">
                        <a href="{{ route('admin.fees.collect') }}" class="card text-decoration-none quick-action-card">
                            <div class="card-body d-flex align-items-center gap-3 py-3">
                                <div class="kpi-icon kpi-amber"><i class="bi bi-cash-stack"></i></div>
                                <div>
                                    <div class="fw-600 text-sm">Collect Fee</div>
                                    <div class="text-xs text-muted">Record a fee payment</div>
                                </div>
                                <i class="bi bi-arrow-right ms-auto text-muted"></i>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6">
                        <a href="{{ route('admin.notices.create') }}" class="card text-decoration-none quick-action-card">
                            <div class="card-body d-flex align-items-center gap-3 py-3">
                                <div class="kpi-icon kpi-purple"><i class="bi bi-megaphone-fill"></i></div>
                                <div>
                                    <div class="fw-600 text-sm">New Notice</div>
                                    <div class="text-xs text-muted">Post an announcement</div>
                                </div>
                                <i class="bi bi-arrow-right ms-auto text-muted"></i>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6">
                        <a href="{{ route('admin.admissions.create') }}" class="card text-decoration-none quick-action-card">
                            <div class="card-body d-flex align-items-center gap-3 py-3">
                                <div class="kpi-icon kpi-cyan"><i class="bi bi-person-plus-fill"></i></div>
                                <div>
                                    <div class="fw-600 text-sm">Admission Enquiry</div>
                                    <div class="text-xs text-muted">Add a new applicant</div>
                                </div>
                                <i class="bi bi-arrow-right ms-auto text-muted"></i>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Reports & Analytics Quick Links --}}
<div class="card mb-4">
    <div class="card-header">
        <span class="fw-600 text-sm"><i class="bi bi-bar-chart-line me-2 text-success"></i>Reports &amp; Analytics</span>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-sm-6 col-lg-4">
                <a href="{{ route('admin.institutional-kpi') }}" class="card text-decoration-none quick-action-card h-100">
                    <div class="card-body d-flex align-items-center gap-3 py-3">
                        <div style="width:40px;height:40px;background:rgba(79,70,229,.1);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="bi bi-speedometer2 text-primary fs-5"></i>
                        </div>
                        <div>
                            <div class="fw-semibold text-sm">Institutional KPI</div>
                            <div class="text-xs text-muted">Director dashboard</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-4">
                <a href="{{ route('admin.aicte-report') }}" class="card text-decoration-none quick-action-card h-100">
                    <div class="card-body d-flex align-items-center gap-3 py-3">
                        <div style="width:40px;height:40px;background:rgba(16,185,129,.1);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="bi bi-file-earmark-bar-graph text-success fs-5"></i>
                        </div>
                        <div>
                            <div class="fw-semibold text-sm">AICTE Compliance</div>
                            <div class="text-xs text-muted">Compliance report + PDF</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-4">
                <a href="{{ route('cmc.placement-stats') }}" class="card text-decoration-none quick-action-card h-100">
                    <div class="card-body d-flex align-items-center gap-3 py-3">
                        <div style="width:40px;height:40px;background:rgba(245,158,11,.1);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="bi bi-briefcase text-warning fs-5"></i>
                        </div>
                        <div>
                            <div class="fw-semibold text-sm">Placement Stats</div>
                            <div class="text-xs text-muted">Drives &amp; outcomes</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-4">
                <a href="{{ route('academic.transcripts.index') }}" class="card text-decoration-none quick-action-card h-100">
                    <div class="card-body d-flex align-items-center gap-3 py-3">
                        <div style="width:40px;height:40px;background:rgba(6,182,212,.1);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="bi bi-journal-text text-info fs-5"></i>
                        </div>
                        <div>
                            <div class="fw-semibold text-sm">Academic Transcripts</div>
                            <div class="text-xs text-muted">Student grade records</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-4">
                <a href="{{ route('admin.audit.index') }}" class="card text-decoration-none quick-action-card h-100">
                    <div class="card-body d-flex align-items-center gap-3 py-3">
                        <div style="width:40px;height:40px;background:rgba(139,92,246,.1);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="bi bi-shield-check text-purple fs-5" style="color:#8b5cf6 !important;"></i>
                        </div>
                        <div>
                            <div class="fw-semibold text-sm">Audit Log</div>
                            <div class="text-xs text-muted">System activity trail</div>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

{{-- E1) Recent Students + Upcoming Exams --}}
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-600 text-sm"><i class="bi bi-person-plus me-2 text-primary"></i>Recent Enrolments</span>
                <a href="{{ route('admin.students.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Student</th>
                            <th>Enrol No.</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($recentStudents as $rs)
                    <tr>
                        <td>
                            <div class="fw-semibold text-sm">{{ $rs->user->name }}</div>
                            <div class="text-muted" style="font-size:.75rem">{{ $rs->user->email }}</div>
                        </td>
                        <td class="text-sm">{{ $rs->enrollment_number }}</td>
                        <td><span class="badge badge-{{ $rs->status === 'active' ? 'active' : 'pending' }}">{{ ucfirst($rs->status) }}</span></td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3">
                            <div class="empty-state py-3">
                                <div class="empty-icon"><i class="bi bi-people"></i></div>
                                <div class="text-muted text-sm mt-1">No students enrolled yet.</div>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-600 text-sm"><i class="bi bi-calendar-event me-2 text-danger"></i>Upcoming Exams</span>
                <a href="{{ route('admin.exams.index') }}" class="btn btn-sm btn-outline-secondary">All</a>
            </div>
            <div class="list-group list-group-flush">
                @forelse($upcomingExams as $exam)
                <div class="list-group-item py-2 px-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold text-sm">{{ $exam->name }}</div>
                            <div class="text-muted" style="font-size:.75rem">{{ optional($exam->subject)->name ?? 'Subject not assigned' }}</div>
                        </div>
                        <div class="text-end">
                            <div class="badge bg-danger bg-opacity-10 text-danger">{{ $exam->exam_date->format('d M') }}</div>
                        </div>
                    </div>
                </div>
                @empty
                <div class="list-group-item">
                    <div class="empty-state py-3">
                        <div class="empty-icon"><i class="bi bi-calendar-x"></i></div>
                        <div class="text-muted text-sm mt-1">No exams in the next 14 days.</div>
                    </div>
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- E) Bottom Row --}}
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-600 text-sm"><i class="bi bi-grid-3x3-gap me-2 text-primary"></i>Recent Timetable Entries</span>
                <a href="{{ route('admin.timetable.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Subject</th>
                            <th>Course</th>
                            <th>Teacher</th>
                            <th>Room</th>
                            <th>Day</th>
                            <th>Slot</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($recentEntries as $e)
                    <tr>
                        <td class="fw-semibold">{{ $e->subject->name }}</td>
                        <td><span class="badge bg-light text-dark border">{{ $e->course->code }}</span></td>
                        <td>{{ $e->teacher->user->name }}</td>
                        <td><span class="badge bg-secondary">{{ $e->classroom->room_number }}</span></td>
                        <td>{{ $e->day_name }}</td>
                        <td class="text-muted text-sm">{{ $e->slot->start_time }} - {{ $e->slot->end_time }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6">
                            <div class="empty-state py-4">
                                <div class="empty-icon"><i class="bi bi-calendar-x"></i></div>
                                <div class="mt-2 text-muted">No timetable entries yet. Create academic structure, subjects, teachers, classrooms, and timetable slots before publishing schedules.</div>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-600 text-sm"><i class="bi bi-megaphone me-2 text-warning"></i>Recent Notices</span>
                <a href="{{ route('admin.notices.index') }}" class="btn btn-sm btn-outline-secondary">All</a>
            </div>
            <div class="list-group list-group-flush">
                @forelse($recentNotices as $n)
                <div class="list-group-item list-group-item-action py-2 px-3">
                    <div class="fw-semibold text-sm">{{ $n->title }}</div>
                    <div class="text-muted text-xs mt-1">{{ $n->publish_date->format('d M Y') }} - <span class="badge badge-active">{{ ucfirst($n->audience) }}</span></div>
                </div>
                @empty
                <div class="list-group-item">
                    <div class="empty-state py-3">
                        <div class="empty-icon"><i class="bi bi-megaphone"></i></div>
                        <div class="text-muted text-sm">No notices posted yet. Create a notice before staff, students, or parents can see announcements.</div>
                    </div>
                </div>
                @endforelse
            </div>
        </div>

        @if($currentSemester)
        <div class="card mt-3">
            <div class="card-body">
                <div class="text-xs text-muted mb-1 text-uppercase fw-semibold" style="letter-spacing:.06em">Current Semester</div>
                <div class="fw-bold">{{ $currentSemester->name }}</div>
                <div class="text-muted text-sm">{{ $currentSemester->academicYear->name }}</div>
                <div class="text-sm mt-1">
                    <i class="bi bi-calendar-range me-1 text-muted"></i>
                    {{ $currentSemester->start_date->format('d M') }} - {{ $currentSemester->end_date->format('d M Y') }}
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const attData  = @json($attendanceTrend);
const feeData  = @json($feeCollection);
const deptData = @json($enrollmentByDept);

// Attendance trend line chart
new Chart(document.getElementById('attendanceChart'), {
    type: 'line',
    data: {
        labels: attData.map(d => d.date),
        datasets: [{
            label: 'Attendance %',
            data: attData.map(d => d.pct),
            borderColor: '#4f46e5',
            backgroundColor: 'rgba(79,70,229,0.1)',
            fill: true,
            tension: 0.4,
            pointRadius: 3,
            pointBackgroundColor: '#4f46e5',
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { min: 0, max: 100, ticks: { callback: v => v + '%' }, grid: { color: 'rgba(0,0,0,.05)' } },
            x: { grid: { display: false } }
        }
    }
});

// Fee collection bar chart
new Chart(document.getElementById('feeChart'), {
    type: 'bar',
    data: {
        labels: feeData.map(d => d.month),
        datasets: [{
            label: 'Amount Collected (Rs.)',
            data: feeData.map(d => d.amount),
            backgroundColor: 'rgba(16,185,129,0.75)',
            borderRadius: 6,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { ticks: { callback: v => 'Rs. ' + (v/1000).toFixed(0) + 'k' }, grid: { color: 'rgba(0,0,0,.05)' } },
            x: { grid: { display: false } }
        }
    }
});

// Department doughnut chart
new Chart(document.getElementById('deptChart'), {
    type: 'doughnut',
    data: {
        labels: deptData.map(d => d.name),
        datasets: [{
            data: deptData.map(d => d.student_count),
            backgroundColor: ['#4f46e5','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4'],
            borderWidth: 2,
            borderColor: '#fff',
        }]
    },
    options: {
        responsive: true,
        cutout: '60%',
        plugins: { legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 12 } } }
    }
});
</script>
@endpush
@endsection
