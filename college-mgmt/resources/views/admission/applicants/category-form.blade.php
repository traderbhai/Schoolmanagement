@extends('layouts.admin')

@section('title', 'Update Category & Exam Details — ' . $applicant->application_number)

@section('content')
<div class="mb-4">
    <a href="{{ route('admission.applicants.show', $applicant) }}" class="text-muted small">
        <i class="bi bi-arrow-left"></i> Back to Applicant
    </a>
    <h2 class="fw-bold mb-0 mt-1">Update Category &amp; Exam Details</h2>
    <p class="text-muted mb-0">
        <span class="font-monospace">{{ $applicant->application_number }}</span>
        &mdash; {{ $applicant->user->name ?? 'Unknown' }}
    </p>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle me-2"></i><strong>Please fix the errors below.</strong>
        <ul class="mb-0 mt-1">
            @foreach($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<form action="{{ route('admission.applicants.category.update', $applicant) }}" method="POST">
    @csrf
    @method('PUT')

    {{-- Section 1: Reservation Category --}}
    <div class="card mb-4">
        <div class="card-header fw-semibold">
            <i class="bi bi-tags me-2"></i>Reservation Category
        </div>
        <div class="card-body">
            <div class="row g-3">

                <div class="col-md-6">
                    <label for="category" class="form-label">Category</label>
                    <select name="category" id="category" class="form-select @error('category') is-invalid @enderror">
                        <option value="">— Select Category —</option>
                        <option value="general"  {{ old('category', $applicant->category) === 'general'  ? 'selected' : '' }}>General</option>
                        <option value="obc"      {{ old('category', $applicant->category) === 'obc'      ? 'selected' : '' }}>OBC</option>
                        <option value="obc_ncl"  {{ old('category', $applicant->category) === 'obc_ncl'  ? 'selected' : '' }}>OBC (Non-Creamy Layer)</option>
                        <option value="sc"       {{ old('category', $applicant->category) === 'sc'       ? 'selected' : '' }}>SC</option>
                        <option value="st"       {{ old('category', $applicant->category) === 'st'       ? 'selected' : '' }}>ST</option>
                        <option value="ews"      {{ old('category', $applicant->category) === 'ews'      ? 'selected' : '' }}>EWS</option>
                        <option value="pwd"      {{ old('category', $applicant->category) === 'pwd'      ? 'selected' : '' }}>PwD</option>
                        <option value="nri"      {{ old('category', $applicant->category) === 'nri'      ? 'selected' : '' }}>NRI</option>
                    </select>
                    @error('category')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label for="sub_category" class="form-label">Sub-Category</label>
                    <input
                        type="text"
                        name="sub_category"
                        id="sub_category"
                        class="form-control @error('sub_category') is-invalid @enderror"
                        placeholder="e.g. Creamy Layer, Ex-Servicemen"
                        value="{{ old('sub_category', $applicant->sub_category) }}"
                        maxlength="100"
                    >
                    @error('sub_category')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input
                            type="checkbox"
                            name="is_pwd"
                            id="is_pwd"
                            class="form-check-input @error('is_pwd') is-invalid @enderror"
                            value="1"
                            {{ old('is_pwd', $applicant->is_pwd) ? 'checked' : '' }}
                        >
                        <label class="form-check-label" for="is_pwd">
                            Person with Disability (PwD)
                        </label>
                        @error('is_pwd')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-md-6" id="pwd_certificate_field" style="{{ old('is_pwd', $applicant->is_pwd) ? '' : 'display:none;' }}">
                    <label for="pwd_certificate_number" class="form-label">PwD Certificate No.</label>
                    <input
                        type="text"
                        name="pwd_certificate_number"
                        id="pwd_certificate_number"
                        class="form-control @error('pwd_certificate_number') is-invalid @enderror"
                        value="{{ old('pwd_certificate_number', $applicant->pwd_certificate_number) }}"
                        maxlength="100"
                    >
                    @error('pwd_certificate_number')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

            </div>
        </div>
    </div>

    {{-- Section 2: Entrance Exam Details --}}
    <div class="card mb-4">
        <div class="card-header fw-semibold">
            <i class="bi bi-pencil-square me-2"></i>Entrance Exam Details
        </div>
        <div class="card-body">
            <div class="row g-3">

                <div class="col-md-6">
                    <label for="entrance_exam_name" class="form-label">Exam Name</label>
                    <input
                        type="text"
                        name="entrance_exam_name"
                        id="entrance_exam_name"
                        class="form-control @error('entrance_exam_name') is-invalid @enderror"
                        placeholder="e.g. JEE Main, CAT, MAT, GATE"
                        value="{{ old('entrance_exam_name', $applicant->entrance_exam_name) }}"
                        maxlength="255"
                    >
                    @error('entrance_exam_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label for="entrance_exam_roll_number" class="form-label">Roll Number</label>
                    <input
                        type="text"
                        name="entrance_exam_roll_number"
                        id="entrance_exam_roll_number"
                        class="form-control @error('entrance_exam_roll_number') is-invalid @enderror"
                        value="{{ old('entrance_exam_roll_number', $applicant->entrance_exam_roll_number) }}"
                        maxlength="100"
                    >
                    @error('entrance_exam_roll_number')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label for="entrance_exam_score" class="form-label">Score / Percentile</label>
                    <input
                        type="number"
                        name="entrance_exam_score"
                        id="entrance_exam_score"
                        class="form-control @error('entrance_exam_score') is-invalid @enderror"
                        step="0.01"
                        min="0"
                        max="999999.99"
                        value="{{ old('entrance_exam_score', $applicant->entrance_exam_score) }}"
                    >
                    @error('entrance_exam_score')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label for="entrance_exam_rank" class="form-label">Rank</label>
                    <input
                        type="number"
                        name="entrance_exam_rank"
                        id="entrance_exam_rank"
                        class="form-control @error('entrance_exam_rank') is-invalid @enderror"
                        min="1"
                        value="{{ old('entrance_exam_rank', $applicant->entrance_exam_rank) }}"
                    >
                    @error('entrance_exam_rank')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label for="entrance_exam_date" class="form-label">Exam Date</label>
                    <input
                        type="date"
                        name="entrance_exam_date"
                        id="entrance_exam_date"
                        class="form-control @error('entrance_exam_date') is-invalid @enderror"
                        value="{{ old('entrance_exam_date', $applicant->entrance_exam_date?->format('Y-m-d')) }}"
                    >
                    @error('entrance_exam_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save me-1"></i> Save Details
        </button>
        <a href="{{ route('admission.applicants.show', $applicant) }}" class="btn btn-outline-secondary">
            Cancel
        </a>
    </div>
</form>

<script>
    document.getElementById('is_pwd').addEventListener('change', function () {
        var field = document.getElementById('pwd_certificate_field');
        field.style.display = this.checked ? '' : 'none';
        if (!this.checked) {
            document.getElementById('pwd_certificate_number').value = '';
        }
    });
</script>
@endsection
