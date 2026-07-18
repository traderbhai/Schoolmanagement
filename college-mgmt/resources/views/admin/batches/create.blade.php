@extends('layouts.admin')

@section('title', 'Add Batch — Admin')
@section('page-title', 'Add Batch')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.batches.index') }}">Batches</a></li>
    <li class="breadcrumb-item active">Add Batch</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0 fw-bold">New Batch</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.batches.store') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Program <span class="text-danger">*</span></label>
                            <select name="program_id" id="programSelect" class="form-select @error('program_id') is-invalid @enderror" required>
                                <option value="">Select Program</option>
                                @foreach($programs as $prog)
                                    <option value="{{ $prog->id }}"
                                        data-code="{{ $prog->code }}"
                                        data-years="{{ $prog->duration_years }}"
                                        {{ (old('program_id', request('program_id')) == $prog->id) ? 'selected' : '' }}>
                                        {{ $prog->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('program_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Academic Year</label>
                            <select aria-label="Academic Year" name="academic_year_id" class="form-select">
                                <option value="">None</option>
                                @foreach($academicYears as $ay)
                                    <option value="{{ $ay->id }}" {{ old('academic_year_id') == $ay->id || $ay->is_current ? 'selected' : '' }}>
                                        {{ $ay->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Batch Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="batchName" class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name') }}" placeholder="e.g. PGDM Batch 2024-26" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" id="batchCode" class="form-control @error('code') is-invalid @enderror"
                                value="{{ old('code') }}" placeholder="PGDM-24" maxlength="20" required>
                            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" id="startDate" class="form-control @error('start_date') is-invalid @enderror"
                                value="{{ old('start_date') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">End Date <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" id="endDate" class="form-control @error('end_date') is-invalid @enderror"
                                value="{{ old('end_date') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Intake Capacity <span class="text-danger">*</span></label>
                            <input aria-label="Intake Capacity" type="number" name="intake_capacity" class="form-control"
                                value="{{ old('intake_capacity', 60) }}" min="1" max="500" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select aria-label="Status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                                @foreach(['upcoming','active','completed','cancelled'] as $s)
                                    <option value="{{ $s }}" {{ old('status', 'upcoming') == $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea aria-label="Description" name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" name="auto_generate_terms" value="1" class="form-check-input"
                                    id="autoTerms" {{ old('auto_generate_terms', '1') ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="autoTerms">
                                    Auto-generate terms on create
                                </label>
                                <div class="form-text">Terms will be created based on the program's total terms setting (e.g. Semester I, Semester II...).</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Create Batch
                        </button>
                        <a href="{{ route('admin.batches.index') }}" class="btn btn-outline-secondary">Cancel</a>
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
    var programSelect = document.getElementById('programSelect');
    var batchName     = document.getElementById('batchName');
    var batchCode     = document.getElementById('batchCode');
    var startDate     = document.getElementById('startDate');
    var endDate       = document.getElementById('endDate');

    programSelect.addEventListener('change', function () {
        var opt  = this.options[this.selectedIndex];
        var code = opt.getAttribute('data-code') || '';
        var yrs  = parseInt(opt.getAttribute('data-years')) || 2;
        var year = new Date().getFullYear();
        var yr2  = (year + yrs) % 100;
        var yr2Full = year + yrs;
        var shortYr = String(year).slice(2);

        if (code && !batchName.value) {
            batchName.value = code + ' Batch ' + year + '-' + yr2;
        }
        if (code && !batchCode.value) {
            batchCode.value = (code + '-' + shortYr).slice(0, 20);
        }
        if (!startDate.value) {
            startDate.value = year + '-07-01';
        }
        if (!endDate.value) {
            endDate.value = yr2Full + '-06-30';
        }
    });

    // Trigger if pre-selected
    if (programSelect.value) programSelect.dispatchEvent(new Event('change'));
})();
</script>
@endpush
