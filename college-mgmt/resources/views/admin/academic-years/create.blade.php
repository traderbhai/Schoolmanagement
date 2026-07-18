@extends('layouts.admin')
@section('title', 'Add Academic Year')
@section('page-title', 'Add Academic Year')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.academic-years.index') }}">Academic Years</a></li>
    <li class="breadcrumb-item active">Add Academic Year</li>
@endsection

@section('content')

<div class="card" style="max-width:600px">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-calendar-range me-2 text-primary"></i>New Academic Year</span>
        <a href="{{ route('admin.academic-years.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.academic-years.store') }}">
            @csrf
            <div class="row g-3 mb-3">
                <div class="col-12">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input aria-label="Academic year name" type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="e.g. 2025-2026" required>
                    <div class="form-text">Descriptive label for this academic year.</div>
                    @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Start Year <span class="text-danger">*</span></label>
                    <input aria-label="Start Year" type="number" name="start_year" class="form-control" value="{{ old('start_year', date('Y')) }}" min="2000" max="2100" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">End Year <span class="text-danger">*</span></label>
                    <input aria-label="End Year" type="number" name="end_year" class="form-control" value="{{ old('end_year', date('Y')+1) }}" min="2000" max="2100" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Start Date <span class="text-danger">*</span></label>
                    <input aria-label="Start Date" type="date" name="start_date" class="form-control @error('start_date') is-invalid @enderror" value="{{ old('start_date') }}" required>
                    @error('start_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">End Date <span class="text-danger">*</span></label>
                    <input aria-label="End Date" type="date" name="end_date" class="form-control @error('end_date') is-invalid @enderror" value="{{ old('end_date') }}" required>
                    @error('end_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox" name="is_current" class="form-check-input" id="is_current" value="1" @checked(old('is_current'))>
                        <label class="form-check-label" for="is_current">Set as current academic year</label>
                    </div>
                    <div class="form-text">Only one academic year can be current at a time.</div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4 pt-2 border-top">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Save Academic Year</button>
                <a href="{{ route('admin.academic-years.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
