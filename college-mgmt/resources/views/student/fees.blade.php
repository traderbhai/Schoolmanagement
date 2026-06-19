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
                        <div class="fw-bold lh-1 mt-1" style="font-size:2rem">Rs. {{ number_format($totalDue) }}</div>
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
                        <div class="fw-bold lh-1 mt-1" style="font-size:2rem">Rs. {{ number_format($totalPaid) }}</div>
                        <div class="opacity-75 mt-2" style="font-size:.78rem">Confirmed payments</div>
                    </div>
                    <div class="opacity-40"><i class="bi bi-check-circle-fill" style="font-size:2.5rem"></i></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Balance Due --}}
    <div class="col-md-4">
        @if($overallBalanceDue > 0)
        <div class="card border-0 h-100" style="background:linear-gradient(135deg,#dc2626,#b91c1c)">
        @else
        <div class="card border-0 h-100" style="background:linear-gradient(135deg,#059669,#047857)">
        @endif
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="opacity-75 small fw-semibold mb-1">Balance Due</div>
                        <div class="fw-bold lh-1 mt-1" style="font-size:2rem">Rs. {{ number_format($overallBalanceDue) }}</div>
                        <div class="opacity-75 mt-2" style="font-size:.78rem">
                            {{ $overallBalanceDue > 0 ? 'Academic and hostel outstanding amount' : 'Fully paid - No dues' }}
                        </div>
                    </div>
                    <div class="opacity-40">
                        <i class="bi bi-{{ $overallBalanceDue > 0 ? 'exclamation-circle-fill' : 'check2-all' }}" style="font-size:2.5rem"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Outstanding Fee Demands Summary --}}
@if($feeDemands->isNotEmpty())
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent fw-semibold d-flex align-items-center gap-2">
        <i class="bi bi-file-earmark-text text-primary"></i> Fee Demands
        @if($outstandingTotal > 0)
            <span class="badge bg-danger ms-auto">Outstanding: Rs. {{ number_format($outstandingTotal, 2) }}</span>
        @endif
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Term</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Penalty</th>
                        <th>Due Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($feeDemands as $demand)
                    @php
                        $isOverdue = $demand->status === 'overdue'
                            || ($demand->due_date && $demand->due_date->isPast() && $demand->status === 'pending');
                    @endphp
                    <tr>
                        <td>{{ $demand->term->name ?? '-' }}</td>
                        <td class="text-end">Rs. {{ number_format($demand->final_amount, 2) }}</td>
                        <td class="text-end">
                            @if(($demand->penalty_amount ?? 0) > 0)
                                <span class="text-danger fw-semibold">Rs. {{ number_format($demand->penalty_amount, 2) }}</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="{{ $isOverdue ? 'text-danger fw-semibold' : '' }}">
                            {{ $demand->due_date ? $demand->due_date->format('d M Y') : '-' }}
                            @if($isOverdue) <i class="bi bi-exclamation-circle-fill ms-1"></i> @endif
                        </td>
                        <td>
                            @if($demand->status === 'fully_paid')
                                <span class="badge bg-success">Paid</span>
                            @elseif($isOverdue)
                                <span class="badge bg-danger">Overdue</span>
                            @elseif($demand->status === 'partially_paid')
                                <span class="badge bg-info">Partial</span>
                            @else
                                <span class="badge bg-warning text-dark">Pending</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

{{-- Hostel Fee Demands --}}
@if($hostelFeeDemands->isNotEmpty())
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent fw-semibold d-flex align-items-center gap-2">
        <i class="bi bi-house-door text-success"></i> Hostel Fee Demands
        @if($hostelOutstandingTotal > 0)
            <span class="badge bg-danger ms-auto">Outstanding: Rs. {{ number_format($hostelOutstandingTotal, 2) }}</span>
        @endif
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Month</th>
                        <th>Room</th>
                        <th class="text-end">Amount</th>
                        <th>Due Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($hostelFeeDemands as $demand)
                    @php
                        $isHostelOverdue = $demand->due_date && $demand->due_date->isPast() && $demand->status === 'pending';
                    @endphp
                    <tr>
                        <td>{{ $demand->month }}</td>
                        <td>
                            {{ $demand->allocation->room->block->name ?? 'Hostel' }} /
                            Room {{ $demand->allocation->room->room_number ?? '-' }}
                        </td>
                        <td class="text-end fw-semibold">Rs. {{ number_format($demand->amount, 2) }}</td>
                        <td class="{{ $isHostelOverdue ? 'text-danger fw-semibold' : '' }}">
                            {{ $demand->due_date ? $demand->due_date->format('d M Y') : '-' }}
                            @if($isHostelOverdue) <i class="bi bi-exclamation-circle-fill ms-1"></i> @endif
                        </td>
                        <td>
                            @if($demand->status === 'paid')
                                <span class="badge bg-success">Paid</span>
                            @elseif($demand->status === 'waived')
                                <span class="badge bg-secondary">Waived</span>
                            @elseif($isHostelOverdue)
                                <span class="badge bg-danger">Overdue</span>
                            @else
                                <span class="badge bg-warning text-dark">Pending</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

{{-- How to Pay Info Box --}}
<div class="alert alert-info d-flex gap-2 align-items-start mb-4" role="alert">
    <i class="bi bi-info-circle-fill fs-5 mt-1 flex-shrink-0"></i>
    <div>
        <strong>How to Pay Your Fees</strong><br>
        Contact the accounts office or admin portal for fee payment. Mention your enrollment number and a receipt will be issued immediately. Online payment options (NEFT, UPI, RTGS) are also accepted - share the transaction reference with the accounts team.
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
                        <td class="text-muted">{{ $fs->description ?: '-' }}</td>
                        <td class="text-end fw-semibold">Rs. {{ number_format($fs->amount, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="2" class="fw-bold text-end">Total Fee Due</td>
                        <td class="text-end fw-bold text-primary" style="font-size:1.05rem">Rs. {{ number_format($totalDue, 2) }}</td>
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
                    <tr style="{{ $payment->status === 'paid' ? 'background:#f0fdf4' : ($payment->status === 'pending' ? 'background:#fffbeb' : '') }}">
                        <td>
                            @if($payment->receipt_number)
                                <span class="font-monospace fw-bold" style="font-size:.82rem;color:#059669">{{ $payment->receipt_number }}</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="text-muted small">
                            {{ $payment->feeStructure ? ucwords(str_replace('_', ' ', $payment->feeStructure->fee_type)) : '-' }}
                        </td>
                        <td class="text-end fw-bold" style="font-size:.95rem">Rs. {{ number_format($payment->amount_paid, 2) }}</td>
                        <td class="small">{{ $payment->payment_date ? $payment->payment_date->format('d M Y') : '-' }}</td>
                        <td class="small text-muted">{{ $payment->payment_method ? ucfirst($payment->payment_method) : '-' }}</td>
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
