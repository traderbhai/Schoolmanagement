@extends('layouts.teacher')

@section('title', 'Upload Study Material')

@section('content')
<div class="container-fluid px-4">

    <div class="d-flex align-items-center mb-3">
        <a href="{{ route('teacher.materials.index') }}" class="btn btn-sm btn-outline-secondary me-3">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h4 class="mb-0"><i class="bi bi-cloud-upload me-2 text-primary"></i>Upload Study Material</h4>
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
            <form method="POST" action="{{ route('teacher.materials.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="row g-3">

                    {{-- Subject --}}
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
                        <select name="subject_id" class="form-select @error('subject_id') is-invalid @enderror" required @disabled($actionBlockedReason)>
                            <option value="">Select Subject</option>
                            @foreach($subjects as $subject)
                                <option value="{{ $subject->id }}" {{ old('subject_id') == $subject->id ? 'selected' : '' }}>
                                    {{ $subject->name }} ({{ $subject->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('subject_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Type --}}
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Material Type <span class="text-danger">*</span></label>
                        <select name="type" class="form-select @error('type') is-invalid @enderror" required @disabled($actionBlockedReason)>
                            <option value="">Select Type</option>
                            <option value="pre_read"  {{ old('type') === 'pre_read'  ? 'selected' : '' }}>Pre-Read</option>
                            <option value="post_read" {{ old('type') === 'post_read' ? 'selected' : '' }}>Post-Read</option>
                            <option value="notes"     {{ old('type') === 'notes'     ? 'selected' : '' }}>Notes</option>
                            <option value="reference" {{ old('type') === 'reference' ? 'selected' : '' }}>Reference</option>
                            <option value="slides"    {{ old('type') === 'slides'    ? 'selected' : '' }}>Slides</option>
                            <option value="video"     {{ old('type') === 'video'     ? 'selected' : '' }}>Video</option>
                            <option value="other"     {{ old('type') === 'other'     ? 'selected' : '' }}>Other</option>
                        </select>
                        @error('type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Title --}}
                    <div class="col-12">
                        <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" value="{{ old('title') }}"
                               class="form-control @error('title') is-invalid @enderror"
                               placeholder="e.g. Unit 3 - Sorting Algorithms" required @disabled($actionBlockedReason)>
                        @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Description --}}
                    <div class="col-12">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" rows="3"
                                  class="form-control @error('description') is-invalid @enderror"
                                  placeholder="Brief description of this material (optional)" @disabled($actionBlockedReason)>{{ old('description') }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- File Upload --}}
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">File Upload</label>
                        <input type="file" name="file"
                               class="form-control @error('file') is-invalid @enderror" @disabled($actionBlockedReason)>
                        <div class="form-text">Max file size: 20 MB. Accepted: PDF, DOCX, PPTX, XLSX, ZIP, MP4, etc.</div>
                        @error('file')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- External URL --}}
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">External URL</label>
                        <input type="url" name="external_url" value="{{ old('external_url') }}"
                               class="form-control @error('external_url') is-invalid @enderror"
                               placeholder="https://..." @disabled($actionBlockedReason)>
                        <div class="form-text">OR provide a URL instead of uploading a file.</div>
                        @error('external_url')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Published --}}
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_published" id="is_published"
                                   value="1" {{ old('is_published', '1') ? 'checked' : '' }} @disabled($actionBlockedReason)>
                            <label class="form-check-label fw-semibold" for="is_published">
                                Publish immediately (visible to students)
                            </label>
                        </div>
                    </div>

                </div>

                <hr class="mt-4">

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary" @disabled($actionBlockedReason)>
                        <i class="bi bi-cloud-upload me-1"></i> Upload Material
                    </button>
                    <a href="{{ route('teacher.materials.index') }}" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>

            </form>
        </div>
    </div>

</div>
@endsection
