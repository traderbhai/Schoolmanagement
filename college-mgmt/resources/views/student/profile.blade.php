@extends('layouts.student')
@section('title', 'My Profile')
@section('page-title', 'My Profile')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('student.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Profile</li>
@endsection

@section('content')

@if(session('status') === 'profile-updated')
<div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 mb-3" role="alert">
    <i class="bi bi-check-circle-fill"></i>
    <span>Profile updated successfully.</span>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="row g-4">

    {{-- Left: Profile Card + Academic Info --}}
    <div class="col-lg-4">
        {{-- Profile Identity Card --}}
        <div class="card mb-3 text-center" style="box-shadow:var(--shadow-sm)">
            <div class="card-body pt-4 pb-3">
                @if($student->photo)
                    <img src="{{ asset('storage/' . $student->photo) }}"
                         class="rounded-circle border border-3 mb-3"
                         style="width:80px;height:80px;object-fit:cover;"
                         alt="Photo">
                @else
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3 fw-bold text-white"
                         style="width:80px;height:80px;background:var(--clr-primary,#4f46e5);font-size:2rem">
                        {{ strtoupper(substr($student->user->name, 0, 1)) }}
                    </div>
                @endif

                <h5 class="fw-bold mb-1">{{ $student->user->name }}</h5>
                <div class="text-muted small mb-2">{{ $student->user->email }}</div>
                <div class="font-monospace small mb-3 text-muted">{{ $student->enrollment_number }}</div>
                <span class="badge {{ $student->status === 'active' ? 'badge-active' : 'bg-secondary' }}">
                    {{ ucfirst($student->status) }}
                </span>
            </div>
        </div>

        {{-- Academic Info Card --}}
        <div class="card" style="box-shadow:var(--shadow-sm)">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-person-badge text-primary"></i>
                <span class="fw-semibold">Academic Information</span>
            </div>
            <div class="card-body">
                <dl class="row mb-0" style="font-size:.875rem">
                    <dt class="col-5 fw-normal" style="color:var(--clr-text-muted)">Course</dt>
                    <dd class="col-7 fw-semibold">{{ optional($student->course)->name ?: '—' }}</dd>

                    <dt class="col-5 fw-normal" style="color:var(--clr-text-muted)">Department</dt>
                    <dd class="col-7 fw-semibold">{{ optional($student->department)->name ?: '—' }}</dd>

                    <dt class="col-5 fw-normal" style="color:var(--clr-text-muted)">Semester</dt>
                    <dd class="col-7 fw-semibold">Semester {{ $student->current_semester }}</dd>

                    <dt class="col-5 fw-normal" style="color:var(--clr-text-muted)">Roll No.</dt>
                    <dd class="col-7 fw-semibold font-monospace">{{ $student->roll_number ?: '—' }}</dd>

                    <dt class="col-5 fw-normal" style="color:var(--clr-text-muted)">Admission</dt>
                    <dd class="col-7 fw-semibold">
                        {{ $student->admission_date ? $student->admission_date->format('d M Y') : '—' }}
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    {{-- Right: Edit Forms with Tabs --}}
    <div class="col-lg-8">
        <div class="card" style="box-shadow:var(--shadow-sm)">
            <div class="card-header p-0">
                <ul class="nav nav-tabs card-header-tabs px-3" id="profileTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-semibold" id="personal-tab"
                                data-bs-toggle="tab" data-bs-target="#personal"
                                type="button" role="tab">
                            <i class="bi bi-person me-1"></i>Personal Info
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold" id="password-tab"
                                data-bs-toggle="tab" data-bs-target="#password"
                                type="button" role="tab">
                            <i class="bi bi-lock me-1"></i>Change Password
                        </button>
                    </li>
                </ul>
            </div>

            <div class="tab-content">
                {{-- Personal Info Tab --}}
                <div class="tab-pane fade show active p-4" id="personal" role="tabpanel">
                    <form method="POST" action="{{ route('student.profile.update') }}">
                        @csrf
                        @method('PATCH')

                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name"
                                   class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name', $student->user->name) }}"
                                   required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Email Address</label>
                            <input type="email" class="form-control bg-light"
                                   value="{{ $student->user->email }}" readonly disabled>
                            <div class="form-text">Email cannot be changed. Contact admin if needed.</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold small">Date of Birth</label>
                            <input type="text" class="form-control bg-light"
                                   value="{{ $student->date_of_birth ? $student->date_of_birth->format('d M Y') : '—' }}"
                                   readonly disabled>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Save Changes
                            </button>
                            <a href="{{ route('student.dashboard') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>

                {{-- Change Password Tab --}}
                <div class="tab-pane fade p-4" id="password" role="tabpanel">
                    <form method="POST" action="{{ route('student.profile.update') }}">
                        @csrf
                        @method('PATCH')

                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Current Password <span class="text-danger">*</span></label>
                            <input type="password" name="current_password"
                                   class="form-control @error('current_password') is-invalid @enderror"
                                   autocomplete="current-password"
                                   placeholder="Enter your current password">
                            @error('current_password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small">New Password <span class="text-danger">*</span></label>
                            <input type="password" name="password"
                                   class="form-control @error('password') is-invalid @enderror"
                                   autocomplete="new-password"
                                   placeholder="Min. 8 characters">
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Use at least 8 characters with a mix of letters and numbers.</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold small">Confirm New Password <span class="text-danger">*</span></label>
                            <input type="password" name="password_confirmation"
                                   class="form-control"
                                   autocomplete="new-password"
                                   placeholder="Re-enter new password">
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-warning text-dark fw-semibold">
                                <i class="bi bi-shield-lock me-1"></i>Update Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script>
// Activate password tab if there are password errors
@error('password') @error('current_password')
document.addEventListener('DOMContentLoaded', function() {
    const tab = document.getElementById('password-tab');
    if (tab) { tab.click(); }
});
@enderror @enderror
</script>
@endpush
