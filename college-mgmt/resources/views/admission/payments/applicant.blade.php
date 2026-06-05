@extends('layouts.admin')
@section('title', 'Payment History — ' . ($applicant->user->name ?? 'Applicant'))

@section('content')
<div class="mb-3">
    <a href="{{ route('admission.applicants.show', $applicant) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Applicant
    </a>
</div>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-0 fw-bold">{{ $applicant->user->name ?? 'N/A' }}</h3>
        <small class="text-muted">{{ $applicant->application_number }} — {{ $applicant->program->name ?? '' }}</small>
    </div>
    <div>
        <span class="{{ $applicant->status_badge }}">{{ $applicant->status_label }}</span>
    </div>
</div>

{{-- Summary --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-3">
                <div class="fs-3 fw-bold text-dark">₹{{ number_format($installments->sum('amount'), 0) }}</div>
                <div class="small text-muted">Total Fee</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-3">
                <div class="fs-3 fw-bold text-success">₹{{ number_format($applicant->total_paid, 0) }}</div>
                <div class="small text-muted">Paid & Verified</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-3">
                <div class="fs-3 fw-bold text-warning">₹{{ number_format($applicant->outstanding_amount, 0) }}</div>
                <div class="small text-muted">Outstanding</div>
            </div>
        </div>
    </div>
</div>

{{-- Installments --}}
@forelse($installments as $installment)
@php $payment = $payments[$installment->id] ?? null; @endphp
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h6 class="fw-bold mb-1">{{ $installment->name }}</h6>
                <small class="text-muted">Due: {{ $installment->due_date?->format('d M Y') ?? 'N/A' }}</small>
            </div>
            <div class="text-end">
                <div class="fw-bold fs-5">₹{{ number_format($installment->amount, 0) }}</div>
                @if($payment)
                    <span class="{{ $payment->status_badge }}">{{ ucfirst($payment->status) }}</span>
                @else
                    <span class="badge bg-secondary">Not Paid</span>
                @endif
            </div>
        </div>

        @if($payment)
        <hr class="my-2">
        <div class="row g-2 small">
            <div class="col-md-3"><span class="text-muted">Amount Paid:</span> <strong>₹{{ number_format($payment->amount_paid, 0) }}</strong></div>
            <div class="col-md-3"><span class="text-muted">Mode:</span> <strong>{{ $payment->payment_mode_label }}</strong></div>
            <div class="col-md-3"><span class="text-muted">Date:</span> <strong>{{ $payment->payment_date->format('d M Y') }}</strong></div>
            <div class="col-md-3">
                @if($payment->payment_proof_path)
                    <a href="{{ route('admission.payments.proof', $payment) }}" class="btn btn-sm btn-outline-secondary" target="_blank">
                        <i class="bi bi-download me-1"></i>Proof
                    </a>
                @endif
            </div>
            @if($payment->transaction_reference)
            <div class="col-12"><span class="text-muted">Transaction Ref:</span> <code>{{ $payment->transaction_reference }}</code></div>
            @endif
            @if($payment->verification_notes)
            <div class="col-12"><span class="text-muted">Notes:</span> {{ $payment->verification_notes }}</div>
            @endif
            @if($payment->verifiedBy)
            <div class="col-12"><span class="text-muted">Verified by:</span> {{ $payment->verifiedBy->name }} on {{ $payment->verified_at?->format('d M Y h:i A') }}</div>
            @endif
        </div>

        @if($payment->status === 'pending')
        <div class="mt-2 d-flex gap-2">
            <form method="POST" action="{{ route('admission.payments.verify', $payment) }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Verify this payment?')">
                    <i class="bi bi-check-lg"></i> Verify
                </button>
            </form>
            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal"
                data-bs-target="#rejectModal-{{ $payment->id }}">
                <i class="bi bi-x-lg"></i> Reject
            </button>
            @include('admission.payments._reject-modal', ['payment' => $payment])
        </div>
        @endif
        @endif
    </div>
</div>
@empty
<div class="alert alert-info">No installments configured for this program.</div>
@endforelse
@endsection
