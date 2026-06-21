@extends('layouts.applicant')
@section('title', 'Fees & Payments')
@section('page-title', 'Fees & Payments')

@section('content')
<div class="container-fluid px-4 py-4">

    {{-- Header --}}
    <div class="row g-3 mb-4">
        <div class="col-12">
            <h4 class="fw-bold mb-0">{{ $applicant->program->name ?? 'Program' }} - Fee Summary</h4>
            <small class="text-muted">Application: {{ $applicant->application_number }}</small>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-3">
                    <div class="fs-4 fw-bold text-dark">Rs. {{ number_format($totalFee, 0) }}</div>
                    <div class="small text-muted">Total Fee</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-3">
                    <div class="fs-4 fw-bold text-success">Rs. {{ number_format($totalPaid, 0) }}</div>
                    <div class="small text-muted">Paid & Verified</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-3">
                    <div class="fs-4 fw-bold text-warning">Rs. {{ number_format($outstanding, 0) }}</div>
                    <div class="small text-muted">Outstanding</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Progress bar --}}
    @if($totalFee > 0)
    <div class="mb-4">
        <div class="d-flex justify-content-between small text-muted mb-1">
            <span>Payment Progress</span>
            <span>{{ number_format(($totalPaid / $totalFee) * 100, 1) }}%</span>
        </div>
        <div class="progress" style="height: 10px;">
            <div class="progress-bar bg-success" style="width: {{ min(100, ($totalPaid / $totalFee) * 100) }}%"></div>
        </div>
    </div>
    @endif

    {{-- Installment Cards --}}
    @forelse($installments as $installment)
    @php
        $payment = $payments[$installment->id] ?? null;
        $isOverdue = !$payment && $installment->due_date && $installment->due_date->isPast();
        $canPay = in_array($applicant->status, ['shortlisted', 'selected']);
    @endphp
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <h6 class="fw-bold mb-0">{{ $installment->name }}</h6>
                    <small class="text-muted">
                        Due: {{ $installment->due_date ? $installment->due_date->format('d M Y') : 'Due date not published' }}
                        @if($isOverdue) <span class="text-danger fw-bold ms-1">(Overdue)</span> @endif
                    </small>
                </div>
                <div class="text-end">
                    <div class="fw-bold fs-5">Rs. {{ number_format($installment->amount, 0) }}</div>
                    @if($payment)
                        <span class="{{ $payment->status_badge }}">
                            @if($payment->status === 'verified') Paid & Verified
                            @elseif($payment->status === 'pending') Pending Verification
                            @else Rejected @endif
                        </span>
                    @elseif($isOverdue)
                        <span class="badge bg-danger">Overdue</span>
                    @else
                        <span class="badge bg-secondary">Not Paid</span>
                    @endif
                </div>
            </div>

            @if($installment->description)
                <p class="text-muted small mb-2">{{ $installment->description }}</p>
            @endif

            {{-- Verified payment --}}
            @if($payment && $payment->status === 'verified')
                <div class="alert alert-success py-2 mb-0">
                    <i class="bi bi-check-circle me-1"></i>
                    Paid Rs. {{ number_format($payment->amount_paid, 0) }} via {{ $payment->payment_mode_label }}
                    on {{ $payment->payment_date?->format('d M Y') ?? '-' }}.
                    Verified on {{ $payment->verified_at?->format('d M Y') }}.
                    <a href="{{ route('applicant.fees.show', $payment) }}" class="ms-2 text-success fw-bold">View Details</a>
                </div>

            {{-- Pending payment --}}
            @elseif($payment && $payment->status === 'pending')
                <div class="alert alert-warning py-2 mb-0">
                    <i class="bi bi-clock me-1"></i>
                    Payment submitted on {{ $payment->created_at->format('d M Y') }} - awaiting verification.
                    <a href="{{ route('applicant.fees.show', $payment) }}" class="ms-2 text-dark fw-bold">View Details</a>
                </div>

            {{-- Rejected - allow resubmission --}}
            @elseif($payment && $payment->status === 'rejected')
                <div class="alert alert-danger py-2 mb-2">
                    <i class="bi bi-x-circle me-1"></i>
                    Previous submission rejected.
                    @if($payment->verification_notes)
                        <strong>Reason:</strong> {{ $payment->verification_notes }}
                    @endif
                </div>
                @if($canPay)
                <button class="btn btn-sm btn-outline-primary" type="button"
                    data-bs-toggle="collapse" data-bs-target="#pay-form-{{ $installment->id }}">
                    <i class="bi bi-arrow-repeat me-1"></i>Re-submit Payment
                </button>
                @endif

            {{-- Not paid --}}
            @elseif(!$payment && $canPay)
                <button class="btn btn-sm btn-primary" type="button"
                    data-bs-toggle="collapse" data-bs-target="#pay-form-{{ $installment->id }}">
                    <i class="bi bi-cash me-1"></i>Submit Payment
                </button>

            @elseif(!$payment && !$canPay)
                <div class="text-muted small">
                    <i class="bi bi-info-circle me-1"></i>{{ $paymentUnavailableReason }}
                </div>
            @endif

            {{-- Payment form (collapsed) --}}
            @if($canPay && (!$payment || $payment->status === 'rejected'))
            <div class="collapse mt-3" id="pay-form-{{ $installment->id }}">
                <div class="card card-body bg-light border">
                    <form method="POST" action="{{ route('applicant.fees.store', $installment) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Amount Paid <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">Rs. </span>
                                    <input type="number" name="amount_paid" class="form-control"
                                        value="{{ old('amount_paid', $installment->amount) }}"
                                        min="1" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Payment Date <span class="text-danger">*</span></label>
                                <input type="date" name="payment_date" class="form-control"
                                    value="{{ old('payment_date', date('Y-m-d')) }}"
                                    max="{{ date('Y-m-d') }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Payment Mode <span class="text-danger">*</span></label>
                                <select name="payment_mode" class="form-select" required id="mode-{{ $installment->id }}">
                                    <option value="">Select mode...</option>
                                    @foreach(['neft' => 'NEFT', 'rtgs' => 'RTGS', 'imps' => 'IMPS', 'upi' => 'UPI', 'dd' => 'Demand Draft', 'cash' => 'Cash', 'cheque' => 'Cheque'] as $val => $label)
                                        <option value="{{ $val }}" {{ old('payment_mode') == $val ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Transaction Reference / UTR</label>
                                <input type="text" name="transaction_reference" class="form-control"
                                    value="{{ old('transaction_reference') }}"
                                    placeholder="UTR / Transaction ID / DD Number">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Bank Name</label>
                                <input type="text" name="bank_name" class="form-control"
                                    value="{{ old('bank_name') }}"
                                    placeholder="e.g. SBI, HDFC, ICICI">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Payment Proof</label>
                                <input type="file" name="payment_proof" class="form-control"
                                    accept=".jpg,.jpeg,.png,.pdf">
                                <div class="form-text">Upload screenshot or receipt (JPG, PNG, PDF - max 5MB)</div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-send me-1"></i>Submit Payment
                                </button>
                                <button type="button" class="btn btn-outline-secondary ms-2"
                                    data-bs-toggle="collapse" data-bs-target="#pay-form-{{ $installment->id }}">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            @endif
        </div>
    </div>
    @empty
    <div class="alert alert-info">
        <div class="fw-semibold mb-1">No admission fee installments are available yet</div>
        <div class="small">
            Admission or Accounts staff will publish program fee milestones after your admission stage, batch, and fee rules are ready. Until then, track your application status and registration-fee proof separately; admission-fee proof submission opens only after you are shortlisted or selected.
        </div>
        <div class="mt-3 d-flex gap-2 flex-wrap">
            <a href="{{ route('applicant.status') }}" class="btn btn-sm btn-outline-primary">Track status</a>
            <a href="{{ route('applicant.registration-fee.show') }}" class="btn btn-sm btn-outline-secondary">Registration fee</a>
            <a href="{{ route('applicant.checklist') }}" class="btn btn-sm btn-outline-secondary">Checklist</a>
        </div>
    </div>
    @endforelse

</div>
@endsection
