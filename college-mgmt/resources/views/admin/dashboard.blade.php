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
@endsection
