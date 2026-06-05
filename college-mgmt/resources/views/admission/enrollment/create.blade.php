@extends('layouts.admin')

@section('title', 'Enroll Applicant')

@section('content')
<div class="mb-3">
    <a href="{{ route('admission.applicants.show', $applicant) }}" class="text-muted small"><i class="bi bi-arrow-left"></i> Back to Applicant</a>
    <h2 class="fw-bold mb-0 mt-1">Confirm Enrollment</h2>
    <div class="text-muted small">{{ $applicant->application_number }} — {{ $applicant->program->name ?? '—' }}</div>
</div>

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

{{-- Pre-condition checklist --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header fw-semibold bg-transparent">Pre-Enrollment Checklist</div>
    <div class="card-body">
        <ul class="list-unstyled mb-0">
            <li class="mb-2">
                @if($isSelected)
                    <span class="badge bg-success me-2"><i class="bi bi-check-lg"></i></span> Applicant status is <strong>Selected</strong>
                @else
                    <span class="badge bg-danger me-2"><i class="bi bi-x-lg"></i></span> Applicant status must be <strong>Selected</strong> (current: {{ $applicant->status_label }})
                @endif
            </li>
            <li class="mb-2">
                @if($hasVerifiedPayment)
                    <span class="badge bg-success me-2"><i class="bi bi-check-lg"></i></span> Verified admission payment exists
                @else
                    <span class="badge bg-danger me-2"><i class="bi bi-x-lg"></i></span> No verified payment found
                @endif
            </li>
            <li class="mb-2">
                @if($hasVerifiedDocs)
                    <span class="badge bg-success me-2"><i class="bi bi-check-lg"></i></span> Verified documents uploaded
                @else
                    <span class="badge bg-warning text-dark me-2"><i class="bi bi-exclamation-triangle"></i></span> No verified documents (enrollment can still proceed)
                @endif
            </li>
            <li class="mb-2">
                @if(!$alreadyEnrolled)
                    <span class="badge bg-success me-2"><i class="bi bi-check-lg"></i></span> No existing enrollment
                @else
                    <span class="badge bg-danger me-2"><i class="bi bi-x-lg"></i></span> Already enrolled
                @endif
            </li>
        </ul>
    </div>
</div>

{{-- Applicant Summary --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header fw-semibold bg-transparent">Applicant Summary</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4"><div class="small text-muted">Name</div><div class="fw-medium">{{ $applicant->user->name ?? ($applicant->personal_data['name'] ?? '—') }}</div></div>
            <div class="col-md-4"><div class="small text-muted">Email</div><div class="fw-medium">{{ $applicant->user->email ?? ($applicant->personal_data['email'] ?? '—') }}</div></div>
            <div class="col-md-4"><div class="small text-muted">Program</div><div class="fw-medium">{{ $applicant->program->name ?? '—' }}</div></div>
            <div class="col-md-4"><div class="small text-muted">Batch</div><div class="fw-medium">{{ $applicant->batch->name ?? '—' }}</div></div>
            <div class="col-md-4"><div class="small text-muted">Application #</div><div class="fw-medium font-monospace">{{ $applicant->application_number }}</div></div>
            <div class="col-md-4"><div class="small text-muted">Status</div><div><span class="{{ $applicant->status_badge }}">{{ $applicant->status_label }}</span></div></div>
        </div>
    </div>
</div>

@if($alreadyEnrolled)
    <div class="alert alert-warning">This applicant is already enrolled.</div>
@else
{{-- Enrollment Form --}}
<form action="{{ route('admission.enrollment.store', $applicant) }}" method="POST">
    @csrf

    @if(!$isSelected || !$hasVerifiedPayment)
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Pre-conditions are not fully met. Admins may override and proceed, but errors may occur.
    </div>
    @endif

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header fw-semibold bg-transparent">Enrollment Details</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Roll Number <span class="text-danger">*</span></label>
                    <input type="text" name="roll_number" class="form-control @error('roll_number') is-invalid @enderror"
                           value="{{ old('roll_number') }}" placeholder="e.g. PGDM-24-001" required>
                    @error('roll_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Specialization</label>
                    <select name="specialization_id" class="form-select">
                        <option value="">— None —</option>
                        @foreach($specializations as $s)
                            <option value="{{ $s->id }}" @selected(old('specialization_id') == $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Term</label>
                    <select name="term_id" class="form-select">
                        <option value="">— None —</option>
                        @foreach($terms as $t)
                            <option value="{{ $t->id }}" @selected(old('term_id') == $t->id)>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Notes</label>
                    <textarea name="notes" rows="3" class="form-control" placeholder="Optional notes…">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-success px-4">
            <i class="bi bi-person-check me-1"></i> Confirm Enrollment
        </button>
        <a href="{{ route('admission.applicants.show', $applicant) }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
@endif
@endsection
