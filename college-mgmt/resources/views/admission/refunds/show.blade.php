@extends('layouts.admin')

@section('title', 'Refund Request #' . $refund->id)

@section('content')
{{-- Header --}}
<div class="d-flex flex-wrap justify-content-between align-items-start mb-4 gap-3">
    <div>
        <a href="{{ route('admission.refunds.index') }}" class="text-muted small">
            <i class="bi bi-arrow-left"></i> All Refund Requests
        </a>
        <h2 class="fw-bold mb-0 mt-1">Refund Request #{{ $refund->id }}</h2>
        <div class="text-muted small">
            {{ $refund->applicant->user->name ?? '—' }} &middot;
            <span class="font-monospace">{{ $refund->applicant->application_number ?? '' }}</span>
        </div>
    </div>
    <span class="{{ $refund->status_badge }} fs-5 px-3 py-2">{{ ucfirst($refund->status) }}</span>
</div>

{{-- Flash Messages --}}
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- Main Content: Two Columns --}}
<div class="row g-4 mb-4">

    {{-- Left: Applicant Info --}}
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-person-circle me-2"></i>Applicant</h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5 text-muted fw-normal">Name</dt>
                    <dd class="col-7 fw-semibold">{{ $refund->applicant->user->name ?? '—' }}</dd>

                    <dt class="col-5 text-muted fw-normal">Email</dt>
                    <dd class="col-7">{{ $refund->applicant->user->email ?? '—' }}</dd>

                    <dt class="col-5 text-muted fw-normal">Application No.</dt>
                    <dd class="col-7 font-monospace">{{ $refund->applicant->application_number ?? '—' }}</dd>

                    <dt class="col-5 text-muted fw-normal">Program</dt>
                    <dd class="col-7">{{ $refund->applicant->program->name ?? '—' }}</dd>

                    <dt class="col-5 text-muted fw-normal">Applicant Status</dt>
                    <dd class="col-7">
                        <span class="{{ $refund->applicant->status_badge }}">
                            {{ $refund->applicant->status_label }}
                        </span>
                    </dd>

                    <dt class="col-5 text-muted fw-normal">Refund Reason</dt>
                    <dd class="col-7">{{ $refund->reason_label }}</dd>

                    @if($refund->reason_detail)
                    <dt class="col-5 text-muted fw-normal">Details</dt>
                    <dd class="col-7">{{ $refund->reason_detail }}</dd>
                    @endif

                    <dt class="col-5 text-muted fw-normal">Requested</dt>
                    <dd class="col-7 fw-bold text-dark">₹{{ number_format($refund->requested_amount, 2) }}</dd>

                    @if($refund->approved_amount !== null)
                    <dt class="col-5 text-muted fw-normal">Approved</dt>
                    <dd class="col-7 fw-bold text-success">₹{{ number_format($refund->approved_amount, 2) }}</dd>
                    @endif

                    <dt class="col-5 text-muted fw-normal">Submitted On</dt>
                    <dd class="col-7">{{ $refund->created_at->format('d M Y, h:i A') }}</dd>
                </dl>
            </div>
            <div class="card-footer bg-white border-top">
                <a href="{{ route('admission.applicants.show', $refund->applicant) }}"
                   class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-box-arrow-up-right me-1"></i>View Applicant Profile
                </a>
            </div>
        </div>
    </div>

    {{-- Right: Payment Info --}}
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-receipt me-2"></i>Payment Reference</h5>
            </div>
            <div class="card-body">
                @if($refund->payment)
                <dl class="row mb-0">
                    <dt class="col-5 text-muted fw-normal">Reference No.</dt>
                    <dd class="col-7 font-monospace">{{ $refund->payment->reference_number ?? '—' }}</dd>

                    <dt class="col-5 text-muted fw-normal">Amount Paid</dt>
                    <dd class="col-7 fw-bold">₹{{ number_format($refund->payment->amount_paid, 2) }}</dd>

                    <dt class="col-5 text-muted fw-normal">Method</dt>
                    <dd class="col-7">{{ ucfirst(str_replace('_', ' ', $refund->payment->payment_method ?? '—')) }}</dd>

                    <dt class="col-5 text-muted fw-normal">Payment Status</dt>
                    <dd class="col-7">{{ ucfirst($refund->payment->status ?? '—') }}</dd>

                    <dt class="col-5 text-muted fw-normal">Verified At</dt>
                    <dd class="col-7">
                        {{ $refund->payment->verified_at
                            ? $refund->payment->verified_at->format('d M Y, h:i A')
                            : '—' }}
                    </dd>

                    @if($refund->payment->verification_notes)
                    <dt class="col-5 text-muted fw-normal">Notes</dt>
                    <dd class="col-7">{{ $refund->payment->verification_notes }}</dd>
                    @endif
                </dl>
                @else
                <p class="text-muted mb-0 py-3 text-center">
                    <i class="bi bi-file-earmark-x fs-2 d-block mb-2"></i>
                    No specific payment linked to this refund request.
                </p>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Action / Timeline Section --}}
