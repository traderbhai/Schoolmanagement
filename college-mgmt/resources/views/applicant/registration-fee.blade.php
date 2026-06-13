@extends('layouts.applicant')

@section('title', 'Registration Fee')
@section('page-title', 'Registration Fee')

@section('content')
<div class="container-fluid p-4">
    <div class="mb-4">
        <a href="{{ route('applicant.dashboard') }}" class="text-muted small">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
        <h2 class="fw-bold mb-0 mt-1">Registration Fee</h2>
        <p class="text-muted mb-0">{{ $applicant->application_number }} - {{ $applicant->program->name ?? 'Program' }}</p>
    </div>

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1">Please fix the errors below.</div>
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($applicant->hasRegistrationFeePaid())
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="alert alert-success mb-0">
                    <i class="bi bi-check-circle me-1"></i>
                    Registration fee of Rs. {{ number_format($applicant->registration_fee_amount, 2) }}
                    was recorded on {{ $applicant->registration_fee_paid_at->format('d M Y, h:i A') }}.
                    Receipt/reference: <strong>{{ $applicant->registration_fee_receipt }}</strong>.
                </div>
            </div>
        </div>
    @else
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent fw-semibold">
                        <i class="bi bi-credit-card me-2"></i>Submit Registration Fee Details
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" action="{{ route('applicant.registration-fee.store') }}">
                            @csrf

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="amount_paid" class="form-label fw-semibold">Amount Paid <span class="text-danger">*</span></label>
                                    <input type="number" name="amount_paid" id="amount_paid" class="form-control @error('amount_paid') is-invalid @enderror" min="1" max="999999" step="0.01" value="{{ old('amount_paid') }}" required>
                                    @error('amount_paid')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-md-6">
                                    <label for="payment_method" class="form-label fw-semibold">Payment Method <span class="text-danger">*</span></label>
                                    <select name="payment_method" id="payment_method" class="form-select @error('payment_method') is-invalid @enderror" required>
                                        <option value="">Select method</option>
                                        <option value="online" @selected(old('payment_method') === 'online')>Online Payment</option>
                                        <option value="bank_transfer" @selected(old('payment_method') === 'bank_transfer')>Bank Transfer</option>
                                        <option value="dd" @selected(old('payment_method') === 'dd')>Demand Draft</option>
                                        <option value="cash" @selected(old('payment_method') === 'cash')>Cash</option>
                                    </select>
                                    @error('payment_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-md-6">
                                    <label for="reference_number" class="form-label fw-semibold">Reference / UTR Number <span class="text-danger">*</span></label>
                                    <input type="text" name="reference_number" id="reference_number" class="form-control @error('reference_number') is-invalid @enderror" maxlength="100" value="{{ old('reference_number') }}" required>
                                    @error('reference_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-md-6">
                                    <label for="receipt_number" class="form-label fw-semibold">Receipt Number</label>
                                    <input type="text" name="receipt_number" id="receipt_number" class="form-control @error('receipt_number') is-invalid @enderror" maxlength="100" value="{{ old('receipt_number') }}">
                                    @error('receipt_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="alert alert-info small mt-4 mb-4">
                                <i class="bi bi-info-circle me-1"></i>
                                Save the payment reference exactly as shown by your bank or payment provider. The admission team may use this to reconcile your application.
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg me-1"></i>Save Fee Details
                                </button>
                                <a href="{{ route('applicant.dashboard') }}" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent fw-semibold">Why this matters</div>
                    <div class="card-body small text-muted">
                        Your application remains in draft until the registration fee details and all required sections are complete.
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
