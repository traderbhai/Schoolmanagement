@extends('layouts.admin')

@section('title', 'New Refund Request — ' . ($applicant->user->name ?? 'Applicant'))

@section('content')
{{-- Header --}}
<div class="mb-4">
    <a href="{{ route('admission.applicants.show', $applicant) }}" class="text-muted small">
        <i class="bi bi-arrow-left"></i> Back to Applicant
    </a>
    <h2 class="fw-bold mb-0 mt-1">New Refund Request</h2>
    <p class="text-muted mb-0">{{ $applicant->user->name ?? 'Applicant' }} &mdash; {{ $applicant->application_number }}</p>
</div>

{{-- Existing Refund Alert --}}
@if($existingRefund)
<div class="alert alert-warning d-flex align-items-start gap-3 mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill fs-5 mt-1"></i>
    <div>
        <div class="fw-semibold">A refund request already exists for this applicant.</div>
        <div class="small mt-1">
            Status: <span class="{{ $existingRefund->status_badge }} ms-1">{{ ucfirst($existingRefund->status) }}</span>
            &nbsp;&mdash;&nbsp;
            <a href="{{ route('admission.refunds.show', $existingRefund) }}" class="alert-link">View Refund #{{ $existingRefund->id }}</a>
        </div>
    </div>
</div>
@else

