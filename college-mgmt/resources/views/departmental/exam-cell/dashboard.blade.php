@extends('layouts.admin')
@section('title', 'Exam Cell — Dashboard')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-file-earmark-check me-2 text-primary"></i>Exam Cell Dashboard</h4>
    <a href="{{ route('admin.exams.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>New Exam</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body">
                <div class="fs-2 fw-bold text-primary">{{ $upcoming }}</div>
                <div class="text-muted small">Upcoming Exams</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body">
                <div class="fs-2 fw-bold text-danger">{{ $pending }}</div>
                <div class="text-muted small">Pending Result Entry</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body">
                <div class="fs-2 fw-bold text-success">{{ $withResults }}</div>
                <div class="text-muted small">Results Entered</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body">
                <div class="fs-2 fw-bold text-info">{{ $total }}</div>
                <div class="text-muted small">Total Exams</div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent fw-semibold">Upcoming Exams</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Date</th><th>Exam</th><th>Program</th><th>Subject</th><th>Actions</th></tr>
                </thead>
                <tbody>
                @forelse($upcomingList as $exam)
                    <tr>
                        <td>{{ $exam->exam_date->format('d M Y') }}</td>
                        <td>{{ $exam->name }}</td>
                        <td>{{ $exam->program?->name ?? '—' }}</td>
                        <td>{{ $exam->subject?->name ?? '—' }}</td>
                        <td>
                            <a href="{{ route('exam-cell.grade-sheet', $exam) }}" class="btn btn-sm btn-outline-primary">Grade Sheet</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted">No upcoming exams.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
