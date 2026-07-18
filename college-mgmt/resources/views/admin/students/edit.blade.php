@extends('layouts.admin')
@section('title', 'Edit Student')
@section('page-title', 'Edit Student')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.students.index') }}">Students</a></li>
    <li class="breadcrumb-item active">Edit Student</li>
@endsection

@section('content')

<div class="card" style="max-width:760px">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-pencil me-2 text-primary"></i>Edit Student — {{ $student->user->name }}</span>
        <a href="{{ route('admin.students.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.students.update', $student) }}">
            @csrf @method('PUT')

            <div class="text-uppercase fw-semibold text-muted mb-3" style="font-size:.72rem;letter-spacing:.08em">Account Details</div>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input aria-label="Name" type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $student->user->name) }}" required>
                    @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email Address <span class="text-danger">*</span></label>
                    <input aria-label="Email" type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $student->user->email) }}" required>
                    @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="text-uppercase fw-semibold text-muted mb-3" style="font-size:.72rem;letter-spacing:.08em">Academic Details</div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Department <span class="text-danger">*</span></label>
                    <select aria-label="Department" name="department_id" class="form-select @error('department_id') is-invalid @enderror" required>
                        @foreach($departments as $d)<option value="{{ $d->id }}" @selected($d->id==old('department_id',$student->department_id))>{{ $d->name }}</option>@endforeach
                    </select>
                    @error('department_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Course <span class="text-danger">*</span></label>
                    <select aria-label="Course" name="course_id" class="form-select @error('course_id') is-invalid @enderror" required>
                        @foreach($courses as $c)<option value="{{ $c->id }}" @selected($c->id==old('course_id',$student->course_id))>{{ $c->name }}</option>@endforeach
                    </select>
                    @error('course_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Roll Number</label>
                    <input aria-label="Roll Number" type="text" name="roll_number" class="form-control" value="{{ old('roll_number', $student->roll_number) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Current Semester</label>
                    <input aria-label="Current Semester" type="number" name="current_semester" class="form-control" value="{{ old('current_semester', $student->current_semester) }}" min="1" max="12">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select aria-label="Status" name="status" class="form-select @error('status') is-invalid @enderror">
                        @foreach(['active','inactive','graduated','dropped'] as $s)<option value="{{ $s }}" @selected(old('status',$student->status)==$s)>{{ ucfirst($s) }}</option>@endforeach
                    </select>
                    @error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="text-uppercase fw-semibold text-muted mb-3 mt-2" style="font-size:.72rem;letter-spacing:.08em">Personal Details</div>
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Date of Birth</label>
                    <input aria-label="Date Of Birth" type="date" name="date_of_birth" class="form-control" value="{{ old('date_of_birth', optional($student->date_of_birth)->format('Y-m-d')) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Gender</label>
                    <select aria-label="Gender" name="gender" class="form-select">
                        <option value="">Select…</option>
                        @foreach(['male','female','other'] as $g)<option value="{{ $g }}" @selected(old('gender',$student->gender)==$g)>{{ ucfirst($g) }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Phone</label>
                    <input aria-label="Phone" type="text" name="phone" class="form-control" value="{{ old('phone', $student->phone) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Guardian Name</label>
                    <input aria-label="Guardian Name" type="text" name="guardian_name" class="form-control" value="{{ old('guardian_name', $student->guardian_name) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Guardian Phone</label>
                    <input aria-label="Guardian Phone" type="text" name="guardian_phone" class="form-control" value="{{ old('guardian_phone', $student->guardian_phone) }}">
                </div>
            </div>

            <div class="d-flex gap-2 mt-4 pt-2 border-top">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Update Student</button>
                <a href="{{ route('admin.students.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
