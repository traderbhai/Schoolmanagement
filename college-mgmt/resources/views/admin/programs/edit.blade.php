@extends('layouts.admin')

@section('title', 'Edit Program — Admin')
@section('page-title', 'Edit Program')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.programs.index') }}">Programs</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0 fw-bold">Edit: {{ $program->name }}</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.programs.update', $program) }}">
                    @csrf
                    @method('PUT')
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Program Name <span class="text-danger">*</span></label>
                            <input aria-label="Name" type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name', $program->name) }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Code <span class="text-danger">*</span></label>
                            <input aria-label="Code" type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                                value="{{ old('code', $program->code) }}" maxlength="20" required>
                            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Abbreviation</label>
                            <input aria-label="Abbreviation" type="text" name="abbreviation" class="form-control"
                                value="{{ old('abbreviation', $program->abbreviation) }}" maxlength="10">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Department <span class="text-danger">*</span></label>
                            <select aria-label="Department" name="department_id" class="form-select @error('department_id') is-invalid @enderror" required>
                                <option value="">Select Department</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" {{ old('department_id', $program->department_id) == $dept->id ? 'selected' : '' }}>
                                        {{ $dept->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('department_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Term System <span class="text-danger">*</span></label>
                            <select name="system_type" id="systemType" class="form-select @error('system_type') is-invalid @enderror" required>
                                @foreach(['semester','trimester','annual','quarter'] as $type)
                                    <option value="{{ $type }}" {{ old('system_type', $program->system_type) == $type ? 'selected' : '' }}>{{ ucfirst($type) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Duration (Years) <span class="text-danger">*</span></label>
                            <select name="duration_years" id="durationYears" class="form-select" required>
                                @for($y = 1; $y <= 5; $y++)
                                    <option value="{{ $y }}" {{ old('duration_years', $program->duration_years) == $y ? 'selected' : '' }}>{{ $y }} Year{{ $y > 1 ? 's' : '' }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Total Terms <span class="text-danger">*</span></label>
                            <input type="number" name="total_terms" id="totalTerms" class="form-control @error('total_terms') is-invalid @enderror"
                                value="{{ old('total_terms', $program->total_terms) }}" min="1" max="12" required>
                            <div class="form-text" id="termsHint"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Default Intake Capacity</label>
                            <input aria-label="Default Intake Capacity" type="number" name="default_intake_capacity" class="form-control"
                                value="{{ old('default_intake_capacity', $program->default_intake_capacity) }}" min="1" max="500">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea aria-label="Description" name="description" class="form-control" rows="2">{{ old('description', $program->description) }}</textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" value="1" class="form-check-input"
                                    id="isActive" {{ old('is_active', $program->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label" for="isActive">Program is active</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Update Program
                        </button>
                        <a href="{{ route('admin.programs.show', $program) }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var systemType  = document.getElementById('systemType');
    var durationYrs = document.getElementById('durationYears');
    var termsHint   = document.getElementById('termsHint');
    var termsPerYear = { semester: 2, trimester: 3, annual: 1, quarter: 4 };

    function updateHint() {
        var t = systemType.value;
        var d = parseInt(durationYrs.value) || 2;
        termsHint.textContent = d + '-year ' + t + ' = ' + ((termsPerYear[t] || 2) * d) + ' ' + t + 's';
    }
    systemType.addEventListener('change', updateHint);
    durationYrs.addEventListener('change', updateHint);
    updateHint();
})();
</script>
@endpush
