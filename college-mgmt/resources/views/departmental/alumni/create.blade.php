@extends('layouts.admin')
@section('title', 'Add Alumni Profile')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center mb-4 gap-3">
        <a href="{{ route('cmc.alumni.index') }}" class="btn btn-outline-secondary btn-sm" aria-label="Back to alumni list"><i class="bi bi-arrow-left"></i></a>
        <div>
            <h4 class="fw-bold mb-0">Add Alumni Profile</h4>
            <span class="text-muted small">Record a graduate's post-program journey for verified student networking.</span>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    @if($errors->any())
                        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                    @endif
                    <form method="POST" action="{{ route('cmc.alumni.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Student <span class="text-danger">*</span></label>
                            <select aria-label="Student" name="student_id" class="form-select @error('student_id') is-invalid @enderror" required>
                                <option value="">Select Student</option>
                                @foreach($students as $s)
                                <option value="{{ $s->id }}" @selected(old('student_id')==$s->id)>
                                    {{ $s->user->name }} ({{ $s->enrollment_number ?? 'No enrollment number' }})
                                </option>
                                @endforeach
                            </select>
                            @error('student_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-sm-4">
                                <label class="form-label fw-semibold">Graduation Year <span class="text-danger">*</span></label>
                                <input aria-label="Graduation Year" type="number" name="graduation_year" class="form-control @error('graduation_year') is-invalid @enderror" value="{{ old('graduation_year', now()->year) }}" min="2000" max="{{ now()->year }}" required>
                                @error('graduation_year')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label fw-semibold">City</label>
                                <input aria-label="City" type="text" name="city" class="form-control" value="{{ old('city') }}" placeholder="e.g. Mumbai">
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label fw-semibold">Country</label>
                                <input aria-label="Country" type="text" name="country" class="form-control" value="{{ old('country', 'India') }}">
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Current Employer</label>
                                <input aria-label="Company name" type="text" name="current_employer" class="form-control" value="{{ old('current_employer') }}" placeholder="Company name">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Current Role</label>
                                <input aria-label="Job title" type="text" name="current_role" class="form-control" value="{{ old('current_role') }}" placeholder="Job title">
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Current Salary (Rs./year)</label>
                                <input aria-label="Current Salary" type="number" name="current_salary" class="form-control" value="{{ old('current_salary') }}" min="0" step="1000">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">LinkedIn URL</label>
                                <input aria-label="LinkedIn profile URL" type="url" name="linkedin_url" class="form-control" value="{{ old('linkedin_url') }}" placeholder="https://linkedin.com/in/...">
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Feedback about Institution</label>
                            <textarea aria-label="Alumni feedback, referral notes, or mentoring interest" name="feedback" rows="3" class="form-control" placeholder="Alumni feedback, referral notes, or mentoring interest">{{ old('feedback') }}</textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Save Profile</button>
                            <a href="{{ route('cmc.alumni.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
