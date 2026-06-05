@extends('layouts.admin')
@section('title', 'Edit Course')
@section('page-title', 'Edit Course')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.courses.index') }}">Courses</a></li>
    <li class="breadcrumb-item active">Edit Course</li>
@endsection

@section('content')

<div class="card" style="max-width:660px">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-pencil me-2 text-primary"></i>Edit Course — {{ $course->name }}</span>
        <a href="{{ route('admin.courses.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.courses.update', $course) }}">
            @csrf @method('PUT')
            <div class="row g-3 mb-3">
                <div class="col-12">
                    <label class="form-label">Department <span class="text-danger">*</span></label>
                    <select name="department_id" class="form-select @error('department_id') is-invalid @enderror" required>
                        @foreach($departments as $d)<option value="{{ $d->id }}" @selected($d->id==old('department_id',$course->department_id))>{{ $d->name }}</option>@endforeach
                    </select>
                    @error('department_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-8">
                    <label class="form-label">Course Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $course->name) }}" required>
                    @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Code <span class="text-danger">*</span></label>
                    <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $course->code) }}" required>
                    @error('code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Duration (Years)</label>
                    <input type="number" name="duration_years" class="form-control" value="{{ old('duration_years', $course->duration_years) }}" min="1" max="6">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Total Semesters</label>
                    <input type="number" name="total_semesters" class="form-control" value="{{ old('total_semesters', $course->total_semesters) }}" min="1" max="12">
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description', $course->description) }}</textarea>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="is_active" value="1" @checked(old('is_active', $course->is_active))>
                        <label class="form-check-label" for="is_active">Course is active</label>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4 pt-2 border-top">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Update Course</button>
                <a href="{{ route('admin.courses.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
