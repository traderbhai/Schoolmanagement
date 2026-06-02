@extends('layouts.admin')
@section('title','Edit Academic Year')
@section('page-title','Edit Academic Year')
@section('content')
<div class="card" style="max-width:580px">
    <div class="card-header">Edit: {{ $academicYear->name }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.academic-years.update', $academicYear) }}">
            @csrf @method('PUT')
            <div class="mb-3"><label class="form-label fw-semibold">Name *</label><input type="text" name="name" class="form-control" value="{{ old('name', $academicYear->name) }}" required></div>
            <div class="row g-3 mb-3">
                <div class="col"><label class="form-label fw-semibold">Start Year</label><input type="number" name="start_year" class="form-control" value="{{ old('start_year', $academicYear->start_year) }}" required></div>
                <div class="col"><label class="form-label fw-semibold">End Year</label><input type="number" name="end_year" class="form-control" value="{{ old('end_year', $academicYear->end_year) }}" required></div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col"><label class="form-label fw-semibold">Start Date</label><input type="date" name="start_date" class="form-control" value="{{ old('start_date', $academicYear->start_date->format('Y-m-d')) }}" required></div>
                <div class="col"><label class="form-label fw-semibold">End Date</label><input type="date" name="end_date" class="form-control" value="{{ old('end_date', $academicYear->end_date->format('Y-m-d')) }}" required></div>
            </div>
            <div class="mb-3 form-check"><input type="checkbox" name="is_current" class="form-check-input" id="is_current" value="1" @checked(old('is_current', $academicYear->is_current))><label class="form-check-label" for="is_current">Set as Current Academic Year</label></div>
            <div class="d-flex gap-2"><button type="submit" class="btn btn-primary">Update</button><a href="{{ route('admin.academic-years.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
        </form>
    </div>
</div>
@endsection
