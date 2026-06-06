@extends('layouts.admin')
@section('title', 'Dean Academics — Dashboard')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-mortarboard-fill me-2 text-primary"></i>Dean Academics Dashboard</h4>
    <div class="d-flex gap-2">
        <a href="{{ route('dean.approvals') }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-check2-circle me-1"></i>Approvals
            @if($pendingApprovals > 0)<span class="badge bg-danger ms-1">{{ $pendingApprovals }}</span>@endif
        </a>
    </div>
</div>

{{-- KPI Cards --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-2">
        <div class="card text-center border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold text-primary">{{ $totalPrograms }}</div>
                <div class="text-muted small">Programs</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card text-center border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold text-success">{{ $totalStudents }}</div>
                <div class="text-muted small">Active Students</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card text-center border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold text-info">{{ $totalFaculty }}</div>
                <div class="text-muted small">Faculty</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card text-center border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold {{ $attendancePct < 75 ? 'text-danger' : 'text-warning' }}">{{ $attendancePct }}%</div>
                <div class="text-muted small">Attendance</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <a href="{{ route('dean.approvals') }}" class="text-decoration-none">
            <div class="card text-center border-0 shadow-sm h-100 {{ $pendingApprovals > 0 ? 'border border-danger' : '' }}">
                <div class="card-body">
                    <div class="fs-2 fw-bold {{ $pendingApprovals > 0 ? 'text-danger' : 'text-secondary' }}">{{ $pendingApprovals }}</div>
                    <div class="text-muted small">Pending Approvals</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card text-center border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold text-danger">{{ $totalExams }}</div>
                <div class="text-muted small">Exams This Year</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    {{-- Program Health Table --}}
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-bar-chart me-2 text-primary"></i>Program Health
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Program</th><th>Students</th><th>Batches</th><th>Faculty</th><th>Pass Rate</th></tr>
                        </thead>
                        <tbody>
                        @forelse($programs as $prog)
                            <tr>
                                <td>
                                    <div class="fw-semibold small">{{ $prog->name }}</div>
                                    <span class="badge bg-secondary">{{ $prog->code }}</span>
                                </td>
                                <td>{{ $prog->students_count }}</td>
                                <td>{{ $prog->batches_count }}</td>
                                <td>{{ $prog->faculty_count }}</td>
                                <td>
                                    @if($prog->pass_rate !== null)
                                        @php $pr = $prog->pass_rate; $cls = $pr >= 75 ? 'success' : ($pr >= 50 ? 'warning' : 'danger'); @endphp
                                        <span class="badge bg-{{ $cls }}">{{ $pr }}%</span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">No programs found.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Approvals --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-check2-circle me-2 text-warning"></i>Recent Approvals
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                @forelse($recentApprovals as $ap)
                    <li class="list-group-item py-2 px-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small fw-semibold">{{ class_basename($ap->approvable_type) }} #{{ $ap->approvable_id }}</span>
                            <span class="badge bg-{{ $ap->status === 'approved' ? 'success' : ($ap->status === 'rejected' ? 'danger' : 'warning') }}">{{ $ap->status }}</span>
                        </div>
                        <div class="text-muted" style="font-size:.72rem">{{ $ap->created_at->diffForHumans() }}</div>
                    </li>
                @empty
                    <li class="list-group-item text-center text-muted py-3">No approvals.</li>
                @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    {{-- At-Risk Students --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-exclamation-triangle me-2 text-danger"></i>At-Risk Students
                <span class="text-muted fw-normal small">(attendance &lt; 75%, last 30 days)</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Student</th><th>Program</th><th>Attendance</th></tr>
                        </thead>
                        <tbody>
                        @forelse($atRiskStudents as $s)
                            <tr>
                                <td class="small fw-semibold">{{ $s->user?->name ?? '—' }}</td>
                                <td class="small text-muted">{{ $s->program?->name ?? '—' }}</td>
                                <td><span class="badge bg-danger">{{ $s->attendance_pct }}%</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-3">No at-risk students.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Exam Results --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-journal-check me-2 text-success"></i>Recent Exam Results
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Student</th><th>Exam</th><th>Marks</th><th>Result</th></tr>
                        </thead>
                        <tbody>
                        @forelse($recentResults as $r)
                            @php $passing = $r->exam->passing_marks ?? 40; $pass = $r->marks_obtained >= $passing; @endphp
                            <tr>
                                <td class="small">{{ $r->student?->user?->name ?? '—' }}</td>
                                <td class="small text-muted">{{ $r->exam?->name ?? '—' }}</td>
                                <td class="small">{{ $r->marks_obtained }}/{{ $r->exam?->total_marks ?? '—' }}</td>
                                <td><span class="badge bg-{{ $pass ? 'success' : 'danger' }}">{{ $pass ? 'Pass' : 'Fail' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">No results yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Quick Actions --}}
<div class="card border-0 shadow-sm mt-3">
    <div class="card-body d-flex flex-wrap gap-2">
        <a href="{{ route('dean.approvals') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-check2-circle me-1"></i>View Approvals</a>
        <a href="{{ route('dean.programs') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-diagram-3 me-1"></i>Programs</a>
        <a href="{{ route('dean.students') }}" class="btn btn-sm btn-outline-info"><i class="bi bi-people me-1"></i>Students</a>
        <a href="{{ route('dean.attendance') }}" class="btn btn-sm btn-outline-warning"><i class="bi bi-calendar-check me-1"></i>Attendance</a>
        <a href="{{ route('dean.academics') }}" class="btn btn-sm btn-outline-success"><i class="bi bi-mortarboard me-1"></i>Academics</a>
    </div>
</div>
@endsection
