@extends('layouts.admin')
@section('title', 'Add Fee Structure')
@section('page-title', 'Add Fee Structure')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.fees.index') }}">Fee Management</a></li>
    <li class="breadcrumb-item active">Add Fee Management</li>
@endsection

@section('content')

<div class="card" style="max-width:640px">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-cash-stack me-2 text-primary"></i>New Fee Structure</span>
        <a href="{{ route('admin.fees.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.fees.store') }}">
            @csrf
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Course <span class="text-danger">*</span></label>
                    <select aria-label="Course" name="course_id" class="form-select @error('course_id') is-invalid @enderror" required>
                        <option value="">Select course…</option>
                        @foreach($courses as $c)<option value="{{ $c->id }}" @selected(old('course_id')==$c->id)>{{ $c->name }}</option>@endforeach
                    </select>
                    <div class="form-text">The programme this fee applies to.</div>
                    @error('course_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Academic Year <span class="text-danger">*</span></label>
                    <select aria-label="Academic Year" name="academic_year_id" class="form-select @error('academic_year_id') is-invalid @enderror" required>
                        <option value="">Select year…</option>
                        @foreach($years as $y)<option value="{{ $y->id }}" @selected(old('academic_year_id')==$y->id)>{{ $y->name }}</option>@endforeach
                    </select>
                    @error('academic_year_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Fee Type <span class="text-danger">*</span></label>
                    <input aria-label="Fee type" type="text" name="fee_type" class="form-control @error('fee_type') is-invalid @enderror" value="{{ old('fee_type') }}" placeholder="Tuition / Exam / Lab…" required>
                    <div class="form-text">Category of this fee.</div>
                    @error('fee_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Amount (₹) <span class="text-danger">*</span></label>
                    <input aria-label="Amount" type="number" name="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}" min="0" step="0.01" required>
                    @error('amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Semester No.</label>
                    <input aria-label="Leave blank = all" type="number" name="semester_number" class="form-control" value="{{ old('semester_number') }}" placeholder="Leave blank = all" min="1" max="12">
                    <div class="form-text">Leave blank for all semesters.</div>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea aria-label="Description" name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4 pt-2 border-top">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Save Fee Structure</button>
                <a href="{{ route('admin.fees.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
