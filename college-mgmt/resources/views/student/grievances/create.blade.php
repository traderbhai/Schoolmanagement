@extends('layouts.student')
@section('title', 'Submit Grievance')

@section('content')
<div class="mb-4">
    <a href="{{ route('student.grievances.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
    <h4 class="mt-2 mb-0"><i class="bi bi-chat-square-dots me-2 text-primary"></i>Submit Grievance</h4>
</div>

<div class="card border-0 shadow-sm" style="max-width:650px">
    <div class="card-body">
        <form method="POST" action="{{ route('student.grievances.store') }}">
            @csrf

            <div class="mb-3">
                <label class="form-label">Category <span class="text-danger">*</span></label>
                <select name="category" class="form-select @error('category') is-invalid @enderror" required>
                    <option value="">— Select Category —</option>
                    @foreach(['academic'=>'Academic','financial'=>'Financial','facility'=>'Facility','faculty'=>'Faculty','administrative'=>'Administrative','other'=>'Other'] as $val=>$label)
                        <option value="{{ $val }}" @selected(old('category') === $val)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Priority <span class="text-danger">*</span></label>
                <select name="priority" class="form-select @error('priority') is-invalid @enderror" required>
                    <option value="">— Select Priority —</option>
                    @foreach(['low'=>'Low','normal'=>'Normal','high'=>'High','urgent'=>'Urgent'] as $val=>$label)
                        <option value="{{ $val }}" @selected(old('priority') === $val)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('priority')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Title <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                    value="{{ old('title') }}" required maxlength="255">
                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Description <span class="text-danger">*</span></label>
                <textarea name="description" rows="5" class="form-control @error('description') is-invalid @enderror" required>{{ old('description') }}</textarea>
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
