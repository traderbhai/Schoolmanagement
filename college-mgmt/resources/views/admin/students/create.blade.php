@extends('layouts.admin')
@section('title', 'Add Student')
@section('page-title', 'Add Student')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.students.index') }}">Students</a></li>
    <li class="breadcrumb-item active">Add Student</li>
@endsection

@section('content')

<div class="card" style="max-width:760px">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-person-plus me-2 text-primary"></i>New Student Registration</span>
        <a href="{{ route('admin.students.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.students.store') }}">
            @csrf

            <div class="text-uppercase fw-semibold text-muted mb-3" style="font-size:.72rem;letter-spacing:.08em">Account Details</div>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input aria-label="Name" type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                    <div class="form-text">Student's full legal name as in records.</div>
                    @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email Address <span class="text-danger">*</span></label>
                    <input aria-label="Email" type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                    <div class="form-text">Used for login and communication.</div>
                    @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Password <span class="text-danger">*</span></label>
                    <input aria-label="Password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" required minlength="8">
                    <div class="form-text">Minimum 8 characters.</div>
                    @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="text-uppercase fw-semibold text-muted mb-3" style="font-size:.72rem;letter-spacing:.08em">Academic Details</div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Department <span class="text-danger">*</span></label>
                    <select aria-label="Department" name="department_id" class="form-select @error('department_id') is-invalid @enderror" required>
                        <option value="">Select department…</option>
                        @foreach($departments as $d)<option value="{{ $d->id }}" @selected(old('department_id')==$d->id)>{{ $d->name }}</option>@endforeach
                    </select>
                    <div class="form-text">The department this student belongs to.</div>
                    @error('department_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Course <span class="text-danger">*</span></label>
                    <select aria-label="Course" name="course_id" class="form-select @error('course_id') is-invalid @enderror" required>
                        <option value="">Select course…</option>
                        @foreach($courses as $c)<option value="{{ $c->id }}" @selected(old('course_id')==$c->id)>{{ $c->name }}</option>@endforeach
                    </select>
                    <div class="form-text">Programme the student is enrolled in.</div>
                    @error('course_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-5">
                    <label class="form-label">Enrollment Number <span class="text-danger">*</span></label>
                    <input aria-label="Enrollment Number" type="text" name="enrollment_number" class="form-control @error('enrollment_number') is-invalid @enderror" value="{{ old('enrollment_number') }}" required>
                    <div class="form-text">Unique enrollment / admission number.</div>
                    @error('enrollment_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Roll Number</label>
                    <input aria-label="Roll Number" type="text" name="roll_number" class="form-control" value="{{ old('roll_number') }}">
                    <div class="form-text">Class roll number (optional).</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Current Semester</label>
                    <input aria-label="Current Semester" type="number" name="current_semester" class="form-control" value="{{ old('current_semester', 1) }}" min="1" max="12">
                    <div class="form-text">Active semester number.</div>
                </div>
            </div>

            <div class="text-uppercase fw-semibold text-muted mb-3 mt-2" style="font-size:.72rem;letter-spacing:.08em">Personal Details</div>
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Date of Birth</label>
                    <input aria-label="Date Of Birth" type="date" name="date_of_birth" class="form-control" value="{{ old('date_of_birth') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Gender</label>
                    <select aria-label="Gender" name="gender" class="form-select">
                        <option value="">Select…</option>
                        @foreach(['male','female','other'] as $g)<option value="{{ $g }}" @selected(old('gender')==$g)>{{ ucfirst($g) }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Phone</label>
                    <input aria-label="Phone" type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Guardian Name</label>
                    <input aria-label="Guardian Name" type="text" name="guardian_name" class="form-control" value="{{ old('guardian_name') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Guardian Phone</label>
                    <input aria-label="Guardian Phone" type="text" name="guardian_phone" class="form-control" value="{{ old('guardian_phone') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Admission Date</label>
                    <input aria-label="Admission Date" type="date" name="admission_date" class="form-control" value="{{ old('admission_date') }}">
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea aria-label="Address" name="address" class="form-control" rows="2">{{ old('address') }}</textarea>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4 pt-2 border-top">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Register Student</button>
                <a href="{{ route('admin.students.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
