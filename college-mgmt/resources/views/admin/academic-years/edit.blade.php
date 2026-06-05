@extends('layouts.admin')
@section('title', 'Edit Academic Year')
@section('page-title', 'Edit Academic Year')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.academic-years.index') }}">Academic Years</a></li>
    <li class="breadcrumb-item active">Edit Academic Year</li>
@endsection

@section('content')

<div class="card" style="max-width:600px">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-pencil me-2 text-primary"></i>Edit Academic Year — {{ $academicYear->name }}</span>
        <a href="{{ route('admin.academic-years.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.academic-years.update', $academicYear) }}">
            @csrf @method('PUT')
            <div class="row g-3 mb-3">
                <div class="col-12">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $academicYear->name) }}" required>
                    @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Start Year <span class="text-danger">*</span></label>
                    <input type="number" name="start_year" class="form-control" value="{{ old('start_year', $academicYear->start_year) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">End Year <span class="text-danger">*</span></label>
                    <input type="number" name="end_year" class="form-control" value="{{ old('end_year', $academicYear->end_year) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Start Date <span class="text-danger">*</span></label>
                    <input type="date" name="start_date" class="form-control" value="{{ old('start_date', $academicYear->start_date->format('Y-m-d')) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">End Date <span class="text-danger">*</span></label>
                    <input type="date" name="end_date" class="form-control" value="{{ old('end_date', $academicYear->end_date->format('Y-m-d')) }}" required>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox" name="is_current" class="form-check-input" id="is_current" value="1" @checked(old('is_current', $academicYear->is_current))>
                        <label class="form-check-label" for="is_current">Set as current academic year</label>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4 pt-2 border-top">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Update Academic Year</button>
                <a href="{{ route('admin.academic-years.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
