@extends('layouts.admin')
@section('title','Edit Course')
@section('page-title','Edit Course')
@section('content')
<div class="card" style="max-width:640px">
    <div class="card-header">Edit: {{ $course->name }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.courses.update', $course) }}">
            @csrf @method('PUT')
            <div class="mb-3">
                <label class="form-label fw-semibold">Department *</label>
                <select name="department_id" class="form-select" required>
                    @foreach($departments as $d)<option value="{{ $d->id }}" @selected($d->id==old('department_id',$course->department_id))>{{ $d->name }}</option>@endforeach
                </select>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Course Name *</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $course->name) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Code *</label>
                    <input type="text" name="code" class="form-control" value="{{ old('code', $course->code) }}" required>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Duration (Years)</label>
                    <input type="number" name="duration_years" class="form-control" value="{{ old('duration_years', $course->duration_years) }}" min="1" max="6">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Total Semesters</label>
                    <input type="number" name="total_semesters" class="form-control" value="{{ old('total_semesters', $course->total_semesters) }}" min="1" max="12">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Description</label>
                <textarea name="description" class="form-control" rows="3">{{ old('description', $course->description) }}</textarea>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" name="is_active" class="form-check-input" id="is_active" value="1" @checked(old('is_active', $course->is_active))>
                <label class="form-check-label" for="is_active">Active</label>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Update</button>
                <a href="{{ route('admin.courses.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
