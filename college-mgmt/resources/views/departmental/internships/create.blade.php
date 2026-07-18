@extends('layouts.admin')

@section('title', 'Register Internship')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('cmc.internships.index') }}" class="btn btn-outline-secondary me-3" aria-label="Back to internships"><i class="bi bi-arrow-left"></i></a>
        <div>
            <h2 class="mb-0">Register Internship</h2>
            <p class="text-muted mb-0">Add an internship, industrial training, or live project record.</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm" style="max-width: 860px;">
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            <form action="{{ route('cmc.internships.store') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Student <span class="text-danger">*</span></label>
                        <select aria-label="Student" name="student_id" class="form-select @error('student_id') is-invalid @enderror" required>
                            <option value="">Select Student</option>
                            @foreach ($students as $student)
                                <option value="{{ $student->id }}" @selected(old('student_id') == $student->id)>
                                    {{ $student->user->name }} ({{ $student->enrollment_number ?? 'No enrollment number' }})
                                </option>
                            @endforeach
                        </select>
                        @error('student_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Company Name <span class="text-danger">*</span></label>
                        <input aria-label="Company Name" type="text" name="company_name" class="form-control @error('company_name') is-invalid @enderror" value="{{ old('company_name') }}" required>
                        @error('company_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Linked Company</label>
                        <select aria-label="Company" name="company_id" class="form-select @error('company_id') is-invalid @enderror">
                            <option value="">None</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}" @selected(old('company_id') == $company->id)>{{ $company->name }}</option>
                            @endforeach
                        </select>
                        @error('company_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Role / Title <span class="text-danger">*</span></label>
                        <input aria-label="Role Title" type="text" name="role_title" class="form-control @error('role_title') is-invalid @enderror" value="{{ old('role_title') }}" required>
                        @error('role_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                        <select aria-label="Type" name="type" class="form-select @error('type') is-invalid @enderror" required>
                            <option value="internship" @selected(old('type') === 'internship')>Internship</option>
                            <option value="industrial_training" @selected(old('type') === 'industrial_training')>Industrial Training</option>
                            <option value="live_project" @selected(old('type') === 'live_project')>Live Project</option>
                        </select>
                        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
                        <input aria-label="Start Date" type="date" name="start_date" class="form-control @error('start_date') is-invalid @enderror" value="{{ old('start_date') }}" required>
                        @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Planned End Date</label>
                        <input aria-label="End Date" type="date" name="end_date" class="form-control @error('end_date') is-invalid @enderror" value="{{ old('end_date') }}">
                        @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Stipend (Rs./month)</label>
                        <input aria-label="Stipend" type="number" name="stipend" class="form-control @error('stipend') is-invalid @enderror" value="{{ old('stipend') }}" min="0" step="0.01">
                        @error('stipend')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Supervisor Name</label>
                        <input aria-label="Supervisor Name" type="text" name="supervisor_name" class="form-control @error('supervisor_name') is-invalid @enderror" value="{{ old('supervisor_name') }}">
                        @error('supervisor_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Supervisor Email</label>
                        <input aria-label="Supervisor Email" type="email" name="supervisor_email" class="form-control @error('supervisor_email') is-invalid @enderror" value="{{ old('supervisor_email') }}">
                        @error('supervisor_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Description / Responsibilities</label>
                        <textarea aria-label="Description" name="description" class="form-control @error('description') is-invalid @enderror" rows="4">{{ old('description') }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Register Internship</button>
                    <a href="{{ route('cmc.internships.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
