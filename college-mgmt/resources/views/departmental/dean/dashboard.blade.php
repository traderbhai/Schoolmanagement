@extends('layouts.admin')
@section('title', 'Dean Academics — Dashboard')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-mortarboard-fill me-2 text-primary"></i>Dean Academics Dashboard</h4>
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
                <div class="fs-2 fw-bold text-warning">{{ $attendancePct }}%</div>
                <div class="text-muted small">Attendance</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card text-center border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold text-danger">{{ $totalExams }}</div>
                <div class="text-muted small">Exams This Year</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card text-center border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold text-secondary">—</div>
                <div class="text-muted small">Placement Rate</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    {{-- Programs --}}
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold">Program Overview</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Program</th><th>Students</th><th>Batches</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                        @forelse($programs as $prog)
                            <tr>
                                <td>{{ $prog->name }} <span class="badge bg-secondary">{{ $prog->code }}</span></td>
                                <td>{{ $prog->students_count }}</td>
                                <td>{{ $prog->batches_count }}</td>
                                <td><a href="{{ route('dean.students') }}?program_id={{ $prog->id }}" class="btn btn-sm btn-outline-primary">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">No programs found.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Results --}}
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold">Recent Exam Results</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Student</th><th>Exam</th><th>Marks</th></tr>
                        </thead>
                        <tbody>
                        @forelse($recentResults as $r)
                            <tr>
                                <td>{{ $r->student->user->name ?? '—' }}</td>
                                <td>{{ $r->exam->name ?? '—' }}</td>
                                <td>{{ $r->marks_obtained }}/{{ $r->exam->total_marks ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted">No results yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
