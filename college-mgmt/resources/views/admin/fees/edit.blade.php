@extends('layouts.admin')
@section('title', 'Edit Fee Structure')
@section('page-title', 'Edit Fee Structure')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.fees.index') }}">Fee Management</a></li>
    <li class="breadcrumb-item active">Edit Fee Management</li>
@endsection

@section('content')

<div class="card" style="max-width:640px">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-pencil me-2 text-primary"></i>Edit Fee Structure</span>
        <a href="{{ route('admin.fees.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.fees.update', $fee) }}">
            @csrf @method('PUT')
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Course <span class="text-danger">*</span></label>
                    <select name="course_id" class="form-select @error('course_id') is-invalid @enderror" required>
                        @foreach($courses as $c)<option value="{{ $c->id }}" @selected($c->id==old('course_id',$fee->course_id))>{{ $c->name }}</option>@endforeach
                    </select>
                    @error('course_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Academic Year <span class="text-danger">*</span></label>
                    <select name="academic_year_id" class="form-select @error('academic_year_id') is-invalid @enderror" required>
                        @foreach($years as $y)<option value="{{ $y->id }}" @selected($y->id==old('academic_year_id',$fee->academic_year_id))>{{ $y->name }}</option>@endforeach
                    </select>
                    @error('academic_year_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Fee Type <span class="text-danger">*</span></label>
                    <input type="text" name="fee_type" class="form-control @error('fee_type') is-invalid @enderror" value="{{ old('fee_type', $fee->fee_type) }}" required>
                    @error('fee_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Amount (₹) <span class="text-danger">*</span></label>
                    <input type="number" name="amount" class="form-control" value="{{ old('amount', $fee->amount) }}" step="0.01" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Semester No.</label>
                    <input type="number" name="semester_number" class="form-control" value="{{ old('semester_number', $fee->semester_number) }}" min="1" max="12">
                    <div class="form-text">Blank = all.</div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4 pt-2 border-top">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Update Fee Structure</button>
                <a href="{{ route('admin.fees.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
