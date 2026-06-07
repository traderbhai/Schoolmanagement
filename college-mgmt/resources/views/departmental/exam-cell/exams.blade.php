@extends('layouts.admin')
@section('title', 'Exam Cell — Exams')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-file-earmark-text me-2 text-primary"></i>All Exams</h4>
    <a href="{{ route('exam-cell.exams.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>New Exam</a>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-sm-3">
        <select name="program_id" class="form-select form-select-sm">
            <option value="">All Programs</option>
            @foreach($programs as $p)<option value="{{ $p->id }}" @selected(request('program_id')==$p->id)>{{ $p->name }}</option>@endforeach
        </select>
    </div>
    <div class="col-sm-2">
        <select name="status" class="form-select form-select-sm">
            <option value="">All</option>
            <option value="upcoming" @selected(request('status')=='upcoming')>Upcoming</option>
            <option value="past" @selected(request('status')=='past')>Past</option>
        </select>
    </div>
    <div class="col-auto"><button class="btn btn-sm btn-primary">Filter</button></div>
    <div class="col-auto"><a href="{{ route('exam-cell.exams') }}" class="btn btn-sm btn-outline-secondary">Clear</a></div>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Date</th><th>Exam</th><th>Program</th><th>Subject</th><th>Results</th><th>Actions</th></tr>
                </thead>
                <tbody>
                @forelse($exams as $exam)
                    <tr>
                        <td>{{ $exam->exam_date->format('d M Y') }}</td>
                        <td>{{ $exam->name }}</td>
                        <td>{{ $exam->program?->name ?? '—' }}</td>
                        <td>{{ $exam->subject?->name ?? '—' }}</td>
                        <td><span class="badge bg-{{ $exam->results_count > 0 ? 'success' : 'warning' }}">{{ $exam->results_count }} entered</span></td>
                        <td class="d-flex gap-1 flex-wrap">
                            <a href="{{ route('exam-cell.grade-sheet', $exam) }}" class="btn btn-sm btn-outline-primary py-0 px-2">Grade Sheet</a>
                            <a href="{{ route('exam-cell.exams.edit', $exam) }}" class="btn btn-sm btn-outline-secondary py-0 px-2"><i class="bi bi-pencil"></i></a>
                            <button class="btn btn-sm btn-outline-danger py-0 px-2"
                                data-bs-toggle="modal" data-bs-target="#deleteModal"
                                data-action="{{ route('exam-cell.exams.destroy', $exam) }}"
                                data-name="{{ $exam->name }}">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">No exams found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($exams->hasPages())
    <div class="card-footer bg-transparent">{{ $exams->links() }}</div>
    @endif
</div>
@endsection
