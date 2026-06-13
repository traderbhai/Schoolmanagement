@extends('layouts.admin')
@section('title', 'HOD Dashboard')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-building me-2 text-primary"></i>HOD Dashboard
        @if($department)
            <span class="text-muted fw-normal fs-6 ms-2">— {{ $department->name }}</span>
        @endif
    </h4>
    <div class="d-flex gap-2">
        <a href="{{ route('hod.approvals') }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-check2-circle me-1"></i>View Approvals
            @if($pendingApprovals > 0)<span class="badge bg-danger ms-1">{{ $pendingApprovals }}</span>@endif
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="text-uppercase text-muted fw-semibold mb-1" style="font-size:.72rem;letter-spacing:.04em">HOD Priority</div>
            <h5 class="fw-bold mb-1">{{ $hodPriority['title'] }}</h5>
            <p class="text-muted mb-0">{{ $hodPriority['body'] }}</p>
        </div>
        <a href="{{ $hodPriority['route'] }}" class="btn btn-sm {{ $hodPriority['level'] === 'danger' ? 'btn-danger' : ($hodPriority['level'] === 'warning' ? 'btn-warning' : 'btn-primary') }}">
            <i class="bi bi-arrow-right-circle me-1"></i>{{ $hodPriority['action'] }}
        </a>
    </div>
</div>

{{-- KPI Cards --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-2">
        <div class="kpi-card kpi-cyan">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="bi bi-person-badge-fill"></i></div>
                <div>
                    <div class="kpi-value">{{ $facultyCount }}</div>
                    <div class="kpi-label">Faculty Members</div>
                </div>
            </div>
            <div class="kpi-trend"><i class="bi bi-people me-1"></i>Department staff</div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="kpi-card kpi-green">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="bi bi-mortarboard-fill"></i></div>
                <div>
                    <div class="kpi-value">{{ $studentCount }}</div>
                    <div class="kpi-label">Active Students</div>
                </div>
            </div>
            <div class="kpi-trend up"><i class="bi bi-arrow-up me-1"></i>Enrolled</div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="kpi-card kpi-blue">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="bi bi-book-fill"></i></div>
                <div>
                    <div class="kpi-value">{{ $subjectCount }}</div>
                    <div class="kpi-label">Subjects</div>
                </div>
            </div>
            <div class="kpi-trend"><i class="bi bi-collection me-1"></i>This term</div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <a href="{{ route('hod.approvals') }}" class="text-decoration-none">
            <div class="kpi-card {{ $pendingApprovals > 0 ? 'kpi-red' : 'kpi-blue' }}">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon"><i class="bi bi-check2-circle"></i></div>
                    <div>
                        <div class="kpi-value">{{ $pendingApprovals }}</div>
                        <div class="kpi-label">Pending Approvals</div>
                    </div>
                </div>
                <div class="kpi-trend {{ $pendingApprovals > 0 ? 'up' : '' }}">
                    @if($pendingApprovals > 0)<i class="bi bi-exclamation-circle me-1"></i>Needs attention
                    @else
                    <i class="bi bi-check-all me-1"></i>All clear
                    @endif
                </div>
            </div>
        </a>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="kpi-card {{ $attendancePct < 75 ? 'kpi-red' : 'kpi-green' }}">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="bi bi-calendar-check-fill"></i></div>
                <div>
                    <div class="kpi-value">{{ $attendancePct }}%</div>
                    <div class="kpi-label">Dept. Attendance</div>
                </div>
            </div>
            <div class="kpi-trend {{ $attendancePct >= 75 ? 'up' : 'down' }}">
                <i class="bi bi-arrow-{{ $attendancePct >= 75 ? 'up' : 'down' }} me-1"></i>Last 30 days
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="kpi-card {{ $pendingLeaves > 0 ? 'kpi-amber' : 'kpi-blue' }}">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="bi bi-calendar2-minus-fill"></i></div>
                <div>
                    <div class="kpi-value">{{ $pendingLeaves }}</div>
                    <div class="kpi-label">Pending Leaves</div>
                </div>
            </div>
            <div class="kpi-trend {{ $pendingLeaves > 0 ? 'up' : '' }}">
                @if($pendingLeaves > 0)<i class="bi bi-hourglass-split me-1"></i>Awaiting review
                @else
                <i class="bi bi-check me-1"></i>None pending
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    {{-- Recent Department Exams --}}
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-journal-text me-2 text-primary"></i>Recent Department Exams
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Subject</th><th>Date</th><th>Results</th><th>Avg Marks</th><th>Pass Count</th></tr>
                        </thead>
                        <tbody>
                        @forelse($recentExams as $exam)
                            <tr>
                                <td>
                                    <div class="fw-semibold small">{{ $exam->name }}</div>
                                    <div class="text-muted" style="font-size:.75rem">{{ $exam->subject?->name ?? '—' }}</div>
                                </td>
                                <td class="small">{{ $exam->exam_date ? $exam->exam_date->format('d M Y') : '—' }}</td>
                                <td><span class="badge bg-secondary">{{ $exam->result_count }}</span></td>
                                <td>{{ $exam->avg_marks ?? '—' }}</td>
                                <td>{{ $exam->result_count > 0 ? $exam->pass_count . '/' . $exam->result_count : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">No exams found.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Faculty List --}}
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-people me-2 text-success"></i>Department Faculty
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Name</th><th>Designation</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                        @forelse($faculty as $t)
                            <tr>
                                <td class="small fw-semibold">{{ $t->user?->name ?? '—' }}</td>
                                <td class="small text-muted">{{ $t->designation ?? '—' }}</td>
                                <td><span class="badge bg-success-subtle text-success">Active</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-3">No faculty found.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-transparent fw-semibold"><i class="bi bi-lightning-charge-fill me-2 text-warning"></i>Quick Actions</div>
            <div class="card-body d-flex flex-column gap-2">
                <a href="{{ route('hod.faculty.roster') }}" class="btn btn-sm btn-outline-cyan text-start">
                    <i class="bi bi-person-lines-fill me-2"></i>Faculty Roster
                </a>
                <a href="{{ route('hod.leaves') }}" class="btn btn-sm btn-outline-warning text-start">
                    <i class="bi bi-calendar2-minus me-2"></i>Leave Requests
                    @if($pendingLeaves > 0)<span class="badge bg-warning text-dark ms-1">{{ $pendingLeaves }}</span>@endif
                </a>
                <a href="{{ route('hod.department-performance') }}" class="btn btn-sm btn-outline-success text-start">
                    <i class="bi bi-bar-chart-line me-2"></i>Dept. Performance
                </a>
                <a href="{{ route('hod.grievances.index') }}" class="btn btn-sm btn-outline-danger text-start">
                    <i class="bi bi-exclamation-triangle me-2"></i>Grievances
                </a>
                <a href="{{ route('hod.approvals') }}" class="btn btn-sm btn-outline-primary text-start">
                    <i class="bi bi-check2-circle me-2"></i>Approvals
                    @if($pendingApprovals > 0)<span class="badge bg-danger ms-1">{{ $pendingApprovals }}</span>@endif
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
