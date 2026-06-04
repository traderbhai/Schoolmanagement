@extends('layouts.student')
@section('title', 'My Fee Status')
@section('page-title', 'My Fee Status')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('student.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Fee Status</li>
@endsection

@section('content')

{{-- Stat Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card bg-primary">
            <div class="d-flex align-items-center gap-3">
                <div class="fs-2 opacity-75"><i class="bi bi-receipt"></i></div>
                <div>
                    <div class="small opacity-75 mb-1">Total Fee Due</div>
                    <div class="fs-4 fw-bold">₹{{ number_format($totalDue, 2) }}</div>
                    @if($currentYear)
                        <div class="small opacity-75">{{ $currentYear->name }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card bg-success">
            <div class="d-flex align-items-center gap-3">
                <div class="fs-2 opacity-75"><i class="bi bi-check-circle"></i></div>
                <div>
                    <div class="small opacity-75 mb-1">Amount Paid</div>
                    <div class="fs-4 fw-bold">₹{{ number_format($totalPaid, 2) }}</div>
                    <div class="small opacity-75">Confirmed payments</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card {{ $balance > 0 ? 'bg-danger' : 'bg-success' }}">
            <div class="d-flex align-items-center gap-3">
                <div class="fs-2 opacity-75"><i class="bi bi-{{ $balance > 0 ? 'exclamation-circle' : 'check2-all' }}"></i></div>
                <div>
                    <div class="small opacity-75 mb-1">Balance Due</div>
                    <div class="fs-4 fw-bold">₹{{ number_format($balance, 2) }}</div>
                    <div class="small opacity-75">{{ $balance > 0 ? 'Outstanding amount' : 'Fully paid' }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Fee Structures --}}
<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-list-ul text-primary"></i>
        Fee Structures
        @if($currentYear)
            <span class="badge bg-light text-dark ms-1">{{ $currentYear->name }}</span>
        @endif
    </div>
    <div class="card-body p-0">
        @if($feeStructures->isEmpty())
            <div class="alert alert-info m-3 mb-3">
                <i class="bi bi-info-circle me-2"></i>No fee structures defined for your course yet.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Fee Type</th>
                            <th>Description</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($feeStructures as $fs)
                            <tr>
                                <td>
                                    <span class="badge bg-primary bg-opacity-10 text-primary">
                                        {{ ucwords(str_replace('_', ' ', $fs->fee_type)) }}
                                    </span>
                                    @if($fs->semester_number)
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary ms-1">Sem {{ $fs->semester_number }}</span>
                                    @endif
                                </td>
                                <td class="text-muted">{{ $fs->description ?: '—' }}</td>
                                <td class="text-end fw-semibold">₹{{ number_format($fs->amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="2" class="fw-bold text-end">Total</td>
                            <td class="text-end fw-bold">₹{{ number_format($totalDue, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</div>

{{-- Payment History --}}
<div class="card">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-clock-history text-success"></i>
        Payment History
    </div>
    <div class="card-body p-0">
        @if($payments->isEmpty())
            <div class="text-center text-muted py-4">
                <i class="bi bi-inbox fs-3 d-block mb-2 opacity-50"></i>
                No payment records found.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Receipt #</th>
                            <th>Fee Type</th>
                            <th class="text-end">Amount Paid</th>
                            <th>Date</th>
                            <th>Method</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($payments as $payment)
                            <tr>
                                <td class="font-monospace small">{{ $payment->receipt_number ?: '—' }}</td>
                                <td class="text-muted small">
                                    {{ $payment->feeStructure ? ucwords(str_replace('_', ' ', $payment->feeStructure->fee_type)) : '—' }}
                                </td>
                                <td class="text-end fw-semibold">₹{{ number_format($payment->amount_paid, 2) }}</td>
                                <td class="small">{{ $payment->payment_date ? $payment->payment_date->format('d M Y') : '—' }}</td>
                                <td class="small text-muted">{{ $payment->payment_method ? ucfirst($payment->payment_method) : '—' }}</td>
                                <td>
                                    @if($payment->status === 'paid')
                                        <span class="badge bg-success">Paid</span>
                                    @elseif($payment->status === 'pending')
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    @else
                                        <span class="badge bg-secondary">{{ ucfirst($payment->status) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

@endsection
