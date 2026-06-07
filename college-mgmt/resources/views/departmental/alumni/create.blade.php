@extends('layouts.admin')
@section('title', 'Add Alumni Profile')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center mb-4 gap-3">
        <a href="{{ route('cmc.alumni.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
        <div>
            <h4 class="fw-bold mb-0">Add Alumni Profile</h4>
            <span class="text-muted small">Record a graduate's post-program journey</span>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
                    <form method="POST" action="{{ route('cmc.alumni.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Student <span class="text-danger">*</span></label>
                            <select name="student_id" class="form-select @error('student_id') is-invalid @enderror" required>
                                <option value="">Select Student</option>
                                @foreach($students as $s)
                                <option value="{{ $s->id }}" @selected(old('student_id')==$s->id)>
                                    {{ $s->user->name }} ({{ $s->enrollment_number }})
                                </option>
                                @endforeach
                            </select>
                            @error('student_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Graduation Year <span class="text-danger">*</span></label>
                                <input type="number" name="graduation_year" class="form-control @error('graduation_year') is-invalid @enderror"
                                    value="{{ old('graduation_year', now()->year) }}" min="2000" max="{{ now()->year }}" required>
                                @error('graduation_year')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">City</label>
                                <input type="text" name="city" class="form-control" value="{{ old('city') }}" placeholder="e.g. Mumbai">
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Current Employer</label>
                                <input type="text" name="current_employer" class="form-control" value="{{ old('current_employer') }}" placeholder="Company name">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Current Role</label>
                                <input type="text" name="current_role" class="form-control" value="{{ old('current_role') }}" placeholder="Job title">
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Current Salary (₹/year)</label>
                                <input type="number" name="current_salary" class="form-control" value="{{ old('current_salary') }}" min="0" step="1000">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">LinkedIn URL</label>
                                <input type="url" name="linkedin_url" class="form-control" value="{{ old('linkedin_url') }}" placeholder="https://linkedin.com/in/...">
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Feedback about Institution</label>
                            <textarea name="feedback" rows="3" class="form-control" placeholder="Alumni's feedback...">{{ old('feedback') }}</textarea>
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
