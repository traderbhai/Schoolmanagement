@extends('layouts.admin')
@section('title', 'Program Chair — Dashboard')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">
        <i class="bi bi-diagram-3 me-2 text-primary"></i>Program Chair
        @if($programs->count() > 0)
            <span class="text-muted fw-normal fs-6 ms-2">— {{ $programs->pluck('name')->implode(', ') }}</span>
        @endif
    </h4>
    <div>
        <a href="{{ route('chair.approvals') }}" class="btn btn-sm btn-outline-primary">
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
                <div class="fs-2 fw-bold text-primary">{{ $activeStudents }}</div>
                <div class="text-muted small">Active Students</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card text-center border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold text-info">{{ $subjectsThisTerm }}</div>
                <div class="text-muted small">Subjects This Term</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card text-center border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold text-warning">{{ $examCount }}</div>
                <div class="text-muted small">Exams This Year</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card text-center border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold text-success">{{ $avgMarks }}</div>
                <div class="text-muted small">Avg Marks</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <a href="{{ route('chair.approvals') }}" class="text-decoration-none">
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
                <div class="fs-2 fw-bold {{ $attendancePct < 75 ? 'text-danger' : 'text-success' }}">{{ $attendancePct }}%</div>
                <div class="text-muted small">Attendance %</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    {{-- Recent Exams --}}
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-journal-text me-2 text-primary"></i>Recent Exams
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Exam</th><th>Subject</th><th>Date</th><th>Results</th><th>Passed</th></tr>
                        </thead>
                        <tbody>
                        @forelse($recentExams as $exam)
                            <tr>
                                <td class="small fw-semibold">{{ $exam->name }}</td>
                                <td class="small text-muted">{{ $exam->subject?->name ?? '—' }}</td>
                                <td class="small">{{ $exam->exam_date ? $exam->exam_date->format('d M Y') : '—' }}</td>
                                <td><span class="badge bg-secondary">{{ $exam->result_count }}</span></td>
                                <td>
                                    @if($exam->result_count > 0)
                                        <span class="badge bg-success">{{ $exam->pass_count }}/{{ $exam->result_count }}</span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">No exams yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Low Attendance Subjects --}}
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-exclamation-triangle me-2 text-warning"></i>Low Attendance Subjects
                <span class="text-muted fw-normal small">(&lt; 75%)</span>
            </div>
            <div class="card-body p-0">
                @if($lowAttSubjects->count() > 0)
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Subject</th><th>Program</th><th>Att %</th></tr>
                        </thead>
                        <tbody>
                        @foreach($lowAttSubjects as $subj)
                            <tr>
                                <td class="small fw-semibold">{{ $subj->name }}</td>
                                <td class="small text-muted">{{ $subj->program?->code ?? '—' }}</td>
                                <td><span class="badge bg-danger">{{ $subj->attendance_pct }}%</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                    <div class="text-center text-muted py-3"><i class="bi bi-check-circle text-success me-1"></i>All subjects above 75%</div>
                @endif
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-transparent fw-semibold"><i class="bi bi-lightning me-2 text-warning"></i>Quick Actions</div>
            <div class="card-body d-flex flex-wrap gap-2">
                <a href="{{ route('chair.approvals') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-check2-circle me-1"></i>Approvals</a>
                <a href="{{ route('chair.curriculum') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-book me-1"></i>Curriculum</a>
                <a href="{{ route('chair.students') }}" class="btn btn-sm btn-outline-info"><i class="bi bi-people me-1"></i>Students</a>
                <a href="{{ route('chair.exams') }}" class="btn btn-sm btn-outline-warning"><i class="bi bi-journal me-1"></i>Exams</a>
            </div>
        </div>
    </div>
</div>
@endsection
