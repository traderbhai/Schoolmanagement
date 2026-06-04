@extends('layouts.admin')
@section('title','Edit Fee Structure')
@section('page-title','Edit Fee Structure')
@section('content')
<div class="card" style="max-width:620px">
    <div class="card-header">Edit Fee Structure</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.fees.update', $fee) }}">
            @csrf @method('PUT')
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Course *</label>
                    <select name="course_id" class="form-select" required>
                        @foreach($courses as $c)<option value="{{ $c->id }}" @selected($c->id==old('course_id',$fee->course_id))>{{ $c->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Academic Year *</label>
                    <select name="academic_year_id" class="form-select" required>
                        @foreach($years as $y)<option value="{{ $y->id }}" @selected($y->id==old('academic_year_id',$fee->academic_year_id))>{{ $y->name }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label fw-semibold">Fee Type *</label><input type="text" name="fee_type" class="form-control" value="{{ old('fee_type', $fee->fee_type) }}" required></div>
                <div class="col-md-3"><label class="form-label fw-semibold">Amount (₹)</label><input type="number" name="amount" class="form-control" value="{{ old('amount', $fee->amount) }}" step="0.01" required></div>
                <div class="col-md-3"><label class="form-label fw-semibold">Semester</label><input type="number" name="semester_number" class="form-control" value="{{ old('semester_number', $fee->semester_number) }}" min="1" max="12"></div>
            </div>
            <div class="d-flex gap-2"><button type="submit" class="btn btn-primary">Update</button><a href="{{ route('admin.fees.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
        </form>
    </div>
</div>
@endsection
