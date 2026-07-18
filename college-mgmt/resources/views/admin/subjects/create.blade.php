@extends('layouts.admin')
@section('title', 'Add Subject')
@section('page-title', 'Add Subject')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.subjects.index') }}">Subjects</a></li>
    <li class="breadcrumb-item active">Add Subject</li>
@endsection

@section('content')

<div class="card" style="max-width:640px">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-book me-2 text-primary"></i>New Subject</span>
        <a href="{{ route('admin.subjects.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.subjects.store') }}">
            @csrf
            <div class="row g-3 mb-3">
                <div class="col-12">
                    <label class="form-label">Department <span class="text-danger">*</span></label>
                    <select aria-label="Department" name="department_id" class="form-select @error('department_id') is-invalid @enderror" required>
                        <option value="">Select department…</option>
                        @foreach($departments as $d)<option value="{{ $d->id }}" @selected(old('department_id')==$d->id)>{{ $d->name }}</option>@endforeach
                    </select>
                    @error('department_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-8">
                    <label class="form-label">Subject Name <span class="text-danger">*</span></label>
                    <input aria-label="Name" type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                    <div class="form-text">Full subject name.</div>
                    @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Code <span class="text-danger">*</span></label>
                    <input aria-label="CS101" type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code') }}" placeholder="CS101" required>
                    @error('code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Type <span class="text-danger">*</span></label>
                    <select aria-label="Type" name="type" class="form-select @error('type') is-invalid @enderror" required>
                        @foreach(['theory','practical','tutorial'] as $t)<option value="{{ $t }}" @selected(old('type')==$t)>{{ ucfirst($t) }}</option>@endforeach
                    </select>
                    <div class="form-text">Theory, Practical, or Tutorial.</div>
                    @error('type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Credits <span class="text-danger">*</span></label>
                    <input aria-label="Credits" type="number" name="credits" class="form-control" value="{{ old('credits', 3) }}" min="1" max="10" required>
                    <div class="form-text">Credit weightage.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Hours / Week <span class="text-danger">*</span></label>
                    <input aria-label="Hours Per Week" type="number" name="hours_per_week" class="form-control" value="{{ old('hours_per_week', 3) }}" min="1" max="20" required>
                    <div class="form-text">Lecture hours per week.</div>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea aria-label="Description" name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4 pt-2 border-top">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Save Subject</button>
                <a href="{{ route('admin.subjects.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
