@extends('layouts.admin')
@section('title','PMC Dashboard')
@section('page-title','Program Management Dashboard')

@section('content')
<div class="container-fluid py-3">

    {{-- KPI Row --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-2 fw-bold text-primary">{{ $activeStudents }}</div>
                <div class="text-muted small">Active Students</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-2 fw-bold text-success">{{ $subjectsThisTerm }}</div>
                <div class="text-muted small">Subjects This Term</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-2 fw-bold {{ $attendancePct >= 75 ? 'text-success' : 'text-danger' }}">{{ $attendancePct }}%</div>
                <div class="text-muted small">Overall Attendance</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-2 fw-bold text-warning">{{ $pendingApprovals }}</div>
                <div class="text-muted small">Pending Approvals</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- At-risk students --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-exclamation-triangle text-danger me-2"></i>At-Risk Students</span>
                    <a href="{{ route('chair.students.at-risk') }}" class="btn btn-sm btn-outline-danger">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light"><tr><th>Student</th><th>Batch</th><th>Risks</th></tr></thead>
                        <tbody>
                            @forelse($atRiskStudents as $s)
                            <tr>
                                <td class="fw-semibold">{{ $s->user->name }}</td>
                                <td class="text-muted">{{ $s->batch->name ?? '—' }}</td>
                                <td>
                                    @foreach($s->risks as $r)
                                    <span class="badge bg-{{ $r==='attendance'?'warning':($r==='academic'?'danger':($r==='arrear'?'secondary':'info')) }} me-1">{{ ucfirst($r) }}</span>
                                    @endforeach
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="text-muted text-center py-3">No at-risk students</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Faculty workload --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-person-workspace me-2"></i>Faculty Workload (This Term)</span>
                    <a href="{{ route('chair.curriculum.assignments') }}" class="btn btn-sm btn-outline-primary">Manage</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light"><tr><th>Teacher</th><th>Sessions/Week</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse($workloadSummary as $row)
                            <tr>
                                <td class="fw-semibold">{{ $row->teacher?->user?->name ?? '—' }}</td>
                                <td>{{ $row->sessions }}</td>
                                <td>
                                    @if($row->sessions > 20)
                                        <span class="badge bg-danger">Overloaded</span>
                                    @elseif($row->sessions < 8)
                                        <span class="badge bg-warning text-dark">Light</span>
                                    @else
                                        <span class="badge bg-success">OK</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="text-muted text-center py-3">No timetable entries yet</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Timetable status --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-grid-3x3 me-2"></i>Timetable Status</span>
                    <a href="{{ route('chair.timetable.builder') }}" class="btn btn-sm btn-outline-primary">Builder</a>
                </div>
                <div class="list-group list-group-flush">
                    @forelse($timetableVersions as $v)
                    <div class="list-group-item px-3 py-2 d-flex justify-content-between">
                        <div>
                            <div class="small fw-semibold">{{ $v->program->name ?? '—' }}{{ $v->batch ? ' · '.$v->batch->name : '' }}</div>
                            <div class="text-muted" style="font-size:.72rem">v{{ $v->version_number }} · {{ $v->effective_from?->format('d M Y') ?? '—' }}</div>
                        </div>
                        @if($v->status === 'published')
                            <span class="badge bg-success">Published</span>
                        @else
                            <span class="badge bg-warning text-dark">Draft</span>
                        @endif
                    </div>
                    @empty
                    <div class="list-group-item text-muted small text-center py-3">No timetable versions yet</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Elective window status --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-list-check me-2"></i>Elective Registration</span>
                    <a href="{{ route('chair.curriculum.electives') }}" class="btn btn-sm btn-outline-primary">Manage</a>
                </div>
                <div class="list-group list-group-flush">
                    @forelse($electiveWindows as $w)
                    <div class="list-group-item px-3 py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small fw-semibold">{{ $w->program->name ?? '—' }}</span>
                            @php $wbadge = match($w->status) {'open'=>'success','closed'=>'secondary','finalized'=>'primary',default=>'warning'}; @endphp
                            <span class="badge bg-{{ $wbadge }}">{{ ucfirst($w->status) }}</span>
                        </div>
                        <div class="text-muted" style="font-size:.72rem">{{ $w->opens_at->format('d M') }} – {{ $w->closes_at->format('d M Y') }}</div>
                    </div>
                    @empty
                    <div class="list-group-item text-muted small text-center py-3">No elective windows</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Pending actions --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header fw-semibold"><i class="bi bi-bell me-2"></i>Pending Actions</div>
                <div class="list-group list-group-flush">
                    @if($pendingLeaves > 0)
                    <a href="{{ route('chair.students.leaves', ['status'=>'pending']) }}" class="list-group-item list-group-item-action d-flex justify-content-between py-2 px-3">
                        <span class="small"><i class="bi bi-calendar-x me-2 text-warning"></i>Student leaves awaiting approval</span>
                        <span class="badge bg-warning text-dark">{{ $pendingLeaves }}</span>
                    </a>
                    @endif
                    @if($pendingCondonations > 0)
                    <a href="{{ route('chair.students.condonations', ['status'=>'pending']) }}" class="list-group-item list-group-item-action d-flex justify-content-between py-2 px-3">
                        <span class="small"><i class="bi bi-shield-check me-2 text-info"></i>Condonation requests</span>
                        <span class="badge bg-info text-dark">{{ $pendingCondonations }}</span>
                    </a>
                    @endif
                    @if($openGrievances > 0)
                    <a href="{{ route('chair.students.grievances', ['status'=>'open']) }}" class="list-group-item list-group-item-action d-flex justify-content-between py-2 px-3">
                        <span class="small"><i class="bi bi-chat-square-text me-2 text-danger"></i>Open grievances</span>
                        <span class="badge bg-danger">{{ $openGrievances }}</span>
                    </a>
                    @endif
                    @if($pendingApprovals > 0)
                    <a href="{{ route('chair.approvals') }}" class="list-group-item list-group-item-action d-flex justify-content-between py-2 px-3">
                        <span class="small"><i class="bi bi-check2-circle me-2 text-primary"></i>Admission approvals</span>
                        <span class="badge bg-primary">{{ $pendingApprovals }}</span>
                    </a>
                    @endif
                    @if($pendingLeaves == 0 && $pendingCondonations == 0 && $openGrievances == 0 && $pendingApprovals == 0)
                    <div class="list-group-item text-muted small text-center py-3"><i class="bi bi-check-circle text-success me-2"></i>All clear!</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if($lowAttSubjects->isNotEmpty())
    <div class="card border-0 shadow-sm mt-4 border-start border-4 border-warning">
        <div class="card-header fw-semibold text-warning"><i class="bi bi-exclamation-triangle me-2"></i>Subjects with Low Attendance (&lt; 75%)</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light"><tr><th>Subject</th><th>Program</th><th>Attendance %</th></tr></thead>
                <tbody>
                    @foreach($lowAttSubjects as $s)
                    <tr>
                        <td class="fw-semibold">{{ $s->name }}</td>
                        <td class="text-muted">{{ $s->program->name ?? '—' }}</td>
                        <td><span class="badge bg-danger">{{ $s->attendance_pct }}%</span></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>
@endsection
