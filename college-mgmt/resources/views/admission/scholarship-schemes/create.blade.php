@extends('layouts.admin')

@section('title', 'New Scholarship Scheme')

@section('content')
<div class="mb-4">
    <a href="{{ route('admission.scholarship-schemes.index') }}" class="text-muted small"><i class="bi bi-arrow-left"></i> Back to Schemes</a>
    <h2 class="fw-bold mb-0 mt-1">New Scholarship Scheme</h2>
</div>

<form action="{{ route('admission.scholarship-schemes.store') }}" method="POST">
    @csrf
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="name" class="form-label fw-semibold">Scheme Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name') }}" required placeholder="e.g. AICTE Merit Scholarship">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label for="scheme_code" class="form-label fw-semibold">Scheme Code <span class="text-danger">*</span></label>
                    <input type="text" name="scheme_code" id="scheme_code" class="form-control @error('scheme_code') is-invalid @enderror"
                           value="{{ old('scheme_code') }}" required placeholder="e.g. AICTE-MERIT-2024" style="text-transform:uppercase">
                    @error('scheme_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label for="type" class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                    <select name="type" id="type" class="form-select @error('type') is-invalid @enderror" required>
                        <option value="">— Select —</option>
                        <option value="merit"       {{ old('type') === 'merit'       ? 'selected' : '' }}>Merit</option>
                        <option value="need_based"  {{ old('type') === 'need_based'  ? 'selected' : '' }}>Need Based</option>
                        <option value="government"  {{ old('type') === 'government'  ? 'selected' : '' }}>Government</option>
                        <option value="aicte"       {{ old('type') === 'aicte'       ? 'selected' : '' }}>AICTE</option>
                        <option value="institution" {{ old('type') === 'institution' ? 'selected' : '' }}>Institution</option>
                    </select>
                    @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="program_id" class="form-label fw-semibold">Applicable Program</label>
                    <select name="program_id" id="program_id" class="form-select @error('program_id') is-invalid @enderror">
                        <option value="">All Programs</option>
                        @foreach($programs as $prog)
                            <option value="{{ $prog->id }}" {{ old('program_id') == $prog->id ? 'selected' : '' }}>{{ $prog->name }}</option>
                        @endforeach
                    </select>
                    @error('program_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="max_amount" class="form-label fw-semibold">Maximum Amount (₹) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">₹</span>
                        <input type="number" name="max_amount" id="max_amount" class="form-control @error('max_amount') is-invalid @enderror"
                               value="{{ old('max_amount', 0) }}" min="0" step="100" required>
                    </div>
                    @error('max_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="available_seats" class="form-label fw-semibold">Available Seats</label>
                    <input type="number" name="available_seats" id="available_seats" class="form-control @error('available_seats') is-invalid @enderror"
                           value="{{ old('available_seats') }}" min="1" placeholder="Leave blank for unlimited">
                    @error('available_seats')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label for="criteria" class="form-label fw-semibold">Eligibility Criteria</label>
                    <textarea name="criteria" id="criteria" class="form-control @error('criteria') is-invalid @enderror"
                              rows="4" maxlength="2000" placeholder="Describe the eligibility criteria…">{{ old('criteria') }}</textarea>
                    @error('criteria')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="is_active">Active (available for awarding)</label>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('admission.scholarship-schemes.index') }}" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Create Scheme</button>
    </div>
</form>

@push('scripts')
<script>
document.getElementById('scheme_code')?.addEventListener('input', function() { this.value = this.value.toUpperCase(); });
</script>
@endpush
@endsection
