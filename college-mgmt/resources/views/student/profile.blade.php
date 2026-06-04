@extends('layouts.student')
@section('title', 'My Profile')
@section('page-title', 'My Profile')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('student.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Profile</li>
@endsection

@section('content')

<div class="row g-4">

    {{-- Left: Academic Information --}}
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-person-badge text-primary"></i>
                Academic Information
            </div>
            <div class="card-body">
                @if($student->photo)
                    <div class="text-center mb-4">
                        <img src="{{ asset('storage/' . $student->photo) }}"
                             class="rounded-circle border"
                             style="width:90px;height:90px;object-fit:cover;"
                             alt="Photo">
                    </div>
                @else
                    <div class="text-center mb-4">
                        <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center"
                             style="width:90px;height:90px;">
                            <i class="bi bi-person-fill fs-2 text-primary"></i>
                        </div>
                    </div>
                @endif

                <dl class="row mb-0">
                    <dt class="col-5 text-muted small fw-normal">Full Name</dt>
                    <dd class="col-7 fw-semibold small">{{ $student->user->name }}</dd>

                    <dt class="col-5 text-muted small fw-normal">Enrollment No.</dt>
                    <dd class="col-7 fw-semibold small font-monospace">{{ $student->enrollment_number }}</dd>

                    <dt class="col-5 text-muted small fw-normal">Roll No.</dt>
                    <dd class="col-7 fw-semibold small">{{ $student->roll_number ?: '—' }}</dd>

                    <dt class="col-5 text-muted small fw-normal">Department</dt>
                    <dd class="col-7 fw-semibold small">{{ optional($student->department)->name ?: '—' }}</dd>

                    <dt class="col-5 text-muted small fw-normal">Course</dt>
                    <dd class="col-7 fw-semibold small">{{ optional($student->course)->name ?: '—' }}</dd>

                    <dt class="col-5 text-muted small fw-normal">Semester</dt>
                    <dd class="col-7 fw-semibold small">Semester {{ $student->current_semester }}</dd>

                    <dt class="col-5 text-muted small fw-normal">Admission Date</dt>
                    <dd class="col-7 fw-semibold small">
                        {{ $student->admission_date ? $student->admission_date->format('d M Y') : '—' }}
                    </dd>

                    <dt class="col-5 text-muted small fw-normal">Status</dt>
                    <dd class="col-7 small">
                        <span class="badge {{ $student->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                            {{ ucfirst($student->status) }}
                        </span>
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    {{-- Right: Edit Profile --}}
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-pencil-square text-success"></i>
                Edit Profile
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('student.profile.update') }}">
                    @csrf
                    @method('PATCH')

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name"
                               class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $student->user->name) }}"
                               required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Email Address</label>
                        <input type="email" class="form-control bg-light"
                               value="{{ $student->user->email }}" readonly disabled>
                        <div class="form-text">Email cannot be changed. Contact admin if needed.</div>
                    </div>

                    <hr class="my-3">
                    <p class="small fw-semibold text-muted mb-3">Change Password <span class="fw-normal">(leave blank to keep current)</span></p>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">New Password</label>
                        <input type="password" name="password"
                               class="form-control @error('password') is-invalid @enderror"
                               autocomplete="new-password"
                               placeholder="Min. 8 characters">
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Confirm New Password</label>
                        <input type="password" name="password_confirmation"
                               class="form-control"
                               autocomplete="new-password"
                               placeholder="Re-enter new password">
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Save Changes
                        </button>
                        <a href="{{ route('student.dashboard') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

@endsection
