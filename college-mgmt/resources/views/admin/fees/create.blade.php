@extends('layouts.admin')
@section('title','Add Fee Structure')
@section('page-title','Add Fee Structure')
@section('content')
<div class="card" style="max-width:620px">
    <div class="card-header">New Fee Structure</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.fees.store') }}">
            @csrf
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Course *</label>
                    <select name="course_id" class="form-select" required>
                        <option value="">Select course</option>
                        @foreach($courses as $c)<option value="{{ $c->id }}" @selected(old('course_id')==$c->id)>{{ $c->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Academic Year *</label>
                    <select name="academic_year_id" class="form-select" required>
                        <option value="">Select year</option>
                        @foreach($years as $y)<option value="{{ $y->id }}" @selected(old('academic_year_id')==$y->id)>{{ $y->name }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label fw-semibold">Fee Type *</label><input type="text" name="fee_type" class="form-control" value="{{ old('fee_type') }}" placeholder="Tuition / Exam / Lab..." required></div>
                <div class="col-md-3"><label class="form-label fw-semibold">Amount (₹) *</label><input type="number" name="amount" class="form-control" value="{{ old('amount') }}" min="0" step="0.01" required></div>
                <div class="col-md-3"><label class="form-label fw-semibold">Semester</label><input type="number" name="semester_number" class="form-control" value="{{ old('semester_number') }}" placeholder="Leave blank for all" min="1" max="12"></div>
            </div>
            <div class="mb-3"><label class="form-label fw-semibold">Description</label><textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea></div>
            <div class="d-flex gap-2"><button type="submit" class="btn btn-primary">Save</button><a href="{{ route('admin.fees.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
        </form>
    </div>
</div>
@endsection