<div class="row g-4 mb-4">
    <div class="col-md-8">

        {{-- PENDING: Approve / Reject forms --}}
        @if($refund->status === 'pending')
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-check2-circle me-2 text-success"></i>Approve Refund</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admission.refunds.approve', $refund) }}" method="POST">
                    @csrf
                    @method('PATCH')
                    <div class="mb-3">
                        <label for="approved_amount" class="form-label fw-semibold">
                            Approved Amount (₹) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group" style="max-width:280px">
                            <span class="input-group-text">₹</span>
                            <input type="number" name="approved_amount" id="approved_amount"
                                   class="form-control @error('approved_amount') is-invalid @enderror"
                                   value="{{ old('approved_amount', $refund->requested_amount) }}"
                                   min="0" max="{{ $refund->requested_amount }}" step="0.01" required>
                            @error('approved_amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-text">Max: ₹{{ number_format($refund->requested_amount, 2) }} (requested amount)</div>
                    </div>
                    <button type="submit" class="btn btn-success"
                            onclick="return confirm('Approve this refund for the entered amount?')">
                        <i class="bi bi-check-circle me-1"></i>Approve Refund
                    </button>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm border-danger-subtle">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-x-circle me-2 text-danger"></i>Reject Refund</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admission.refunds.reject', $refund) }}" method="POST">
                    @csrf
                    @method('PATCH')
                    <div class="mb-3">
                        <label for="rejection_reason" class="form-label fw-semibold">
                            Rejection Reason <span class="text-danger">*</span>
                        </label>
                        <textarea name="rejection_reason" id="rejection_reason" rows="3"
                                  class="form-control @error('rejection_reason') is-invalid @enderror"
                                  maxlength="500" required
                                  placeholder="Explain why this refund request is being rejected…">{{ old('rejection_reason') }}</textarea>
                        @error('rejection_reason')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-danger"
                            onclick="return confirm('Are you sure you want to reject this refund request?')">
                        <i class="bi bi-x-circle me-1"></i>Reject Request
                    </button>
                </form>
            </div>
        </div>

        {{-- APPROVED: Mark as Processed --}}
        @elseif($refund->status === 'approved')
        <div class="alert alert-success d-flex align-items-center gap-3 mb-3">
            <i class="bi bi-check-circle-fill fs-4"></i>
            <div>
                <div class="fw-semibold">Refund Approved</div>
                <div>Approved Amount: <strong>₹{{ number_format($refund->approved_amount, 2) }}</strong></div>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-send-check me-2 text-primary"></i>Mark as Processed</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">Once the bank transfer is complete, enter the UTR number below to mark this refund as processed.</p>
                <form action="{{ route('admission.refunds.process', $refund) }}" method="POST">
                    @csrf
                    @method('PATCH')
                    <div class="mb-3">
                        <label for="utr_number" class="form-label fw-semibold">
                            UTR / Transaction Reference Number <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="utr_number" id="utr_number"
                               class="form-control @error('utr_number') is-invalid @enderror"
                               value="{{ old('utr_number') }}" maxlength="100"
                               placeholder="Enter UTR number" style="max-width:320px" required>
                        @error('utr_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary"
                            onclick="return confirm('Mark this refund as processed with UTR: ' + document.getElementById('utr_number').value + '?')">
                        <i class="bi bi-send-check me-1"></i>Mark as Processed
                    </button>
                </form>
            </div>
        </div>

        {{-- REJECTED --}}
        @elseif($refund->status === 'rejected')
        <div class="alert alert-danger" role="alert">
            <div class="fw-semibold mb-1"><i class="bi bi-x-octagon-fill me-2"></i>Refund Request Rejected</div>
            <div class="small">{{ $refund->rejection_reason }}</div>
        </div>

        {{-- PROCESSED --}}
        @elseif($refund->status === 'processed')
        <div class="alert alert-success" role="alert">
            <div class="fw-semibold mb-2"><i class="bi bi-check-circle-fill me-2"></i>Refund Successfully Processed</div>
            <dl class="row mb-0 small">
                <dt class="col-4">UTR Number</dt>
                <dd class="col-8 font-monospace fw-bold">{{ $refund->utr_number }}</dd>
                <dt class="col-4">Processed At</dt>
                <dd class="col-8">{{ $refund->processed_at?->format('d M Y, h:i A') ?? '—' }}</dd>
                <dt class="col-4">Approved Amount</dt>
                <dd class="col-8 fw-bold">₹{{ number_format($refund->approved_amount, 2) }}</dd>
            </dl>
        </div>
        @endif

    </div>

    {{-- Right: Bank Details + Audit Trail --}}
    <div class="col-md-4">

        {{-- Bank Details --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-bank me-2"></i>Bank Details</h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-6 text-muted fw-normal">Bank Name</dt>
                    <dd class="col-6 fw-semibold">{{ $refund->bank_name ?? '—' }}</dd>

                    <dt class="col-6 text-muted fw-normal">Account No.</dt>
                    <dd class="col-6 font-monospace">
                        @if($refund->account_number)
                            @php
                                $acc = $refund->account_number;
                                $masked = str_repeat('*', max(0, strlen($acc) - 4)) . substr($acc, -4);
                            @endphp
                            {{ $masked }}
                        @else
                            —
                        @endif
                    </dd>

                    <dt class="col-6 text-muted fw-normal">IFSC Code</dt>
                    <dd class="col-6 font-monospace">{{ $refund->ifsc_code ?? '—' }}</dd>

                    <dt class="col-6 text-muted fw-normal">Account Holder</dt>
                    <dd class="col-6">{{ $refund->account_holder_name ?? '—' }}</dd>
                </dl>
            </div>
        </div>

        {{-- Audit Trail --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-2"></i>Audit Trail</h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted fw-normal">Submitted</dt>
                    <dd class="col-7">{{ $refund->created_at->format('d M Y') }}<br>
                        <span class="text-muted">{{ $refund->created_at->format('h:i A') }}</span>
                    </dd>

                    @if($refund->reviewedBy)
                    <dt class="col-5 text-muted fw-normal">Reviewed By</dt>
                    <dd class="col-7 fw-semibold">{{ $refund->reviewedBy->name }}</dd>
                    @endif

                    @if($refund->reviewed_at)
                    <dt class="col-5 text-muted fw-normal">Reviewed At</dt>
                    <dd class="col-7">{{ $refund->reviewed_at->format('d M Y') }}<br>
                        <span class="text-muted">{{ $refund->reviewed_at->format('h:i A') }}</span>
                    </dd>
                    @endif

                    @if($refund->processed_at)
                    <dt class="col-5 text-muted fw-normal">Processed At</dt>
                    <dd class="col-7">{{ $refund->processed_at->format('d M Y') }}<br>
                        <span class="text-muted">{{ $refund->processed_at->format('h:i A') }}</span>
                    </dd>
                    @endif

                    @if($refund->utr_number)
                    <dt class="col-5 text-muted fw-normal">UTR No.</dt>
                    <dd class="col-7 font-monospace">{{ $refund->utr_number }}</dd>
                    @endif
                </dl>
            </div>
        </div>

    </div>
</div>
@endsection
