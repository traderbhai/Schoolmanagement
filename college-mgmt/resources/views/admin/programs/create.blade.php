@extends('layouts.admin')

@section('title', 'Add Program — Admin')
@section('page-title', 'Add Program')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.programs.index') }}">Programs</a></li>
    <li class="breadcrumb-item active">Add Program</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0 fw-bold">New Academic Program</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.programs.store') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Program Name <span class="text-danger">*</span></label>
                            <input aria-label="Program name" type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name') }}" placeholder="e.g. Post Graduate Diploma in Management" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Code <span class="text-danger">*</span></label>
                            <input aria-label="PGDM" type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                                value="{{ old('code') }}" placeholder="PGDM" maxlength="20" required>
                            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Abbreviation</label>
                            <input aria-label="PGDM" type="text" name="abbreviation" class="form-control"
                                value="{{ old('abbreviation') }}" placeholder="PGDM" maxlength="10">
                            <div class="form-text">Short form used in reports</div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Department <span class="text-danger">*</span></label>
                            <select aria-label="Department" name="department_id" class="form-select @error('department_id') is-invalid @enderror" required>
                                <option value="">Select Department</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>
                                        {{ $dept->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('department_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Term System <span class="text-danger">*</span></label>
                            <select name="system_type" id="systemType" class="form-select @error('system_type') is-invalid @enderror" required>
                                <option value="semester"  {{ old('system_type', 'semester') == 'semester'  ? 'selected' : '' }}>Semester</option>
                                <option value="trimester" {{ old('system_type') == 'trimester' ? 'selected' : '' }}>Trimester</option>
                                <option value="annual"    {{ old('system_type') == 'annual'    ? 'selected' : '' }}>Annual</option>
                                <option value="quarter"   {{ old('system_type') == 'quarter'   ? 'selected' : '' }}>Quarter</option>
                            </select>
                            @error('system_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Duration (Years) <span class="text-danger">*</span></label>
                            <select name="duration_years" id="durationYears" class="form-select @error('duration_years') is-invalid @enderror" required>
                                @for($y = 1; $y <= 5; $y++)
                                    <option value="{{ $y }}" {{ old('duration_years', 2) == $y ? 'selected' : '' }}>{{ $y }} Year{{ $y > 1 ? 's' : '' }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Total Terms <span class="text-danger">*</span></label>
                            <input type="number" name="total_terms" id="totalTerms" class="form-control @error('total_terms') is-invalid @enderror"
                                value="{{ old('total_terms', 4) }}" min="1" max="12" required>
                            <div class="form-text" id="termsHint">e.g. 2-year semester = 4 semesters</div>
                            @error('total_terms')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Default Intake Capacity</label>
                            <input aria-label="Default Intake Capacity" type="number" name="default_intake_capacity" class="form-control"
                                value="{{ old('default_intake_capacity', 60) }}" min="1" max="500">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea aria-label="Brief description of the program" name="description" class="form-control" rows="2"
                                placeholder="Brief description of the program">{{ old('description') }}</textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" value="1" class="form-check-input"
                                    id="isActive" {{ old('is_active', '1') ? 'checked' : '' }}>
                                <label class="form-check-label" for="isActive">Program is active</label>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info mt-3 mb-0 py-2 px-3" style="font-size:.82rem;">
                        <i class="bi bi-info-circle me-1"></i>
                        <strong>Term calculation:</strong> 2-year semester program = 4 semesters &bull; 2-year trimester = 6 trimesters &bull; 1-year annual = 2 terms.
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Create Program
                        </button>
                        <a href="{{ route('admin.programs.index') }}" class="btn btn-outline-secondary">Cancel</a>
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
    var systemType   = document.getElementById('systemType');
    var durationYrs  = document.getElementById('durationYears');
    var totalTerms   = document.getElementById('totalTerms');
    var termsHint    = document.getElementById('termsHint');

    var termsPerYear = { semester: 2, trimester: 3, annual: 1, quarter: 4 };

    function suggest() {
        var t = systemType.value;
        var d = parseInt(durationYrs.value) || 2;
        var suggested = (termsPerYear[t] || 2) * d;
        totalTerms.value = suggested;
        termsHint.textContent = d + '-year ' + t + ' = ' + suggested + ' ' + t + 's';
    }

    systemType.addEventListener('change', suggest);
    durationYrs.addEventListener('change', suggest);
})();
</script>
@endpush