{{-- Applicant Info Box --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom py-3">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-person-circle me-2"></i>Applicant Information</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="small text-muted">Name</div>
                <div class="fw-semibold">{{ $applicant->user->name ?? '—' }}</div>
            </div>
            <div class="col-md-3">
                <div class="small text-muted">Application Number</div>
                <div class="font-monospace fw-semibold">{{ $applicant->application_number }}</div>
            </div>
            <div class="col-md-3">
                <div class="small text-muted">Program</div>
                <div class="fw-semibold">{{ $applicant->program->name ?? '—' }}</div>
            </div>
            <div class="col-md-3">
                <div class="small text-muted">Batch</div>
                <div class="fw-semibold">{{ $applicant->batch->name ?? '—' }}</div>
            </div>
            <div class="col-md-3">
                <div class="small text-muted">Status</div>
                <div><span class="{{ $applicant->status_badge }}">{{ $applicant->status_label }}</span></div>
            </div>
            <div class="col-md-3">
                <div class="small text-muted">Total Paid (Verified)</div>
                <div class="fw-bold text-success fs-5">₹{{ number_format($applicant->total_paid, 2) }}</div>
            </div>
        </div>

        @if($payments->isNotEmpty())
        <div class="mt-4">
            <h6 class="text-muted fw-semibold mb-2">Verified Payments</h6>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Reference No.</th>
                            <th class="text-end">Amount (₹)</th>
                            <th>Method</th>
                            <th>Verified At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($payments as $pmt)
                        <tr>
                            <td class="font-monospace small">{{ $pmt->reference_number ?? '—' }}</td>
                            <td class="text-end fw-semibold">₹{{ number_format($pmt->amount_paid, 2) }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $pmt->payment_method ?? '—')) }}</td>
                            <td>{{ $pmt->verified_at ? $pmt->verified_at->format('d M Y, h:i A') : '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @else
        <div class="alert alert-info mt-3 mb-0 py-2 small">
            <i class="bi bi-info-circle me-1"></i>No verified payments found for this applicant. Refunds can only be processed against verified payments.
        </div>
        @endif
    </div>
</div>

{{-- Refund Request Form --}}
<form action="{{ route('admission.refunds.store', $applicant) }}" method="POST">
    @csrf

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom py-3">
            <h5 class="mb-0 fw-semibold"><i class="bi bi-arrow-counterclockwise me-2"></i>Refund Details</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">

                {{-- Payment Selection --}}
                <div class="col-md-6">
                    <label for="admission_payment_id" class="form-label fw-semibold">
                        Payment Reference <span class="text-muted fw-normal">(optional)</span>
                    </label>
                    <select name="admission_payment_id" id="admission_payment_id"
                            class="form-select @error('admission_payment_id') is-invalid @enderror"
                            onchange="prefillAmount(this)">
                        <option value="">— Select a payment (optional) —</option>
                        @foreach($payments as $pmt)
                        <option value="{{ $pmt->id }}"
                                data-amount="{{ $pmt->amount_paid }}"
                                {{ old('admission_payment_id') == $pmt->id ? 'selected' : '' }}>
                            {{ $pmt->reference_number ?? 'Ref #' . $pmt->id }}
                            — ₹{{ number_format($pmt->amount_paid, 2) }}
                            ({{ ucfirst(str_replace('_', ' ', $pmt->payment_method ?? '')) }})
                        </option>
                        @endforeach
                    </select>
                    @error('admission_payment_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Requested Amount --}}
                <div class="col-md-6">
                    <label for="requested_amount" class="form-label fw-semibold">Requested Amount (₹) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">₹</span>
                        <input type="number" name="requested_amount" id="requested_amount"
                               class="form-control @error('requested_amount') is-invalid @enderror"
                               value="{{ old('requested_amount', $payments->first()?->amount_paid ?? '') }}"
                               min="1" step="0.01" required>
                        @error('requested_amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- Reason --}}
                <div class="col-md-6">
                    <label for="reason" class="form-label fw-semibold">Reason for Refund <span class="text-danger">*</span></label>
                    <select name="reason" id="reason"
                            class="form-select @error('reason') is-invalid @enderror" required>
                        <option value="">— Select reason —</option>
                        <option value="withdrawal"     {{ old('reason') === 'withdrawal'     ? 'selected' : '' }}>Candidate Withdrawal</option>
                        <option value="rejection"      {{ old('reason') === 'rejection'      ? 'selected' : '' }}>Application Rejected</option>
                        <option value="excess_payment" {{ old('reason') === 'excess_payment' ? 'selected' : '' }}>Excess Payment</option>
                        <option value="other"          {{ old('reason') === 'other'          ? 'selected' : '' }}>Other</option>
                    </select>
                    @error('reason')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Reason Detail --}}
                <div class="col-12">
                    <label for="reason_detail" class="form-label fw-semibold">
                        Additional Details <span class="text-muted fw-normal">(optional)</span>
                    </label>
                    <textarea name="reason_detail" id="reason_detail" rows="3"
                              class="form-control @error('reason_detail') is-invalid @enderror"
                              maxlength="500" placeholder="Provide any additional context for this refund request…">{{ old('reason_detail') }}</textarea>
                    @error('reason_detail')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Bank Account Details --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom py-3">
            <h5 class="mb-0 fw-semibold"><i class="bi bi-bank me-2"></i>Bank Account Details for Refund</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">

                <div class="col-md-6">
                    <label for="bank_name" class="form-label fw-semibold">Bank Name <span class="text-danger">*</span></label>
                    <input type="text" name="bank_name" id="bank_name"
                           class="form-control @error('bank_name') is-invalid @enderror"
                           value="{{ old('bank_name') }}" maxlength="255"
                           placeholder="e.g. State Bank of India" required>
                    @error('bank_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label for="account_number" class="form-label fw-semibold">Account Number <span class="text-danger">*</span></label>
                    <input type="text" name="account_number" id="account_number"
                           class="form-control @error('account_number') is-invalid @enderror"
                           value="{{ old('account_number') }}" maxlength="100"
                           placeholder="Enter account number" required>
                    @error('account_number')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label for="ifsc_code" class="form-label fw-semibold">IFSC Code <span class="text-danger">*</span></label>
                    <input type="text" name="ifsc_code" id="ifsc_code"
                           class="form-control @error('ifsc_code') is-invalid @enderror"
                           value="{{ old('ifsc_code') }}" maxlength="11" minlength="11"
                           placeholder="e.g. SBIN0001234" style="text-transform:uppercase" required>
                    <div class="form-text">11-character IFSC code</div>
                    @error('ifsc_code')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label for="account_holder_name" class="form-label fw-semibold">Account Holder Name <span class="text-danger">*</span></label>
                    <input type="text" name="account_holder_name" id="account_holder_name"
                           class="form-control @error('account_holder_name') is-invalid @enderror"
                           value="{{ old('account_holder_name') }}" maxlength="255"
                           placeholder="Name as on bank account" required>
                    @error('account_holder_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('admission.applicants.show', $applicant) }}" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-send me-1"></i>Submit Refund Request
        </button>
    </div>
</form>

@endif {{-- end if not existingRefund --}}

@push('scripts')
<script>
function prefillAmount(select) {
    const option = select.options[select.selectedIndex];
    const amount = option.getAttribute('data-amount');
    if (amount) {
        document.getElementById('requested_amount').value = parseFloat(amount).toFixed(2);
    }
}

// Auto-uppercase IFSC
document.getElementById('ifsc_code')?.addEventListener('input', function () {
    this.value = this.value.toUpperCase();
});
</script>
@endpush

@endsection
