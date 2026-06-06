@extends('layouts.student')
@section('title', 'Submit Grievance')
@section('page-title', 'Submit a Grievance')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('student.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('student.grievances.index') }}">Grievances</a></li>
    <li class="breadcrumb-item active">Submit</li>
@endsection

@section('content')
<div class="card" style="box-shadow:var(--shadow-sm);max-width:700px">
    <div class="card-header bg-transparent border-0 pt-3 pb-0">
        <h6 class="fw-semibold mb-0">Grievance Form</h6>
        <p class="text-muted small mb-0">Provide accurate details so we can address your concern promptly.</p>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('student.grievances.store') }}">
            @csrf

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Category <span class="text-danger">*</span></label>
                    <select name="category" class="form-select form-select-sm @error('category') is-invalid @enderror" required>
                        <option value="">Select a category</option>
                        @foreach($categories as $cat)
                        <option value="{{ $cat }}" @selected(old('category') === $cat)>{{ ucfirst($cat) }}</option>
                        @endforeach
                    </select>
                    @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Priority <span class="text-danger">*</span></label>
                    <select name="priority" class="form-select form-select-sm @error('priority') is-invalid @enderror" required>
                        <option value="low"    @selected(old('priority') === 'low')>Low</option>
                        <option value="normal" @selected(old('priority', 'normal') === 'normal')>Normal</option>
                        <option value="high"   @selected(old('priority') === 'high')>High</option>
                        <option value="urgent" @selected(old('priority') === 'urgent')>Urgent</option>
                    </select>
                    @error('priority')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label class="form-label small fw-semibold">Subject <span class="text-danger">*</span></label>
                    <input type="text" name="subject" class="form-control form-control-sm @error('subject') is-invalid @enderror"
                           value="{{ old('subject') }}" placeholder="Brief summary of the issue" maxlength="255" required>
                    @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label class="form-label small fw-semibold">Description <span class="text-danger">*</span></label>
                    <textarea name="description" rows="6"
                              class="form-control form-control-sm @error('description') is-invalid @enderror"
                              placeholder="Describe your grievance in detail. Include dates, events, and any supporting context..."
                              maxlength="3000" required>{{ old('description') }}</textarea>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text text-muted" style="font-size:.75rem">Max 3000 characters.</div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary btn-sm px-4">
                    <i class="bi bi-send me-1"></i>Submit Grievance
                </button>
                <a href="{{ route('student.grievances.index') }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
