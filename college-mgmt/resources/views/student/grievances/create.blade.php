@extends('layouts.student')
@section('title', 'Submit Grievance')

@section('content')
<div class="mb-4">
    <a href="{{ route('student.grievances.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
    <h4 class="mt-2 mb-0"><i class="bi bi-chat-square-dots me-2 text-primary"></i>Submit Grievance</h4>
    <p class="text-muted small mb-0">Share enough detail for the college team to assign, investigate, and resolve the issue.</p>
</div>

<div class="card border-0 shadow-sm" style="max-width:700px">
    <div class="card-body">
        <form method="POST" action="{{ route('student.grievances.store') }}">
            @csrf

            <div class="mb-3">
                <label class="form-label">Category <span class="text-danger">*</span></label>
                <select aria-label="Category" name="category" class="form-select @error('category') is-invalid @enderror" required>
                    <option value="">Select Category</option>
                    @foreach(['academic'=>'Academic','financial'=>'Financial','facility'=>'Facility','faculty'=>'Faculty','administrative'=>'Administrative','other'=>'Other'] as $val=>$label)
                        <option value="{{ $val }}" @selected(old('category') === $val)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Priority <span class="text-danger">*</span></label>
                <select aria-label="Priority" name="priority" class="form-select @error('priority') is-invalid @enderror" required>
                    <option value="">Select Priority</option>
                    @foreach(['low'=>'Low','normal'=>'Normal','high'=>'High','urgent'=>'Urgent'] as $val=>$label)
                        <option value="{{ $val }}" @selected(old('priority') === $val)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="form-text">Use urgent only for safety, exam, fee deadline, or serious service-impact issues.</div>
                @error('priority')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Title <span class="text-danger">*</span></label>
                <input aria-label="Short summary of the issue" type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" required maxlength="255" placeholder="Short summary of the issue">
                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Description <span class="text-danger">*</span></label>
                <textarea aria-label="Describe what happened, when it happened, and what help you need." name="description" rows="5" class="form-control @error('description') is-invalid @enderror" required placeholder="Describe what happened, when it happened, and what help you need.">{{ old('description') }}</textarea>
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Submit Grievance</button>
                <a href="{{ route('student.grievances.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
