@extends('layouts.admin')
@section('title', 'Add Course')
@section('page-title', 'Add Course')
@section('content')

<div class="card" style="max-width:660px">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-journal-plus me-2 text-primary"></i>New Course</span>
        <a href="{{ route('admin.courses.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.courses.store') }}">
            @csrf
            <div class="row g-3 mb-3">
                <div class="col-12">
                    <label class="form-label">Department <span class="text-danger">*</span></label>
                    <select name="department_id" class="form-select @error('department_id') is-invalid @enderror" required>
                        <option value="">Select department…</option>
                        @foreach($departments as $d)<option value="{{ $d->id }}" @selected(old('department_id')==$d->id)>{{ $d->name }}</option>@endforeach
                    </select>
                    <div class="form-text">The department offering this programme.</div>
                    @error('department_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-8">
                    <label class="form-label">Course Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                    <div class="form-text">Full name of the programme.</div>
                    @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Code <span class="text-danger">*</span></label>
                    <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code') }}" placeholder="BTCS" required>
                    <div class="form-text">Short code used in reports.</div>
                    @error('code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Duration (Years) <span class="text-danger">*</span></label>
                    <input type="number" name="duration_years" class="form-control" value="{{ old('duration_years', 4) }}" min="1" max="6" required>
                    <div class="form-text">e.g. 4 for a B.Tech programme.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Total Semesters <span class="text-danger">*</span></label>
                    <input type="number" name="total_semesters" class="form-control" value="{{ old('total_semesters', 8) }}" min="1" max="12" required>
                    <div class="form-text">Total number of semester periods.</div>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4 pt-2 border-top">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Save Course</button>
                <a href="{{ route('admin.courses.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
