@extends('layouts.student')
@section('title', 'My Fee Status')
@section('page-title', 'My Fee Status')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('student.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Fee Status</li>
@endsection

@section('content')

{{-- KPI / Stat Cards --}}
<div class="row g-3 mb-4">
    {{-- Total Due --}}
    <div class="col-md-4">
        <div class="card border-0 h-100" style="background:linear-gradient(135deg,#d97706,#b45309)">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="opacity-75 small fw-semibold mb-1">Total Fee Due</div>
                        <div class="fw-bold lh-1 mt-1" style="font-size:2rem">₹{{ number_format($totalDue) }}</div>
                        <div class="opacity-75 mt-2" style="font-size:.78rem">
                            @if($currentYear) {{ $currentYear->name }} @else Current year @endif
                        </div>
                    </div>
                    <div class="opacity-40"><i class="bi bi-receipt" style="font-size:2.5rem"></i></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Amount Paid --}}
    <div class="col-md-4">
        <div class="card border-0 h-100" style="background:linear-gradient(135deg,#059669,#047857)">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="opacity-75 small fw-semibold mb-1">Amount Paid</div>
                        <div class="fw-bold lh-1 mt-1" style="font-size:2rem">₹{{ number_format($totalPaid) }}</div>
                        <div class="opacity-75 mt-2" style="font-size:.78rem">Confirmed payments</div>
                    </div>
                    <div class="opacity-40"><i class="bi bi-check-circle-fill" style="font-size:2.5rem"></i></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Balance Due --}}
    <div class="col-md-4">
        @if($balance > 0)
        <div class="card border-0 h-100" style="background:linear-gradient(135deg,#dc2626,#b91c1c)">
        @else
        <div class="card border-0 h-100" style="background:linear-gradient(135deg,#059669,#047857)">
        @endif
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="opacity-75 small fw-semibold mb-1">Balance Due</div>
                        <div class="fw-bold lh-1 mt-1" style="font-size:2rem">₹{{ number_format($balance) }}</div>
                        <div class="opacity-75 mt-2" style="font-size:.78rem">
                            {{ $balance > 0 ? 'Outstanding amount' : 'Fully paid — No dues' }}
                        </div>
                    </div>
                    <div class="opacity-40">
                        <i class="bi bi-{{ $balance > 0 ? 'exclamation-circle-fill' : 'check2-all' }}" style="font-size:2.5rem"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Fee Structures Table --}}
<div class="card mb-4" style="box-shadow:var(--shadow-sm)">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-list-ul text-primary"></i>
        <span class="fw-semibold">Fee Structures</span>
        @if($currentYear)
            <span class="badge bg-light text-dark border ms-1">{{ $currentYear->name }}</span>
        @endif
    </div>
    <div class="card-body p-0">
        @if($feeStructures->isEmpty())
        <div class="empty-state py-4">
            <div class="empty-icon"><i class="bi bi-inbox" style="font-size:2rem;color:var(--clr-text-muted)"></i></div>
            <p class="text-muted small mb-0 mt-2">No fee structures defined for your course yet.</p>
        </div>
        @else
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
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
                            <span class="badge bg-primary bg-opacity-10 text-primary fw-semibold">
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
                        <td colspan="2" class="fw-bold text-end">Total Fee Due</td>
                        <td class="text-end fw-bold text-primary" style="font-size:1.05rem">₹{{ number_format($totalDue, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @endif
    </div>
</div>

{{-- Payment History Table --}}
<div class="card" style="box-shadow:var(--shadow-sm)">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-clock-history text-success"></i>
        <span class="fw-semibold">Payment History</span>
        @if($payments->isNotEmpty())
        <span class="badge bg-success ms-auto">{{ $payments->count() }} payment(s)</span>
        @endif
    </div>
    <div class="card-body p-0">
        @if($payments->isEmpty())
        <div class="empty-state py-4">
            <div class="empty-icon"><i class="bi bi-inbox" style="font-size:2rem;color:var(--clr-text-muted)"></i></div>
            <p class="text-muted small mb-0 mt-2">No payment records found.</p>
        </div>
        @else
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Receipt #</th>
                        <th>Fee Type</th>
                        <th class="text-end">Amount Paid</th>
                        <th>Date</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payments as $payment)
                    <tr>
                        <td class="font-monospace small fw-semibold">{{ $payment->receipt_number ?: '—' }}</td>
                        <td class="text-muted small">
                            {{ $payment->feeStructure ? ucwords(str_replace('_', ' ', $payment->feeStructure->fee_type)) : '—' }}
                        </td>
                        <td class="text-end fw-bold" style="font-size:.95rem">₹{{ number_format($payment->amount_paid, 2) }}</td>
                        <td class="small">{{ $payment->payment_date ? $payment->payment_date->format('d M Y') : '—' }}</td>
                        <td class="small text-muted">{{ $payment->payment_method ? ucfirst($payment->payment_method) : '—' }}</td>
                        <td>
                            @if($payment->status === 'paid')
                                <span class="badge-paid">Paid</span>
                            @elseif($payment->status === 'pending')
                                <span class="badge-pending">Pending</span>
                            @else
                                <span class="badge bg-secondary">{{ ucfirst($payment->status) }}</span>
                            @endif
                        </td>
                        <td>
                            @if($payment->status === 'paid')
                            <a href="{{ route('student.reports.fee-receipt', $payment->id) }}" target="_blank"
                               class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:.75rem"
                               aria-label="Download receipt PDF">
                                <i class="bi bi-download me-1"></i>Receipt
                            </a>
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
