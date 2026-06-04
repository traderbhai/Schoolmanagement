@extends('layouts.admin')
@section('title','Add Teacher')
@section('page-title','Add Teacher')
@section('content')
<div class="card" style="max-width:680px">
    <div class="card-header">New Teacher</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.teachers.store') }}">
            @csrf
            <h6 class="text-muted fw-semibold mb-3 small text-uppercase">Account Details</h6>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label fw-semibold">Full Name *</label><input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label fw-semibold">Email *</label><input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-md-6"><label class="form-label fw-semibold">Password *</label><input type="password" name="password" class="form-control" required minlength="8"></div>
            </div>
            <h6 class="text-muted fw-semibold mb-3 small text-uppercase">Professional Details</h6>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Department *</label>
                    <select name="department_id" class="form-select @error('department_id') is-invalid @enderror" required>
                        <option value="">Select department</option>
                        @foreach($departments as $d)<option value="{{ $d->id }}" @selected(old('department_id')==$d->id)>{{ $d->name }}</option>@endforeach
                    </select>
                    @error('department_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6"><label class="form-label fw-semibold">Employee ID *</label><input type="text" name="employee_id" class="form-control @error('employee_id') is-invalid @enderror" value="{{ old('employee_id') }}" required>@error('employee_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label fw-semibold">Designation</label><input type="text" name="designation" class="form-control" value="{{ old('designation') }}" placeholder="Associate Professor"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Qualification</label><input type="text" name="qualification" class="form-control" value="{{ old('qualification') }}" placeholder="Ph.D, M.Tech"></div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label fw-semibold">Specialization</label><input type="text" name="specialization" class="form-control" value="{{ old('specialization') }}"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Phone</label><input type="text" name="phone" class="form-control" value="{{ old('phone') }}"></div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Employment Type *</label>
                    <select name="employment_type" class="form-select" required>
                        @foreach(['full_time'=>'Full Time','part_time'=>'Part Time','visiting'=>'Visiting'] as $v=>$l)<option value="{{ $v }}" @selected(old('employment_type')==$v)>{{ $l }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label fw-semibold">Date of Joining</label><input type="date" name="date_of_joining" class="form-control" value="{{ old('date_of_joining') }}"></div>
            </div>
            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary">Add Teacher</button>
                <a href="{{ route('admin.teachers.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
