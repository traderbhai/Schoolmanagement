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

{{-- KPI Cards --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-2">
        <div class="card text-center border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold text-info">{{ $facultyCount }}</div>
                <div class="text-muted small">Faculty Members</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card text-center border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold text-success">{{ $studentCount }}</div>
                <div class="text-muted small">Active Students</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card text-center border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold text-primary">{{ $subjectCount }}</div>
                <div class="text-muted small">Subjects</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <a href="{{ route('hod.approvals') }}" class="text-decoration-none">
            <div class="card text-center border-0 shadow-sm h-100 {{ $pendingApprovals > 0 ? 'border-danger border' : '' }}">
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
                <div class="fs-2 fw-bold {{ $attendancePct < 75 ? 'text-danger' : 'text-success' }}">{{ $attendancePct }}%</div>
                <div class="text-muted small">Dept. Attendance (30d)</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card text-center border-0 shadow-sm h-100 {{ $pendingLeaves > 0 ? 'border-warning border' : '' }}">
            <div class="card-body">
                <div class="fs-2 fw-bold text-warning">{{ $pendingLeaves }}</div>
                <div class="text-muted small">Pending Leaves</div>
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
            <div class="card-header bg-transparent fw-semibold"><i class="bi bi-lightning me-2 text-warning"></i>Quick Actions</div>
            <div class="card-body d-flex flex-wrap gap-2">
                <a href="{{ route('hod.approvals') }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-check2-circle me-1"></i>View Approvals
                </a>
                <a href="{{ route('academic.subjects') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-book me-1"></i>Department Subjects
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
