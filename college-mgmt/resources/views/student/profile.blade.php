@extends('layouts.student')
@section('title', 'My Profile')

@section('content')
<div class="container py-4">
    <h4 class="fw-semibold mb-4">My Profile</h4>

    <div class="row g-4">
        {{-- Profile Summary Card --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-4">
                    <div class="mb-3">
                        @if($student->photo)
                        <img src="{{ Storage::url($student->photo) }}" alt="Photo"
                             class="rounded-circle" style="width:90px;height:90px;object-fit:cover;">
                        @else
                        <div style="width:90px;height:90px;border-radius:50%;background:#4f46e5;display:flex;align-items:center;justify-content:center;color:#fff;font-size:2rem;margin:0 auto;">
                            <i class="bi bi-person-fill"></i>
                        </div>
                        @endif
                    </div>
                    <h5 class="fw-semibold mb-0">{{ $student->user->name }}</h5>
                    <p class="text-muted small">{{ $student->enrollment_number ?? 'Enrollment pending' }}</p>
                    <table class="table table-sm text-start mb-0 mt-2" style="font-size:.82rem">
                        <tr><td class="text-muted border-0 py-1">Program</td><td class="border-0 py-1">{{ $student->program->name ?? '—' }}</td></tr>
                        <tr><td class="text-muted border-0 py-1">Batch</td><td class="border-0 py-1">{{ $student->batch->name ?? '—' }}</td></tr>
                        <tr><td class="text-muted border-0 py-1">Status</td><td class="border-0 py-1"><span class="badge bg-success">{{ ucfirst($student->status ?? 'active') }}</span></td></tr>
                        @if($student->mentor)
                        <tr><td class="text-muted border-0 py-1">Mentor</td><td class="border-0 py-1">{{ $student->mentor->name }}</td></tr>
                        @endif
                    </table>
                </div>
            </div>
        </div>

        {{-- Edit Form --}}
        <div class="col-lg-8">
            <form method="POST" action="{{ route('student.profile.update') }}" enctype="multipart/form-data">
                @csrf
                @method('PATCH')

                {{-- Personal Info --}}
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header fw-semibold"><i class="bi bi-person me-2"></i>Personal Information</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="name" value="{{ old('name', $student->user->name) }}" class="form-control @error('name') is-invalid @enderror">
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email <span class="text-muted">(cannot change)</span></label>
                                <input type="email" value="{{ $student->user->email }}" class="form-control" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" value="{{ old('phone', $student->phone) }}" class="form-control @error('phone') is-invalid @enderror">
                                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <textarea name="address" rows="2" class="form-control @error('address') is-invalid @enderror">{{ old('address', $student->address) }}</textarea>
                                @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label">Profile Photo <span class="text-muted">(JPG/PNG, max 2MB)</span></label>
                                <input type="file" name="photo" accept="image/*" class="form-control @error('photo') is-invalid @enderror">
                                @error('photo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Guardian / Emergency Contact --}}
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header fw-semibold"><i class="bi bi-people me-2"></i>Guardian / Emergency Contact</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Guardian Name</label>
                                <input type="text" name="guardian_name" value="{{ old('guardian_name', $student->guardian_name) }}" class="form-control @error('guardian_name') is-invalid @enderror">
                                @error('guardian_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Guardian Phone</label>
                                <input type="text" name="guardian_phone" value="{{ old('guardian_phone', $student->guardian_phone) }}" class="form-control @error('guardian_phone') is-invalid @enderror">
                                @error('guardian_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Change Password --}}
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header fw-semibold"><i class="bi bi-lock me-2"></i>Change Password <span class="text-muted fw-normal">(leave blank to keep current)</span></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">New Password</label>
                                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password">
                                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-check-lg me-1"></i>Save Changes
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
