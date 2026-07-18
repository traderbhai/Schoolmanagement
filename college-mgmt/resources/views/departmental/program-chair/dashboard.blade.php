@extends('layouts.admin')
@section('title', 'PMC Dashboard')
@section('page-title', 'Program Management Cell')

@section('content')

{{-- Welcome Header --}}
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1 fw-bold"><i class="bi bi-diagram-3-fill me-2 text-primary"></i>PMC Dashboard</h4>
        <p class="text-muted mb-0 small">Welcome back, <strong>{{ auth()->user()->name }}</strong>
            &mdash; {{ $programs->pluck('name')->join(', ') ?: 'No programs assigned' }}</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('chair.curriculum.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-journal-bookmark me-1"></i>Curriculum
        </a>
        <a href="{{ route('chair.timetable.builder') }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-grid-3x3 me-1"></i>Timetable
        </a>
        <a href="{{ route('chair.students.at-risk') }}" class="btn btn-sm btn-danger">
            <i class="bi bi-exclamation-triangle me-1"></i>At-Risk Students
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="text-uppercase text-muted fw-semibold mb-1" style="font-size:.72rem;letter-spacing:.04em">Program Chair Priority</div>
            <h5 class="fw-bold mb-1">{{ $chairPriority['title'] }}</h5>
            <p class="text-muted mb-0">{{ $chairPriority['body'] }}</p>
        </div>
        <a href="{{ $chairPriority['route'] }}" class="btn btn-sm {{ $chairPriority['level'] === 'danger' ? 'btn-danger' : ($chairPriority['level'] === 'warning' ? 'btn-warning' : 'btn-primary') }}">
            <i class="bi bi-arrow-right-circle me-1"></i>{{ $chairPriority['action'] }}
        </a>
    </div>
</div>

{{-- KPI Row --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-sm-4 col-xl-2">
        <x-ui.kpi-card tone="blue" icon="bi-people-fill" :value="$activeStudents" label="Active Students" trend="Enrolled in programs" trend-icon="bi-mortarboard" />
    </div>
    <div class="col-6 col-sm-4 col-xl-2">
        <x-ui.kpi-card tone="cyan" icon="bi-book-fill" :value="$subjectsThisTerm" label="Subjects This Term" trend="Current term" trend-icon="bi-calendar-range" />
    </div>
    <div class="col-6 col-sm-4 col-xl-2">
        <x-ui.kpi-card
            :tone="$attendancePct >= 75 ? 'green' : 'red'"
            icon="bi-check2-square"
            :value="$attendancePct . '%'"
            label="Attendance"
            :trend="$attendancePct >= 75 ? 'On track' : 'Below 75% threshold'"
            :trend-icon="$attendancePct >= 75 ? 'bi-arrow-up' : 'bi-arrow-down'"
            :trend-tone="$attendancePct >= 75 ? 'up' : 'down'"
        />
    </div>
    <div class="col-6 col-sm-4 col-xl-2">
        <x-ui.kpi-card
            :tone="$atRiskStudents->count() > 0 ? 'red' : 'green'"
            icon="bi-exclamation-triangle-fill"
            :value="$atRiskStudents->count()"
            label="At-Risk"
            :trend="$atRiskStudents->count() > 0 ? 'Needs attention' : 'All clear'"
            :trend-icon="$atRiskStudents->count() > 0 ? 'bi-exclamation-circle' : 'bi-check-all'"
            :trend-tone="$atRiskStudents->count() > 0 ? 'up' : null"
        />
    </div>
    @php $totalPending = $pendingApprovals + $pendingLeaves + $pendingCondonations + $openGrievances; @endphp
    <div class="col-6 col-sm-4 col-xl-2">
        <x-ui.kpi-card
            :tone="$totalPending > 0 ? 'amber' : 'blue'"
            icon="bi-inbox-fill"
            :value="$totalPending"
            label="Pending Items"
            :trend="$totalPending > 0 ? 'Awaiting action' : 'Inbox clear'"
            :trend-icon="$totalPending > 0 ? 'bi-hourglass-split' : 'bi-check'"
            :trend-tone="$totalPending > 0 ? 'up' : null"
        />
    </div>
    <div class="col-6 col-sm-4 col-xl-2">
        <x-ui.kpi-card
            :tone="$avgMarks !== '—' && $avgMarks >= 50 ? 'green' : 'amber'"
            icon="bi-bar-chart-fill"
            :value="$avgMarks"
            label="Avg Score"
            trend="Across all exams"
            trend-icon="bi-graph-up"
        />
    </div>
</div>

{{-- Main 2-column layout --}}
<div class="row g-4">
    <div class="col-lg-8">

        {{-- At-Risk Students --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>At-Risk Students</span>
                <a href="{{ route('chair.students.at-risk') }}" class="btn btn-sm btn-outline-danger">View at-risk students</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light"><tr><th scope="col">Student</th><th scope="col">Batch</th><th scope="col">Program</th><th scope="col">Risk Flags</th></tr></thead>
                    <tbody>
                        @forelse($atRiskStudents as $s)
                        <tr>
                            <td class="fw-semibold">{{ $s->user->name ?? '—' }}</td>
                            <td class="text-muted">{{ $s->batch->name ?? '—' }}</td>
                            <td class="text-muted small">{{ $s->program->name ?? '—' }}</td>
                            <td>
                                @foreach($s->risks as $risk)
                                <span class="badge me-1 bg-{{ $risk==='attendance' ? 'warning text-dark' : ($risk==='arrear' ? 'danger' : 'secondary') }}">{{ ucfirst($risk) }}</span>
                                @endforeach
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">
                            <i class="bi bi-check-circle text-success fs-4 d-block mb-1"></i>No at-risk students detected
                        </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Recent Exams --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-file-earmark-text me-2"></i>Recent Exams</span>
                <a href="{{ route('chair.exams') }}" class="btn btn-sm btn-outline-primary">All Exams</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light"><tr><th scope="col">Exam</th><th scope="col">Subject</th><th scope="col">Date</th><th scope="col">Results</th><th scope="col">Pass Rate</th></tr></thead>
                    <tbody>
                        @forelse($recentExams as $exam)
                        @php $passRate = $exam->result_count > 0 ? round(($exam->pass_count/$exam->result_count)*100) : null; @endphp
                        <tr>
                            <td class="fw-semibold">{{ $exam->name }}</td>
                            <td class="text-muted">{{ $exam->subject->name ?? '—' }}</td>
                            <td class="text-muted">{{ $exam->exam_date?->format('d M Y') ?? '—' }}</td>
                            <td>{{ $exam->pass_count }}/{{ $exam->result_count }}</td>
                            <td>
                                @if($passRate !== null)
                                <span class="badge bg-{{ $passRate>=75?'success':($passRate>=50?'warning text-dark':'danger') }}">{{ $passRate }}%</span>
                                @else
                                <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No exams recorded yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Faculty Workload --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-person-workspace me-2"></i>Faculty Workload</span>
                <a href="{{ route('chair.faculty.workload') }}" class="btn btn-sm btn-outline-primary">Full Report</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light"><tr><th scope="col">Teacher</th><th scope="col">Sessions/Week</th><th scope="col">Load Status</th></tr></thead>
                    <tbody>
                        @forelse($workloadSummary as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row->teacher?->user?->name ?? '—' }}</td>
                            <td>{{ $row->sessions }}</td>
                            <td>
                                @if($row->sessions > 20)<span class="badge bg-danger">Overloaded</span>
                                @elseif($row->sessions < 6)<span class="badge bg-warning text-dark">Underloaded</span>
                                @else<span class="badge bg-success">Normal</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center text-muted py-3">No timetable entries found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <div class="col-lg-4">

        {{-- Pending Inbox --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-bell-fill text-warning me-2"></i>Pending Actions
            </div>
            <div class="list-group list-group-flush">
                <a href="{{ route('chair.approvals') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 px-3">
                    <span class="small"><i class="bi bi-check2-circle text-primary me-2"></i>Admission Approvals</span>
                    <span class="badge {{ $pendingApprovals > 0 ? 'bg-primary' : 'bg-light text-muted' }} rounded-pill">{{ $pendingApprovals }}</span>
                </a>
                <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                    <span class="small"><i class="bi bi-calendar-x text-warning me-2"></i>Leave Requests</span>
                    <span class="badge {{ $pendingLeaves > 0 ? 'bg-warning text-dark' : 'bg-light text-muted' }} rounded-pill">{{ $pendingLeaves }}</span>
                </div>
                <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                    <span class="small"><i class="bi bi-shield-check text-info me-2"></i>Condonations</span>
                    <span class="badge {{ $pendingCondonations > 0 ? 'bg-info text-dark' : 'bg-light text-muted' }} rounded-pill">{{ $pendingCondonations }}</span>
                </div>
                <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                    <span class="small"><i class="bi bi-chat-square-text text-danger me-2"></i>Open Grievances</span>
                    <span class="badge {{ $openGrievances > 0 ? 'bg-danger' : 'bg-light text-muted' }} rounded-pill">{{ $openGrievances }}</span>
                </div>
            </div>
        </div>

        {{-- Timetable Status --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-grid-3x3 me-2"></i>Timetable Status</span>
                <a href="{{ route('chair.timetable.builder') }}" class="btn btn-sm btn-outline-primary">Builder</a>
            </div>
            <div class="list-group list-group-flush">
                @forelse($timetableVersions as $v)
                <div class="list-group-item px-3 py-2 d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small fw-semibold">{{ $v->program->name ?? '—' }}</div>
                        <div class="text-muted" style="font-size:.72rem">
                            {{ $v->batch->name ?? 'All batches' }} &middot; v{{ $v->version_number }}
                            @if($v->effective_from) &middot; {{ $v->effective_from->format('d M Y') }} @endif
                        </div>
                    </div>
                    @if($v->status === 'published')<span class="badge bg-success">Published</span>
                    @elseif($v->status === 'archived')<span class="badge bg-secondary">Archived</span>
                    @else<span class="badge bg-warning text-dark">Draft</span>
                    @endif
                </div>
                @empty
                <div class="list-group-item text-muted small text-center py-3">
                    <i class="bi bi-grid-3x3-gap d-block fs-4 mb-1 opacity-25"></i>No timetable versions yet
                </div>
                @endforelse
            </div>
        </div>

        {{-- Elective Windows --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-list-check me-2"></i>Elective Registration</span>
                <a href="{{ route('chair.curriculum.electives') }}" class="btn btn-sm btn-outline-secondary">Manage Elective Registration</a>
            </div>
            <div class="list-group list-group-flush">
                @forelse($electiveWindows as $w)
                <div class="list-group-item px-3 py-2">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small fw-semibold">{{ $w->program->name ?? '—' }}</span>
                        @php $badge = match($w->status ?? '') { 'open'=>'success','closed'=>'secondary','finalized'=>'primary',default=>'warning text-dark' }; @endphp
                        <span class="badge bg-{{ $badge }}">{{ ucfirst($w->status ?? 'draft') }}</span>
                    </div>
                    <div class="text-muted" style="font-size:.72rem">
                        {{ $w->opens_at->format('d M') }} &mdash; {{ $w->closes_at->format('d M Y') }}
                    </div>
                </div>
                @empty
                <div class="list-group-item text-muted small text-center py-3">No elective windows configured</div>
                @endforelse
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold"><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Quick Actions</div>
            <div class="card-body d-flex flex-column gap-2 p-3">
                <a href="{{ route('chair.curriculum.index') }}" class="btn btn-sm btn-outline-primary text-start">
                    <i class="bi bi-journal-bookmark me-2"></i>Manage Curriculum
                </a>
                <a href="{{ route('chair.timetable.builder') }}" class="btn btn-sm btn-outline-primary text-start">
                    <i class="bi bi-grid-3x3 me-2"></i>Timetable Builder
                </a>
                <a href="{{ route('chair.students.at-risk') }}" class="btn btn-sm btn-outline-danger text-start">
                    <i class="bi bi-exclamation-triangle me-2"></i>At-Risk Students
                </a>
                <a href="{{ route('chair.faculty.workload') }}" class="btn btn-sm btn-outline-secondary text-start">
                    <i class="bi bi-person-workspace me-2"></i>Faculty Workload
                </a>
                <a href="{{ route('chair.reports.subject-performance') }}" class="btn btn-sm btn-outline-success text-start">
                    <i class="bi bi-bar-chart-line me-2"></i>Performance Reports
                </a>
                <a href="{{ route('chair.approvals') }}" class="btn btn-sm btn-outline-warning text-start">
                    <i class="bi bi-check2-circle me-2"></i>Pending Approvals
                    @if($pendingApprovals > 0)<span class="badge bg-warning text-dark ms-1">{{ $pendingApprovals }}</span>@endif
                </a>
            </div>
        </div>

    </div>
</div>

@if($lowAttSubjects->isNotEmpty())
<div class="card border-0 shadow-sm mt-4 border-start border-4 border-warning">
    <div class="card-header bg-warning bg-opacity-10 fw-semibold">
        <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Subjects with Low Attendance (&lt; 75%)
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light"><tr><th scope="col">Subject</th><th scope="col">Code</th><th scope="col">Program</th><th scope="col">Attendance</th></tr></thead>
            <tbody>
                @foreach($lowAttSubjects as $s)
                <tr>
                    <td class="fw-semibold">{{ $s->name }}</td>
                    <td class="font-monospace text-muted">{{ $s->code }}</td>
                    <td class="text-muted">{{ $s->program->name ?? '—' }}</td>
                    <td><span class="badge bg-danger">{{ $s->attendance_pct }}%</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection
