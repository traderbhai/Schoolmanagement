@extends('layouts.teacher')

@section('title', 'Create Assignment')

@section('content')
<div class="container-fluid px-4">

    <div class="d-flex align-items-center mb-3">
        <a href="{{ route('teacher.assignments.index') }}" class="btn btn-sm btn-outline-secondary me-3">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h4 class="mb-0"><i class="bi bi-journal-plus me-2 text-primary"></i>Create Assignment</h4>
    </div>

    @if($currentTerm)
        <div class="alert alert-info py-2 mb-3">
            <i class="bi bi-info-circle me-1"></i>
            Current term: <strong>{{ $currentTerm->name }}</strong>
        </div>
    @endif

    @if($actionBlockedReason)
        <div class="alert alert-warning">
            <i class="bi bi-lock me-1"></i>{{ $actionBlockedReason }}
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('teacher.assignments.store') }}" enctype="multipart/form-data" id="assignment-form">
                @csrf

                <div class="row g-3">

                    {{-- Subject --}}
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
                        <select name="subject_id" class="form-select @error('subject_id') is-invalid @enderror" required @disabled($actionBlockedReason)>
                            <option value="">— Select Subject —</option>
                            @foreach($subjects as $subject)
                                <option value="{{ $subject->id }}" {{ old('subject_id') == $subject->id ? 'selected' : '' }}>
                                    {{ $subject->name }} ({{ $subject->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('subject_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Max Marks --}}
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Max Marks <span class="text-danger">*</span></label>
                        <input type="number" name="max_marks" value="{{ old('max_marks', 100) }}"
                               class="form-control @error('max_marks') is-invalid @enderror"
                               min="1" max="1000" required @disabled($actionBlockedReason)>
                        @error('max_marks')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Due Date --}}
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Due Date & Time <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="due_at" value="{{ old('due_at') }}"
                               class="form-control @error('due_at') is-invalid @enderror" required @disabled($actionBlockedReason)>
                        @error('due_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Title --}}
                    <div class="col-12">
                        <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" value="{{ old('title') }}"
                               class="form-control @error('title') is-invalid @enderror"
                               placeholder="e.g. Assignment 1 - Linked Lists" required @disabled($actionBlockedReason)>
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Description --}}
                    <div class="col-12">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" rows="3"
                                  class="form-control @error('description') is-invalid @enderror"
                                  placeholder="Brief overview of the assignment" @disabled($actionBlockedReason)>{{ old('description') }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Instructions --}}
                    <div class="col-12">
                        <label class="form-label fw-semibold">Instructions</label>
                        <textarea name="instructions" rows="4"
                                  class="form-control @error('instructions') is-invalid @enderror"
                                  placeholder="Step-by-step instructions for students" @disabled($actionBlockedReason)>{{ old('instructions') }}</textarea>
                        @error('instructions')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Late Submission --}}
                    <div class="col-md-6">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="allow_late_submission"
                                   id="allow_late_submission" value="1"
                                   {{ old('allow_late_submission') ? 'checked' : '' }}
                                   onchange="document.getElementById('late-penalty-row').style.display = this.checked ? '' : 'none'" @disabled($actionBlockedReason)>
                            <label class="form-check-label fw-semibold" for="allow_late_submission">
                                Allow Late Submissions
                            </label>
                        </div>
                        <div id="late-penalty-row" style="{{ old('allow_late_submission') ? '' : 'display:none' }}">
                            <label class="form-label small">Late Penalty (%)</label>
                            <input type="number" name="late_penalty_percent"
                                   value="{{ old('late_penalty_percent', 10) }}"
                                   class="form-control form-control-sm @error('late_penalty_percent') is-invalid @enderror"
                                   min="0" max="100" style="max-width:120px;" @disabled($actionBlockedReason)>
                            <div class="form-text">Marks deducted per late submission (0–100%).</div>
                            @error('late_penalty_percent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    {{-- Attachment --}}
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Attachment (optional)</label>
                        <input type="file" name="attachment"
                               class="form-control @error('attachment') is-invalid @enderror" @disabled($actionBlockedReason)>
                        <div class="form-text">Reference file for students (PDF, DOCX, etc.).</div>
                        @error('attachment')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Publish --}}
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_published"
                                   id="is_published" value="1"
                                   {{ old('is_published', '1') ? 'checked' : '' }} @disabled($actionBlockedReason)>
                            <label class="form-check-label fw-semibold" for="is_published">
                                Publish immediately (visible to students)
                            </label>
                        </div>
                    </div>

                </div>

                <hr class="mt-4">

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary" @disabled($actionBlockedReason)>
                        <i class="bi bi-journal-check me-1"></i> Create Assignment
                    </button>
                    <a href="{{ route('teacher.assignments.index') }}" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>

            </form>
        </div>
    </div>

</div>
@endsection
