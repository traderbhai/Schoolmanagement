@extends('layouts.admin')

@section('title', 'Edit - ' . $scholarshipScheme->name)

@section('content')
<div class="mb-4">
    <a href="{{ route('admission.scholarship-schemes.index') }}" class="text-muted small"><i class="bi bi-arrow-left"></i> Back to Schemes</a>
    <h2 class="fw-bold mb-0 mt-1">Edit Scheme</h2>
    <p class="text-muted mb-0">{{ $scholarshipScheme->name }}</p>
</div>

@if($errors->has('scholarship_scheme'))
    <div class="alert alert-warning mb-4">
        <div class="fw-semibold">Scheme contract is locked for active scholarship records.</div>
        <div>{{ $errors->first('scholarship_scheme') }}</div>
    </div>
@endif

<form action="{{ route('admission.scholarship-schemes.update', $scholarshipScheme) }}" method="POST">
    @csrf
    @method('PUT')
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="name" class="form-label fw-semibold">Scheme Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $scholarshipScheme->name) }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label for="scheme_code" class="form-label fw-semibold">Scheme Code <span class="text-danger">*</span></label>
                    <input type="text" name="scheme_code" id="scheme_code" class="form-control @error('scheme_code') is-invalid @enderror"
                           value="{{ old('scheme_code', $scholarshipScheme->scheme_code) }}" required style="text-transform:uppercase">
                    @error('scheme_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label for="type" class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                    <select name="type" id="type" class="form-select @error('type') is-invalid @enderror" required>
                        @foreach(['merit','need_based','government','aicte','institution'] as $t)
                            <option value="{{ $t }}" {{ old('type', $scholarshipScheme->type) === $t ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_', ' ', $t)) }}
                            </option>
                        @endforeach
                    </select>
                    @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="program_id" class="form-label fw-semibold">Applicable Program</label>
                    <select name="program_id" id="program_id" class="form-select @error('program_id') is-invalid @enderror">
                        <option value="">All Programs</option>
                        @foreach($programs as $prog)
                            <option value="{{ $prog->id }}" {{ old('program_id', $scholarshipScheme->program_id) == $prog->id ? 'selected' : '' }}>{{ $prog->name }}</option>
                        @endforeach
                    </select>
                    @error('program_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="max_amount" class="form-label fw-semibold">Maximum Amount (Rs.) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">Rs.</span>
                        <input type="number" name="max_amount" id="max_amount" class="form-control @error('max_amount') is-invalid @enderror"
                               value="{{ old('max_amount', $scholarshipScheme->max_amount) }}" min="0" step="100" required>
                    </div>
                    @error('max_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="available_seats" class="form-label fw-semibold">Available Seats</label>
                    <input type="number" name="available_seats" id="available_seats" class="form-control @error('available_seats') is-invalid @enderror"
                           value="{{ old('available_seats', $scholarshipScheme->available_seats) }}" min="1" placeholder="Unlimited">
                    @error('available_seats')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label for="criteria" class="form-label fw-semibold">Eligibility Criteria</label>
                    <textarea name="criteria" id="criteria" class="form-control @error('criteria') is-invalid @enderror"
                              rows="4" maxlength="2000">{{ old('criteria', $scholarshipScheme->criteria) }}</textarea>
                    @error('criteria')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="min_cgpa" class="form-label fw-semibold">Minimum CGPA</label>
                    <input type="number" name="min_cgpa" id="min_cgpa" class="form-control @error('min_cgpa') is-invalid @enderror"
                           value="{{ old('min_cgpa', $scholarshipScheme->min_cgpa) }}" min="0" max="10" step="0.01" placeholder="e.g. 7.50">
                    @error('min_cgpa')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="max_family_income" class="form-label fw-semibold">Max Family Income</label>
                    <input type="number" name="max_family_income" id="max_family_income" class="form-control @error('max_family_income') is-invalid @enderror"
                           value="{{ old('max_family_income', $scholarshipScheme->max_family_income) }}" min="0" step="1000" placeholder="Annual income">
                    @error('max_family_income')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check form-switch">
                        <input type="hidden" name="requires_document" value="0">
                        <input class="form-check-input" type="checkbox" name="requires_document" id="requires_document" value="1"
                               {{ old('requires_document', $scholarshipScheme->requires_document) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="requires_document">Require proof document</label>
                    </div>
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                               {{ old('is_active', $scholarshipScheme->is_active) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="is_active">Active and available for awarding</label>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('admission.scholarship-schemes.index') }}" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save Changes</button>
    </div>
</form>
@endsection
