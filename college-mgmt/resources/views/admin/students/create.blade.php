@extends('layouts.admin')
@section('title','Add Student')
@section('page-title','Add Student')
@section('content')
<div class="card" style="max-width:720px">
    <div class="card-header">New Student Registration</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.students.store') }}">
            @csrf
            <h6 class="text-muted fw-semibold mb-3 small text-uppercase">Account Details</h6>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label fw-semibold">Full Name *</label><input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label fw-semibold">Email *</label><input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-md-6"><label class="form-label fw-semibold">Password *</label><input type="password" name="password" class="form-control" required minlength="8"></div>
            </div>
            <h6 class="text-muted fw-semibold mb-3 small text-uppercase">Academic Details</h6>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Department *</label>
                    <select name="department_id" class="form-select @error('department_id') is-invalid @enderror" required>
                        <option value="">Select department</option>
                        @foreach($departments as $d)<option value="{{ $d->id }}" @selected(old('department_id')==$d->id)>{{ $d->name }}</option>@endforeach
                    </select>
                    @error('department_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Course *</label>
                    <select name="course_id" class="form-select @error('course_id') is-invalid @enderror" required>
                        <option value="">Select course</option>
                        @foreach($courses as $c)<option value="{{ $c->id }}" @selected(old('course_id')==$c->id)>{{ $c->name }}</option>@endforeach
                    </select>
                    @error('course_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label fw-semibold">Enrollment Number *</label><input type="text" name="enrollment_number" class="form-control @error('enrollment_number') is-invalid @enderror" value="{{ old('enrollment_number') }}" required>@error('enrollment_number')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-3"><label class="form-label fw-semibold">Roll Number</label><input type="text" name="roll_number" class="form-control" value="{{ old('roll_number') }}"></div>
                <div class="col-md-3"><label class="form-label fw-semibold">Current Semester</label><input type="number" name="current_semester" class="form-control" value="{{ old('current_semester', 1) }}" min="1" max="12"></div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-4"><label class="form-label fw-semibold">Date of Birth</label><input type="date" name="date_of_birth" class="form-control" value="{{ old('date_of_birth') }}"></div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">Select</option>
                        @foreach(['male','female','other'] as $g)<option value="{{ $g }}" @selected(old('gender')==$g)>{{ ucfirst($g) }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label fw-semibold">Phone</label><input type="text" name="phone" class="form-control" value="{{ old('phone') }}"></div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label fw-semibold">Guardian Name</label><input type="text" name="guardian_name" class="form-control" value="{{ old('guardian_name') }}"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Guardian Phone</label><input type="text" name="guardian_phone" class="form-control" value="{{ old('guardian_phone') }}"></div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label fw-semibold">Admission Date</label><input type="date" name="admission_date" class="form-control" value="{{ old('admission_date') }}"></div>
            </div>
            <div class="mb-3"><label class="form-label fw-semibold">Address</label><textarea name="address" class="form-control" rows="2">{{ old('address') }}</textarea></div>
            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary">Register Student</button>
                <a href="{{ route('admin.students.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
