@extends('layouts.student')
@section('title', $assignment->title)

@section('content')
<div class="container py-4" style="max-width:800px">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('student.assignments.index') }}">Assignments</a></li>
            <li class="breadcrumb-item active">{{ Str::limit($assignment->title, 40) }}</li>
        </ol>
    </nav>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h5 class="fw-semibold">{{ $assignment->title }}</h5>
            <p class="text-muted mb-2">{{ $assignment->subject->name }}</p>
            <div class="row g-2 mb-3">
                <div class="col-auto">
                    <span class="badge bg-secondary">Max Marks: {{ $assignment->max_marks }}</span>
                </div>
                <div class="col-auto">
                    <span class="badge {{ $assignment->isOverdue() ? 'bg-danger' : 'bg-success' }}">
                        Due: {{ $assignment->due_at->format('d M Y, h:i A') }}
                    </span>
                </div>
                @if($assignment->allow_late_submission)
                <div class="col-auto"><span class="badge bg-warning text-dark">Late submission allowed</span></div>
                @endif
            </div>
            <div class="mb-3">{!! nl2br(e($assignment->description)) !!}</div>
            @if($assignment->instructions)
            <div class="alert alert-info py-2"><strong>Instructions:</strong> {!! nl2br(e($assignment->instructions)) !!}</div>
            @endif
        </div>
    </div>

    @if($submission && $submission->status === 'graded')
    <div class="card border-success border-0 shadow-sm mb-4 bg-success-subtle">
        <div class="card-body">
            <h6 class="fw-semibold text-success mb-2"><i class="bi bi-check-circle me-2"></i>Graded</h6>
            <div class="fs-4 fw-bold mb-1">{{ $submission->marks_obtained }} / {{ $assignment->max_marks }}</div>
            @if($submission->feedback)
            <p class="mb-0 text-muted">{{ $submission->feedback }}</p>
            @endif
        </div>
    </div>
    @endif

    @if(!$submission || $submission->status === 'not_submitted')
        @if($assignment->isOverdue() && !$assignment->allow_late_submission)
        <div class="alert alert-danger">Submission deadline has passed.</div>
        @else
        <div class="card border-0 shadow-sm">
            <div class="card-header fw-semibold">Submit Assignment</div>
            <div class="card-body">
                <form method="POST" action="{{ route('student.assignments.submit', $assignment) }}" enctype="multipart/form-data">
                    @csrf
                    @if($assignment->isOverdue())
                    <div class="alert alert-warning py-2">Late submission — may attract penalty.</div>
                    @endif
                    <div class="mb-3">
                        <label class="form-label">Your Answer <span class="text-muted">(optional if uploading file)</span></label>
                        <textarea name="answer_text" rows="6" class="form-control @error('answer_text') is-invalid @enderror">{{ old('answer_text') }}</textarea>
                        @error('answer_text')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Upload File <span class="text-muted">(max 10MB)</span></label>
                        <input type="file" name="file" class="form-control @error('file') is-invalid @enderror">
                        @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary">Submit Assignment</button>
                </form>
            </div>
        </div>
        @endif
    @elseif($submission->status === 'submitted')
    <div class="alert alert-success">
        <i class="bi bi-check2-circle me-2"></i>Submitted on {{ $submission->submitted_at->format('d M Y, h:i A') }}.
        @if($submission->is_late) <span class="badge bg-warning text-dark ms-2">Late</span> @endif
    </div>
    @endif
</div>
@endsection
