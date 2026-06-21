@extends('layouts.admin')
@section('title', 'Payment Verification Queue')

@section('content')
@php
    $filterSummary = collect([
        request('program_id') ? 'Program filtered' : null,
        request('installment_id') ? 'Installment filtered' : null,
        request('payment_mode') ? 'Mode: ' . strtoupper(request('payment_mode')) : null,
        request('date_from') ? 'From: ' . request('date_from') : null,
        request('date_to') ? 'To: ' . request('date_to') : null,
    ])->filter()->implode(' | ') ?: 'All visible pending payments';
@endphp
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0 fw-bold">Payment Verification Queue</h2>
        <small class="text-muted">Review and verify pending payment submissions</small>
        <div class="small text-muted">Filter: {{ $filterSummary }}</div>
    </div>
    <a href="{{ route('admission.payments.queue.export', request()->query()) }}" class="btn btn-sm btn-outline-success">
        <i class="bi bi-download me-1"></i>Export Current View
    </a>
</div>

{{-- Stats --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-3">
                <div class="fs-2 fw-bold text-warning">{{ $stats['total_pending'] }}</div>
                <div class="small text-muted">Pending Verification</div>
                @if(($stats['all_pending'] ?? $stats['total_pending']) !== $stats['total_pending'])
                    <div class="small text-muted">of {{ $stats['all_pending'] }} visible total</div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-3">
                <div class="fs-2 fw-bold text-success">{{ $stats['verified_today'] }}</div>
                <div class="small text-muted">Verified Today</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-3">
                <div class="fs-2 fw-bold text-danger">Rs. {{ number_format($stats['amount_pending'], 0) }}</div>
                <div class="small text-muted">Amount Pending</div>
            </div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Program</label>
                <select name="program_id" class="form-select form-select-sm">
                    <option value="">All Programs</option>
                    @foreach($programs as $prog)
                        <option value="{{ $prog->id }}" {{ request('program_id') == $prog->id ? 'selected' : '' }}>
                            {{ $prog->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Installment</label>
                <select name="installment_id" class="form-select form-select-sm">
                    <option value="">All Installments</option>
                    @foreach($installments as $inst)
                        <option value="{{ $inst->id }}" {{ request('installment_id') == $inst->id ? 'selected' : '' }}>
                            {{ $inst->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Payment Mode</label>
                <select name="payment_mode" class="form-select form-select-sm">
                    <option value="">All Modes</option>
                    @foreach(['neft' => 'NEFT', 'rtgs' => 'RTGS', 'imps' => 'IMPS', 'upi' => 'UPI', 'dd' => 'DD', 'cash' => 'Cash', 'cheque' => 'Cheque'] as $val => $label)
                        <option value="{{ $val }}" {{ request('payment_mode') == $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Date From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Date To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
                <a href="{{ route('admission.payments.queue') }}" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
            </div>
        </form>
    </div>
</div>

{{-- Payments Table --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="fw-semibold">Pending Payments ({{ $payments->total() }})</span>
        <span class="small text-muted">Showing {{ $payments->firstItem() ?? 0 }}-{{ $payments->lastItem() ?? 0 }} of {{ $payments->total() }}</span>
    </div>
    <div class="card-body p-0">
        @if($payments->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-check2-all fs-1"></i>
                <div class="fw-semibold text-dark mt-2">No pending payments in this queue</div>
                <div class="small">
                    Applicant payment submissions appear here only when they are pending verification and inside your admission scope. Clear filters or open the applicant list if your team expects a payment to review.
                </div>
                <div class="mt-3 d-flex flex-wrap justify-content-center gap-2">
                    <a href="{{ route('admission.payments.queue') }}" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
                    <a href="{{ route('admission.applicants.index') }}" class="btn btn-sm btn-outline-primary">Open Applicants</a>
                </div>
            </div>
        @else
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Applicant</th>
                        <th>Installment</th>
                        <th>Amount</th>
                        <th>Mode</th>
                        <th>Transaction Ref</th>
                        <th>Submitted</th>
                        <th>Proof</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payments as $payment)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $payment->applicant->user->name ?? 'Applicant name missing' }}</div>
                            <small class="text-muted">{{ $payment->applicant->application_number ?? 'Application number missing' }}</small>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $payment->installment->name ?? 'Installment not linked' }}</div>
                            <small class="text-muted">{{ $payment->applicant->program->name ?? 'Program not assigned' }}</small>
                        </td>
                        <td class="fw-bold">Rs. {{ number_format($payment->amount_paid, 0) }}</td>
                        <td><span class="badge bg-info text-dark">{{ $payment->payment_mode_label }}</span></td>
                        <td>
                            @if($payment->transaction_reference)
                                <code class="small">{{ $payment->transaction_reference }}</code>
                            @else
                                <span class="text-muted">Reference not captured</span>
                            @endif
                        </td>
                        <td>
                            <div>{{ $payment->created_at->diffForHumans() }}</div>
                            <small class="text-muted">{{ $payment->created_at->format('d M Y') }}</small>
                        </td>
                        <td>
                            @if($payment->payment_proof_path)
                                <a href="{{ route('admission.payments.proof', $payment) }}"
                                    class="btn btn-sm btn-outline-secondary" target="_blank">
                                    <i class="bi bi-download me-1"></i>Proof
                                </a>
                            @else
                                <span class="text-muted small">No file</span>
                            @endif
                        </td>
                        <td>
                            <form method="POST" action="{{ route('admission.payments.verify', $payment) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success"
                                    onclick="return confirm('Verify this payment?')">
                                    <i class="bi bi-check-lg"></i> Verify
                                </button>
                            </form>
                            <button type="button" class="btn btn-sm btn-outline-danger ms-1"
                                data-bs-toggle="modal" data-bs-target="#rejectModal-{{ $payment->id }}">
                                <i class="bi bi-x-lg"></i> Reject
                            </button>

                            @include('admission.payments._reject-modal', ['payment' => $payment])
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-3">
            {{ $payments->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
