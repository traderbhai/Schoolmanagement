@extends('layouts.admin')
@section('title', 'Edit Semester')
@section('page-title', 'Edit Semester')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.semesters.index') }}">Semesters</a></li>
    <li class="breadcrumb-item active">Edit Semester</li>
@endsection

@section('content')

<div class="card" style="max-width:600px">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-pencil me-2 text-primary"></i>Edit Semester — {{ $semester->name }}</span>
        <a href="{{ route('admin.semesters.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.semesters.update', $semester) }}">
            @csrf @method('PUT')
            <div class="row g-3 mb-3">
                <div class="col-12">
                    <label class="form-label">Academic Year <span class="text-danger">*</span></label>
                    <select name="academic_year_id" class="form-select @error('academic_year_id') is-invalid @enderror" required>
                        @foreach($years as $y)<option value="{{ $y->id }}" @selected($y->id==old('academic_year_id',$semester->academic_year_id))>{{ $y->name }}</option>@endforeach
                    </select>
                    @error('academic_year_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-8">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $semester->name) }}" required>
                    @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Number <span class="text-danger">*</span></label>
                    <input type="number" name="number" class="form-control" value="{{ old('number', $semester->number) }}" min="1" max="12" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Start Date <span class="text-danger">*</span></label>
                    <input type="date" name="start_date" class="form-control" value="{{ old('start_date', $semester->start_date->format('Y-m-d')) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">End Date <span class="text-danger">*</span></label>
                    <input type="date" name="end_date" class="form-control" value="{{ old('end_date', $semester->end_date->format('Y-m-d')) }}" required>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox" name="is_current" class="form-check-input" id="ic" value="1" @checked(old('is_current',$semester->is_current))>
                        <label class="form-check-label" for="ic">Set as current semester</label>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4 pt-2 border-top">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Update Semester</button>
                <a href="{{ route('admin.semesters.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
