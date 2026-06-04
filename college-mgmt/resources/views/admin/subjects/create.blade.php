@extends('layouts.admin')
@section('title','Add Subject')
@section('page-title','Add Subject')
@section('content')
<div class="card" style="max-width:600px">
    <div class="card-header">New Subject</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.subjects.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-semibold">Department *</label>
                <select name="department_id" class="form-select" required>
                    <option value="">Select department</option>
                    @foreach($departments as $d)<option value="{{ $d->id }}" @selected(old('department_id')==$d->id)>{{ $d->name }}</option>@endforeach
                </select>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Subject Name *</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Code *</label>
                    <input type="text" name="code" class="form-control" value="{{ old('code') }}" placeholder="CS101" required>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Type *</label>
                    <select name="type" class="form-select" required>
                        @foreach(['theory','practical','tutorial'] as $t)<option value="{{ $t }}" @selected(old('type')==$t)>{{ ucfirst($t) }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Credits *</label>
                    <input type="number" name="credits" class="form-control" value="{{ old('credits', 3) }}" min="1" max="10" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Hours/Week *</label>
                    <input type="number" name="hours_per_week" class="form-control" value="{{ old('hours_per_week', 3) }}" min="1" max="20" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Description</label>
                <textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('admin.subjects.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
