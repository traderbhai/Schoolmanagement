@extends('layouts.admin')
@section('title', 'Propose Curriculum Change')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center mb-4 gap-3">
        <a href="{{ route('academic.curriculum-changes.index') }}" class="btn btn-outline-secondary btn-sm" aria-label="Back to curriculum changes">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h4 class="fw-bold mb-0">Propose Curriculum Change</h4>
            <span class="text-muted small">Submit a change proposal for Dean review</span>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('academic.curriculum-changes.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Program <span class="text-danger">*</span></label>
                            <select aria-label="Program" name="program_id" class="form-select @error('program_id') is-invalid @enderror" required>
                                <option value="">Select Program</option>
                                @foreach($programs as $p)
                                    <option value="{{ $p->id }}" @selected(old('program_id') == $p->id)>{{ $p->name }}</option>
                                @endforeach
                            </select>
                            @error('program_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Related Subject <span class="text-muted fw-normal">(optional)</span></label>
                            <select aria-label="Subject" name="subject_id" class="form-select @error('subject_id') is-invalid @enderror">
                                <option value="">— None —</option>
                                @foreach($subjects as $s)
                                    <option value="{{ $s->id }}" @selected(old('subject_id') == $s->id)>{{ $s->name }} ({{ $s->code }})</option>
                                @endforeach
                            </select>
                            @error('subject_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Change Type <span class="text-danger">*</span></label>
                            <select aria-label="Change Type" name="change_type" class="form-select @error('change_type') is-invalid @enderror" required>
                                <option value="">Select Type</option>
                                <option value="add_subject" @selected(old('change_type')=='add_subject')>Add Subject</option>
                                <option value="remove_subject" @selected(old('change_type')=='remove_subject')>Remove Subject</option>
                                <option value="modify_credits" @selected(old('change_type')=='modify_credits')>Modify Credits</option>
                                <option value="modify_syllabus" @selected(old('change_type')=='modify_syllabus')>Modify Syllabus</option>
                                <option value="add_elective" @selected(old('change_type')=='add_elective')>Add Elective</option>
                            </select>
                            @error('change_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                            <input aria-label="Brief title for this change" type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                                value="{{ old('title') }}" placeholder="Brief title for this change" required>
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Description / Justification <span class="text-danger">*</span></label>
                            <textarea aria-label="Curriculum change description" name="description" rows="5" class="form-control @error('description') is-invalid @enderror"
                                placeholder="Explain the proposed change and its rationale..." required>{{ old('description') }}</textarea>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send me-1"></i>Submit Proposal
                            </button>
                            <a href="{{ route('academic.curriculum-changes.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
