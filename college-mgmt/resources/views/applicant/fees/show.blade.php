@extends('layouts.applicant')
@section('title', 'Payment Details')
@section('page-title', 'Payment Details')

@section('content')
<div class="container-fluid px-4 py-4">
    <div class="mb-3">
        <a href="{{ route('applicant.fees.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Fees
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">Payment Details</h5>
            <span class="{{ $payment->status_badge }} fs-6">
                {{ ucfirst($payment->status) }}
            </span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="text-muted small">Installment</label>
                    <div class="fw-semibold">{{ $payment->installment->name ?? 'N/A' }}</div>
                </div>
                <div class="col-md-6">
                    <label class="text-muted small">Amount Paid</label>
                    <div class="fw-bold fs-5">{{ $payment->formatted_amount }}</div>
                </div>
                <div class="col-md-6">
                    <label class="text-muted small">Payment Date</label>
                    <div class="fw-semibold">{{ $payment->payment_date->format('d M Y') }}</div>
                </div>
                <div class="col-md-6">
                    <label class="text-muted small">Payment Mode</label>
                    <div class="fw-semibold">{{ $payment->payment_mode_label }}</div>
                </div>
                @if($payment->transaction_reference)
                <div class="col-md-6">
                    <label class="text-muted small">Transaction Reference / UTR</label>
                    <div class="fw-semibold">{{ $payment->transaction_reference }}</div>
                </div>
                @endif
                @if($payment->bank_name)
                <div class="col-md-6">
                    <label class="text-muted small">Bank Name</label>
                    <div class="fw-semibold">{{ $payment->bank_name }}</div>
                </div>
                @endif
                <div class="col-md-6">
                    <label class="text-muted small">Submitted On</label>
                    <div class="fw-semibold">{{ $payment->created_at->format('d M Y, h:i A') }}</div>
                </div>
            </div>

            <hr class="my-4">

            <h6 class="fw-bold mb-3">Status Timeline</h6>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex align-items-start gap-3 px-0">
                    <div class="badge bg-primary rounded-circle p-2 mt-1"><i class="bi bi-upload"></i></div>
                    <div>
                        <div class="fw-semibold">Payment Submitted</div>
                        <small class="text-muted">{{ $payment->created_at->format('d M Y, h:i A') }}</small>
                    </div>
                </li>
                @if($payment->status === 'verified')
                <li class="list-group-item d-flex align-items-start gap-3 px-0">
                    <div class="badge bg-success rounded-circle p-2 mt-1"><i class="bi bi-check-lg"></i></div>
                    <div>
                        <div class="fw-semibold">Payment Verified</div>
                        <small class="text-muted">{{ $payment->verified_at?->format('d M Y, h:i A') }}</small>
                        @if($payment->verifiedBy)
                            <div class="small text-muted">by {{ $payment->verifiedBy->name }}</div>
                        @endif
                        @if($payment->verification_notes)
                            <div class="small text-success mt-1">{{ $payment->verification_notes }}</div>
                        @endif
                    </div>
                </li>
                @elseif($payment->status === 'rejected')
                <li class="list-group-item d-flex align-items-start gap-3 px-0">
                    <div class="badge bg-danger rounded-circle p-2 mt-1"><i class="bi bi-x-lg"></i></div>
                    <div>
                        <div class="fw-semibold text-danger">Payment Rejected</div>
                        <small class="text-muted">{{ $payment->verified_at?->format('d M Y, h:i A') }}</small>
                        @if($payment->verification_notes)
                            <div class="small text-danger mt-1"><strong>Reason:</strong> {{ $payment->verification_notes }}</div>
                        @endif
                    </div>
                </li>
                @else
                <li class="list-group-item d-flex align-items-start gap-3 px-0 text-muted">
                    <div class="badge bg-warning text-dark rounded-circle p-2 mt-1"><i class="bi bi-clock"></i></div>
                    <div>
                        <div class="fw-semibold">Pending Verification</div>
                        <small>Our accounts team will verify your payment shortly.</small>
                    </div>
                </li>
                @endif
            </ul>
        </div>
    </div>
</div>
@endsection
