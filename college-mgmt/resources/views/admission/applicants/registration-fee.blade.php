@extends('layouts.admin')

@section('title', 'Registration Fee - ' . ($applicant->application_number ?? 'Application number missing'))

@section('content')
<div class="mb-4">
    <a href="{{ route('admission.applicants.show', $applicant) }}" class="text-muted small">
        <i class="bi bi-arrow-left"></i> Back to Applicant
    </a>
    <h2 class="fw-bold mb-0 mt-1">Registration Fee</h2>
    <p class="text-muted mb-0">
        <span class="font-monospace">{{ $applicant->application_number ?? 'Application number missing' }}</span>
        - {{ $applicant->user->name ?? 'Applicant name missing' }}
        @if($applicant->program)
            - {{ $applicant->program->name }}
        @else
            - Program not assigned
        @endif
    </p>
</div>

<div class="alert alert-light border mb-4">
    <div class="row g-2">
        <div class="col-sm-4">
            <span class="text-muted small">Applicant</span><br>
            <strong>{{ $applicant->user->name ?? 'Applicant name missing' }}</strong>
        </div>
        <div class="col-sm-4">
            <span class="text-muted small">Application No.</span><br>
            <strong class="font-monospace">{{ $applicant->application_number ?? 'Application number missing' }}</strong>
        </div>
        <div class="col-sm-4">
            <span class="text-muted small">Program</span><br>
            <strong>{{ $applicant->program->name ?? 'Program not assigned' }}</strong>
        </div>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if($applicant->hasRegistrationFeePaid())
    <div class="alert alert-success">
        <i class="bi bi-check-circle-fill me-2"></i>
        <strong>Fee Already Recorded.</strong>
        Registration fee of
        <strong>Rs. {{ number_format($applicant->registration_fee_amount, 2) }}</strong>
        was recorded on
        <strong>{{ $applicant->registration_fee_paid_at->format('d M Y H:i') }}</strong>.
        Receipt: <strong>{{ $applicant->registration_fee_receipt ?? 'Receipt reference missing' }}</strong>.
    </div>

    <a href="{{ route('admission.applicants.show', $applicant) }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to Applicant
    </a>

@elseif($registrationFeeLocked ?? false)
    <div class="alert alert-warning">
        <i class="bi bi-lock-fill me-2"></i>
        <strong>Registration fee recording is locked.</strong>
        This application is already in a final admission state, so new registration fee details cannot be recorded through the standard workflow.
    </div>

    <a href="{{ route('admission.applicants.show', $applicant) }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to Applicant
    </a>

@else
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

    <div class="alert alert-info border-0 shadow-sm small">
        <div class="fw-semibold mb-1">Staff registration-fee recording sequence</div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge text-bg-light border">1. Confirm applicant identity</span>
            <span class="badge text-bg-light border">2. Verify payment reference</span>
            <span class="badge text-bg-light border">3. Upload proof when available</span>
            <span class="badge text-bg-light border">4. Record payment</span>
            <span class="badge text-bg-light border">5. Applicant can continue submission</span>
        </div>
        <div class="text-muted mt-2">Use this page only after staff have verified the payment source. Duplicate references are blocked to protect financial records.</div>
    </div>

    <div class="card mb-4">
        <div class="card-header fw-semibold">
            <i class="bi bi-cash-coin me-2"></i>Record Registration Fee Payment
        </div>
        <div class="card-body">
            <form
                action="{{ route('admission.applicants.registration-fee.store', $applicant) }}"
                method="POST"
                enctype="multipart/form-data"
            >
                @csrf

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="amount_paid" class="form-label">
                            Amount Paid (Rs.) <span class="text-danger">*</span>
                        </label>
                        <input
                            type="number"
                            name="amount_paid"
                            id="amount_paid"
                            class="form-control @error('amount_paid') is-invalid @enderror"
                            step="0.01"
                            min="1"
                            max="999999"
                            value="{{ old('amount_paid') }}"
                            required
                        >
                        @error('amount_paid')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="payment_method" class="form-label">
                            Payment Method <span class="text-danger">*</span>
                        </label>
                        <select
                            name="payment_method"
                            id="payment_method"
                            class="form-select @error('payment_method') is-invalid @enderror"
                            required
                        >
                            <option value="">Select Method</option>
                            <option value="online" {{ old('payment_method') === 'online' ? 'selected' : '' }}>Online Payment</option>
                            <option value="bank_transfer" {{ old('payment_method') === 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                            <option value="dd" {{ old('payment_method') === 'dd' ? 'selected' : '' }}>Demand Draft</option>
                            <option value="cash" {{ old('payment_method') === 'cash' ? 'selected' : '' }}>Cash</option>
                        </select>
                        @error('payment_method')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="reference_number" class="form-label">
                            Reference / UTR Number <span class="text-danger">*</span>
                        </label>
                        <input
                            type="text"
                            name="reference_number"
                            id="reference_number"
                            class="form-control @error('reference_number') is-invalid @enderror"
                            maxlength="100"
                            value="{{ old('reference_number') }}"
                            required
                        >
                        @error('reference_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="receipt_number" class="form-label">Receipt Number</label>
                        <input
                            type="text"
                            name="receipt_number"
                            id="receipt_number"
                            class="form-control @error('receipt_number') is-invalid @enderror"
                            maxlength="100"
                            value="{{ old('receipt_number') }}"
                        >
                        @error('receipt_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label for="proof_document" class="form-label">Upload Payment Proof</label>
                        <input
                            type="file"
                            name="proof_document"
                            id="proof_document"
                            class="form-control @error('proof_document') is-invalid @enderror"
                            accept=".pdf,.jpg,.jpeg,.png"
                        >
                        <div class="form-text">Accepted formats: PDF, JPG, PNG. Max size: 2 MB.</div>
                        @error('proof_document')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <hr class="my-4">

                <div class="alert alert-info py-2 mb-3">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Note:</strong> Once the registration fee is recorded, the applicant can continue application submission from their portal.
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Record Payment
                    </button>
                    <a href="{{ route('admission.applicants.show', $applicant) }}" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection
