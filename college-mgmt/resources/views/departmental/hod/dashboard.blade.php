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
        <x-ui.kpi-card tone="cyan" icon="bi-person-badge-fill" :value="$facultyCount" label="Faculty Members" trend="Department staff" trend-icon="bi-people" />
    </div>
    <div class="col-sm-6 col-lg-2">
        <x-ui.kpi-card tone="green" icon="bi-mortarboard-fill" :value="$studentCount" label="Active Students" trend="Enrolled" trend-icon="bi-arrow-up" trend-tone="up" />
    </div>
    <div class="col-sm-6 col-lg-2">
        <x-ui.kpi-card tone="blue" icon="bi-book-fill" :value="$subjectCount" label="Subjects" trend="This term" trend-icon="bi-collection" />
    </div>
    <div class="col-sm-6 col-lg-2">
        <x-ui.kpi-card href="{{ route('hod.approvals') }}" :tone="$pendingApprovals > 0 ? 'red' : 'blue'" icon="bi-check2-circle" :value="$pendingApprovals" label="Pending Approvals" :trend="$pendingApprovals > 0 ? 'Needs attention' : 'All clear'" :trend-icon="$pendingApprovals > 0 ? 'bi-exclamation-circle' : 'bi-check-all'" :trend-tone="$pendingApprovals > 0 ? 'up' : null" />
    </div>
    <div class="col-sm-6 col-lg-2">
        <x-ui.kpi-card :tone="$attendancePct < 75 ? 'red' : 'green'" icon="bi-calendar-check-fill" value="{{ $attendancePct }}%" label="Dept. Attendance" trend="Last 30 days" :trend-icon="$attendancePct >= 75 ? 'bi-arrow-up' : 'bi-arrow-down'" :trend-tone="$attendancePct >= 75 ? 'up' : 'down'" />
    </div>
    <div class="col-sm-6 col-lg-2">
        <x-ui.kpi-card :tone="$pendingLeaves > 0 ? 'amber' : 'blue'" icon="bi-calendar2-minus-fill" :value="$pendingLeaves" label="Pending Leaves" :trend="$pendingLeaves > 0 ? 'Awaiting review' : 'None pending'" :trend-icon="$pendingLeaves > 0 ? 'bi-hourglass-split' : 'bi-check'" :trend-tone="$pendingLeaves > 0 ? 'up' : null" />
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
