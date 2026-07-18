@extends('layouts.admin')
@section('title', 'Add Teacher')
@section('page-title', 'Add Teacher')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.teachers.index') }}">Teachers</a></li>
    <li class="breadcrumb-item active">Add Teacher</li>
@endsection

@section('content')

<div class="card" style="max-width:720px">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-person-plus me-2 text-primary"></i>New Teacher</span>
        <a href="{{ route('admin.teachers.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.teachers.store') }}">
            @csrf

            <div class="text-uppercase fw-semibold text-muted mb-3" style="font-size:.72rem;letter-spacing:.08em">Account Details</div>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input aria-label="Name" type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                    <div class="form-text">Teacher's full name.</div>
                    @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email Address <span class="text-danger">*</span></label>
                    <input aria-label="Email" type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                    <div class="form-text">Used for login.</div>
                    @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Password <span class="text-danger">*</span></label>
                    <input aria-label="Password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" required minlength="8">
                    <div class="form-text">Minimum 8 characters.</div>
                    @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="text-uppercase fw-semibold text-muted mb-3" style="font-size:.72rem;letter-spacing:.08em">Professional Details</div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Department <span class="text-danger">*</span></label>
                    <select aria-label="Department" name="department_id" class="form-select @error('department_id') is-invalid @enderror" required>
                        <option value="">Select department…</option>
                        @foreach($departments as $d)<option value="{{ $d->id }}" @selected(old('department_id')==$d->id)>{{ $d->name }}</option>@endforeach
                    </select>
                    <div class="form-text">Primary department.</div>
                    @error('department_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Employee ID <span class="text-danger">*</span></label>
                    <input aria-label="Employee" type="text" name="employee_id" class="form-control @error('employee_id') is-invalid @enderror" value="{{ old('employee_id') }}" required>
                    <div class="form-text">Unique staff ID number.</div>
                    @error('employee_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Designation</label>
                    <input aria-label="Associate Professor" type="text" name="designation" class="form-control" value="{{ old('designation') }}" placeholder="Associate Professor">
                    <div class="form-text">Job title / rank.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Qualification</label>
                    <input aria-label="Teacher qualification" type="text" name="qualification" class="form-control" value="{{ old('qualification') }}" placeholder="Ph.D, M.Tech…">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Specialization</label>
                    <input aria-label="Specialization" type="text" name="specialization" class="form-control" value="{{ old('specialization') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input aria-label="Phone" type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Employment Type <span class="text-danger">*</span></label>
                    <select aria-label="Employment Type" name="employment_type" class="form-select" required>
                        @foreach(['full_time'=>'Full Time','part_time'=>'Part Time','visiting'=>'Visiting'] as $v=>$l)
                            <option value="{{ $v }}" @selected(old('employment_type')==$v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Date of Joining</label>
                    <input aria-label="Date Of Joining" type="date" name="date_of_joining" class="form-control" value="{{ old('date_of_joining') }}">
                </div>
            </div>

            <div class="d-flex gap-2 mt-4 pt-2 border-top">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Add Teacher</button>
                <a href="{{ route('admin.teachers.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
