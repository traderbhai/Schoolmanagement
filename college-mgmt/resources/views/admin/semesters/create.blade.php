@extends('layouts.admin')
@section('title','Add Semester')
@section('page-title','Add Semester')
@section('content')
<div class="card" style="max-width:580px">
    <div class="card-header">New Semester</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.semesters.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-semibold">Academic Year *</label>
                <select name="academic_year_id" class="form-select" required>
                    <option value="">Select year</option>
                    @foreach($years as $y)<option value="{{ $y->id }}" @selected(old('academic_year_id')==$y->id)>{{ $y->name }}</option>@endforeach
                </select>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-8"><label class="form-label fw-semibold">Semester Name *</label><input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="e.g. Semester 5 (2025-26)" required></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Number *</label><input type="number" name="number" class="form-control" value="{{ old('number', 1) }}" min="1" max="12" required></div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col"><label class="form-label fw-semibold">Start Date *</label><input type="date" name="start_date" class="form-control" value="{{ old('start_date') }}" required></div>
                <div class="col"><label class="form-label fw-semibold">End Date *</label><input type="date" name="end_date" class="form-control" value="{{ old('end_date') }}" required></div>
            </div>
            <div class="mb-3 form-check"><input type="checkbox" name="is_current" class="form-check-input" id="ic" value="1" @checked(old('is_current'))><label class="form-check-label" for="ic">Set as Current Semester</label></div>
            <div class="d-flex gap-2"><button type="submit" class="btn btn-primary">Save</button><a href="{{ route('admin.semesters.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
        </form>
    </div>
</div>
@endsection
