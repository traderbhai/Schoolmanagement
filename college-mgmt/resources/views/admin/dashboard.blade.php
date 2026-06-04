@extends('layouts.admin')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('content')
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8)">
            <div class="d-flex justify-content-between align-items-start">
                <div><div class="small opacity-75">Active Students</div><div class="fs-2 fw-bold">{{ $stats['students'] }}</div></div>
                <i class="bi bi-people fs-2 opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#10b981,#047857)">
            <div class="d-flex justify-content-between align-items-start">
                <div><div class="small opacity-75">Teachers</div><div class="fs-2 fw-bold">{{ $stats['teachers'] }}</div></div>
                <i class="bi bi-person-badge fs-2 opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#f59e0b,#d97706)">
            <div class="d-flex justify-content-between align-items-start">
                <div><div class="small opacity-75">Departments</div><div class="fs-2 fw-bold">{{ $stats['departments'] }}</div></div>
                <i class="bi bi-building fs-2 opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#8b5cf6,#6d28d9)">
            <div class="d-flex justify-content-between align-items-start">
                <div><div class="small opacity-75">Courses</div><div class="fs-2 fw-bold">{{ $stats['courses'] }}</div></div>
                <i class="bi bi-journal-bookmark fs-2 opacity-50"></i>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-grid-3x3-gap me-2 text-primary"></i>Recent Timetable Entries</span>
                <a href="{{ route('admin.timetable.index') }}" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Subject</th><th>Course</th><th>Teacher</th><th>Room</th><th>Day</th><th>Slot</th></tr></thead>
                    <tbody>
                    @forelse($recentEntries as $e)
                    <tr>
                        <td>{{ $e->subject->name }}</td>
                        <td>{{ $e->course->code }}</td>
                        <td>{{ $e->teacher->user->name }}</td>
                        <td><span class="badge bg-light text-dark">{{ $e->classroom->room_number }}</span></td>
                        <td>{{ $e->day_name }}</td>
                        <td>{{ $e->slot->start_time }} - {{ $e->slot->end_time }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">No timetable entries yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-megaphone me-2 text-warning"></i>Recent Notices</span>
                <a href="{{ route('admin.notices.index') }}" class="btn btn-sm btn-outline-secondary">All</a>
            </div>
            <div class="list-group list-group-flush">
                @forelse($recentNotices as $n)
                <div class="list-group-item py-2">
                    <div class="fw-semibold small">{{ $n->title }}</div>
                    <div class="text-muted" style="font-size:.75rem">{{ $n->publish_date->format('d M Y') }} · {{ ucfirst($n->audience) }}</div>
                </div>
                @empty
                <div class="list-group-item text-muted small py-3 text-center">No notices.</div>
                @endforelse
            </div>
        </div>
        @if($currentSemester)
        <div class="card mt-3">
            <div class="card-body">
                <div class="small text-muted mb-1">Current Semester</div>
                <div class="fw-bold">{{ $currentSemester->name }}</div>
                <div class="small text-muted">{{ $currentSemester->academicYear->name }}</div>
                <div class="small mt-1">{{ $currentSemester->start_date->format('d M') }} – {{ $currentSemester->end_date->format('d M Y') }}</div>
            </div>
        </div>
        @endif
    </div>
</div>

<style>
.chart-card { background:#fff; border-radius:10px; box-shadow:0 1px 4px rgba(0,0,0,.07); padding:1.2rem; }
</style>

<div class="row g-3 mt-1">
    <div class="col-12">
        <div class="text-muted small text-uppercase fw-semibold mb-2" style="letter-spacing:.08em">Analytics</div>
    </div>

    {{-- Attendance Trend --}}
    <div class="col-lg-6">
        <div class="chart-card">
            <div class="fw-semibold small mb-2"><i class="bi bi-check2-square me-1 text-primary"></i>Attendance Trend (Last 14 Days)</div>
            <canvas id="attendanceChart" height="180"></canvas>
        </div>
    </div>

    {{-- Fee Collection --}}
    <div class="col-lg-6">
        <div class="chart-card">
            <div class="fw-semibold small mb-2"><i class="bi bi-cash-coin me-1 text-success"></i>Fee Collection (Last 6 Months)</div>
            <canvas id="feeChart" height="180"></canvas>
        </div>
    </div>

    {{-- Enrollment by Department --}}
    <div class="col-lg-5">
        <div class="chart-card">
            <div class="fw-semibold small mb-2"><i class="bi bi-building me-1 text-warning"></i>Students by Department</div>
            <canvas id="deptChart" height="200"></canvas>
        </div>
    </div>

    {{-- Quick stats --}}
    <div class="col-lg-7">
        <div class="chart-card h-100">
            <div class="fw-semibold small mb-3"><i class="bi bi-lightning me-1 text-danger"></i>Quick Stats</div>
            <div class="row g-2 text-center">
                <div class="col-6"><div class="border rounded p-3"><div class="fs-4 fw-bold text-primary">{{ $totalStudents }}</div><div class="text-muted small">Total Students</div></div></div>
                <div class="col-6"><div class="border rounded p-3"><div class="fs-4 fw-bold text-success">{{ $totalTeachers }}</div><div class="text-muted small">Total Teachers</div></div></div>
                <div class="col-6"><div class="border rounded p-3"><div class="fs-4 fw-bold text-warning">{{ $totalDepartments }}</div><div class="text-muted small">Departments</div></div></div>
                <div class="col-6"><div class="border rounded p-3"><div class="fs-4 fw-bold text-info">{{ $totalCourses }}</div><div class="text-muted small">Courses</div></div></div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const attData = @json($attendanceTrend);
const feeData = @json($feeCollection);
const deptData = @json($enrollmentByDept);

// Attendance trend line chart
new Chart(document.getElementById('attendanceChart'), {
    type: 'line',
    data: {
        labels: attData.map(d => d.date),
        datasets: [{
            label: 'Attendance %',
            data: attData.map(d => d.pct),
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59,130,246,0.1)',
            fill: true,
            tension: 0.3,
            pointRadius: 3,
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { min: 0, max: 100, ticks: { callback: v => v + '%' } } } }
});

// Fee collection bar chart
new Chart(document.getElementById('feeChart'), {
    type: 'bar',
    data: {
        labels: feeData.map(d => d.month),
        datasets: [{
            label: 'Amount Collected (₹)',
            data: feeData.map(d => d.amount),
            backgroundColor: 'rgba(16,185,129,0.7)',
            borderRadius: 4,
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { ticks: { callback: v => '₹' + (v/1000).toFixed(0) + 'k' } } } }
});

// Department doughnut chart
new Chart(document.getElementById('deptChart'), {
    type: 'doughnut',
    data: {
        labels: deptData.map(d => d.name),
        datasets: [{
            data: deptData.map(d => d.student_count),
            backgroundColor: ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6'],
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } } }
});
</script>
@endpush
@endsection
