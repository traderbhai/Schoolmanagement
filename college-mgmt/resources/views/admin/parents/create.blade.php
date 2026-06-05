@extends('layouts.admin')
@section('title', 'Add Parent')
@section('page-title', 'Add Parent')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.parents.index') }}">Parents</a></li>
    <li class="breadcrumb-item active">Add Parent</li>
@endsection

@section('content')
<div class="card" style="max-width:760px">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-person-plus me-2 text-primary"></i>New Parent Account</span>
        <a href="{{ route('admin.parents.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.parents.store') }}">
            @csrf
            <h6 class="fw-semibold mb-3 text-muted text-uppercase" style="font-size:.75rem;letter-spacing:.06em">Login Account</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Password <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <h6 class="fw-semibold mb-3 text-muted text-uppercase" style="font-size:.75rem;letter-spacing:.06em">Parent Details</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label">Relation <span class="text-danger">*</span></label>
                    <select name="relation" class="form-select @error('relation') is-invalid @enderror" required>
                        @foreach(['father','mother','guardian','parent'] as $rel)
                        <option value="{{ $rel }}" @selected(old('relation') == $rel)>{{ ucfirst($rel) }}</option>
                        @endforeach
                    </select>
                    @error('relation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Occupation</label>
                    <input type="text" name="occupation" class="form-control" value="{{ old('occupation') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Annual Income</label>
                    <input type="text" name="annual_income" class="form-control" value="{{ old('annual_income') }}">
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2">{{ old('address') }}</textarea>
                </div>
            </div>

            <h6 class="fw-semibold mb-3 text-muted text-uppercase" style="font-size:.75rem;letter-spacing:.06em">Link Students</h6>
            <div class="row g-2 mb-4">
                @foreach($students as $student)
                <div class="col-md-6">
                    <div class="form-check">
                        <input type="checkbox" name="student_ids[]" value="{{ $student->id }}"
                            class="form-check-input" id="student_{{ $student->id }}"
                            @checked(is_array(old('student_ids')) && in_array($student->id, old('student_ids')))>
                        <label class="form-check-label" for="student_{{ $student->id }}">
                            {{ $student->user->name }}
                            <span class="text-muted" style="font-size:.8rem">{{ $student->enrollment_number }}</span>
                        </label>
                    </div>
                </div>
                @endforeach
                @if($students->isEmpty())
                <div class="col-12"><span class="text-muted">No students found.</span></div>
                @endif
            </div>

            <div class="d-flex gap-2 pt-2 border-top">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Create Parent</button>
                <a href="{{ route('admin.parents.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
