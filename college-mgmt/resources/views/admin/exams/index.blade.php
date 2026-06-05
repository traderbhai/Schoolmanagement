@extends('layouts.admin')
@section('title', 'Exams')
@section('page-title', 'Exams & Schedule')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Exams</li>
@endsection

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h5 class="mb-0 fw-bold">Exams &amp; Schedule</h5>
        <div class="text-muted" style="font-size:.82rem">Schedule and manage examinations</div>
    </div>
    <a href="{{ route('admin.exams.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Schedule Exam
    </a>
</div>

<div class="card mb-4">
    <div class="card-body py-3">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-md-4">
                <label class="form-label form-label-sm mb-1">Semester</label>
                <select name="semester_id" class="form-select form-select-sm">
                    <option value="">All Semesters</option>
                    @foreach($semesters as $s)
                        <option value="{{ $s->id }}" @selected(request('semester_id')==$s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm">Filter</button>
                @if(request('semester_id'))
                    <a href="{{ route('admin.exams.index') }}" class="btn btn-outline-secondary btn-sm ms-1">Clear</a>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Exam Name</th>
                    <th>Subject</th>
                    <th>Course</th>
                    <th>Semester</th>
                    <th>Type</th>
                    <th>Date</th>
                    <th>Marks</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($exams as $e)
            <tr>
                <td class="fw-semibold">{{ $e->name }}</td>
                <td>{{ $e->subject->name }}</td>
                <td><span class="badge bg-light text-dark border">{{ $e->subject->department->code ?? '–' }}</span></td>
                <td>{{ $e->semester->name }}</td>
                <td>
                    <span class="badge {{ match($e->type){'internal'=>'bg-info text-dark','midterm'=>'bg-warning text-dark','final'=>'badge-danger','practical'=>'badge-active','assignment'=>'bg-secondary',default=>'bg-secondary'} }}">
                        {{ ucfirst($e->type) }}
                    </span>
                </td>
                <td>{{ $e->exam_date->format('d M Y') }}</td>
                <td><span class="text-muted">{{ $e->passing_marks }}</span>/{{ $e->total_marks }}</td>
                <td>
                    <div class="d-flex gap-1">
                        <a href="{{ route('admin.exams.show', $e) }}" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('admin.exams.results', $e) }}" class="btn btn-sm btn-outline-success" title="Enter Results"><i class="bi bi-pencil-square"></i></a>
                        <a href="{{ route('admin.exams.edit', $e) }}" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                        <button type="button" class="btn btn-sm btn-outline-danger" title="Delete"
                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                            data-action="{{ route('admin.exams.destroy', $e) }}"
                            data-name="{{ $e->name }}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8">
                    <div class="empty-state py-5">
                        <div class="empty-icon"><i class="bi bi-clipboard-check"></i></div>
                        <div class="mt-2 fw-semibold">No exams scheduled yet</div>
                        <a href="{{ route('admin.exams.create') }}" class="btn btn-primary btn-sm mt-3">Schedule Exam</a>
                    </div>
                </td>
            </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($exams->hasPages())
    <div class="card-footer">{{ $exams->links() }}</div>
    @endif
</div>
@endsection
