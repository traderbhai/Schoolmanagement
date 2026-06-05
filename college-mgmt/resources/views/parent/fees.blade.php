@extends('layouts.parent')
@section('title', 'Fee Status — '.$student->user->name)
@section('page-title', 'Fee Status')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('parent.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('parent.children') }}">My Children</a></li>
    <li class="breadcrumb-item active">Fees</li>
@endsection

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h5 class="fw-bold mb-0">{{ $student->user->name }} — Fee Status</h5>
        <div class="text-muted" style="font-size:.82rem">{{ optional($student->course)->name }}</div>
    </div>
    <a href="{{ route('parent.children') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="kpi-card kpi-blue">
            <div class="kpi-label">Total Fee</div>
            <div class="kpi-value">₹{{ number_format($feeDue) }}</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="kpi-card kpi-green">
            <div class="kpi-label">Paid</div>
            <div class="kpi-value">₹{{ number_format($feePaid) }}</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="kpi-card {{ $balance > 0 ? 'kpi-red' : 'kpi-green' }}">
            <div class="kpi-label">Balance Due</div>
            <div class="kpi-value">₹{{ number_format($balance) }}</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header fw-semibold"><i class="bi bi-receipt me-2 text-primary"></i>Payment History</div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Receipt No.</th>
                    <th>Fee Type</th>
                    <th>Amount</th>
                    <th>Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            @forelse($payments as $payment)
            <tr>
                <td>{{ $payment->receipt_number ?? '—' }}</td>
                <td>{{ optional($payment->feeStructure)->fee_type ?? '—' }}</td>
                <td>₹{{ number_format($payment->amount_paid) }}</td>
                <td>{{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('d M Y') : '—' }}</td>
                <td>
                    <span class="badge {{ $payment->status === 'paid' ? 'badge-paid' : ($payment->status === 'pending' ? 'badge-pending' : 'bg-secondary') }}">
                        {{ ucfirst($payment->status) }}
                    </span>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="text-center text-muted py-3">No payments recorded yet.</td>
            </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
