@extends('layouts.admin')
@section('title','Edit Department')
@section('page-title','Edit Department')
@section('content')
<div class="card" style="max-width:600px">
    <div class="card-header">Edit: {{ $department->name }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.departments.update', $department) }}">
            @csrf @method('PUT')
            <div class="mb-3">
                <label class="form-label fw-semibold">Department Name *</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $department->name) }}" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Code *</label>
                <input type="text" name="code" class="form-control" value="{{ old('code', $department->code) }}" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Head of Department</label>
                <input type="text" name="head_name" class="form-control" value="{{ old('head_name', $department->head_name) }}">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Description</label>
                <textarea name="description" class="form-control" rows="3">{{ old('description', $department->description) }}</textarea>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" name="is_active" class="form-check-input" id="is_active" value="1" @checked(old('is_active', $department->is_active))>
                <label class="form-check-label" for="is_active">Active</label>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Update</button>
                <a href="{{ route('admin.departments.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
