@extends('layouts.parent')
@section('title', 'Fee Status - '.$student->user->name)
@section('page-title', 'Fee Status')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('parent.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('parent.children') }}">My Children</a></li>
    <li class="breadcrumb-item active">Fees</li>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h5 class="fw-bold mb-0">{{ $student->user->name }} - Fee Status</h5>
        <div class="text-muted" style="font-size:.82rem">{{ optional($student->program)->name ?? optional($student->course)->name }}</div>
    </div>
    <a href="{{ route('parent.children') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

@if($finance['overdue_count'] > 0)
    <div class="alert alert-danger">
        <div class="fw-semibold">Fee follow-up needed</div>
        <div>{{ $finance['overdue_count'] }} demand(s) are overdue. Review the open demand list and coordinate payment with the student or accounts office.</div>
    </div>
@elseif($balance > 0)
    <div class="alert alert-warning">
        <div class="fw-semibold">Open fee balance</div>
        <div>Rs. {{ number_format($balance, 0) }} remains open across {{ $finance['open_demand_count'] }} active demand(s).</div>
    </div>
@else
    <div class="alert alert-success">
        <div class="fw-semibold">No open fee balance</div>
        <div>All active fee demands are currently clear.</div>
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="kpi-card kpi-blue">
            <div class="kpi-label">Demanded + Active Penalty</div>
            <div class="kpi-value">Rs. {{ number_format($feeDue) }}</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="kpi-card kpi-green">
            <div class="kpi-label">Paid</div>
            <div class="kpi-value">Rs. {{ number_format($feePaid) }}</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="kpi-card {{ $balance > 0 ? 'kpi-red' : 'kpi-green' }}">
            <div class="kpi-label">Open Balance</div>
            <div class="kpi-value">Rs. {{ number_format($balance) }}</div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header fw-semibold"><i class="bi bi-calendar2-check me-2 text-primary"></i>Fee Demands</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Term</th>
                        <th>Due Date</th>
                        <th>Demand</th>
                        <th>Penalty</th>
                        <th>Total Open</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($feeDemands as $demand)
                @php
                    $isOpen = in_array($demand->status, ['pending', 'partially_paid', 'overdue'], true);
                    $isOverdue = $demand->status === 'overdue' || ($demand->status === 'pending' && $demand->due_date && $demand->due_date->isPast());
                    $openTotal = $isOpen ? (float) $demand->final_amount + (float) ($demand->penalty_amount ?? 0) : 0;
                @endphp
                <tr>
                    <td>{{ $demand->term?->name ?? '-' }}</td>
                    <td>{{ $demand->due_date ? $demand->due_date->format('d M Y') : '-' }}</td>
                    <td>Rs. {{ number_format($demand->final_amount, 0) }}</td>
                    <td>Rs. {{ number_format($demand->penalty_amount ?? 0, 0) }}</td>
                    <td class="{{ $openTotal > 0 ? 'text-danger fw-semibold' : 'text-success' }}">Rs. {{ number_format($openTotal, 0) }}</td>
                    <td>
                        <span class="badge {{ $isOverdue ? 'bg-danger' : ($demand->status === 'fully_paid' ? 'bg-success' : 'bg-warning text-dark') }}">
                            {{ ucwords(str_replace('_', ' ', $demand->status)) }}
                        </span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-3">No fee demands recorded yet.</td>
                </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($hostelFeeDemands->isNotEmpty())
<div class="card mb-4">
    <div class="card-header fw-semibold"><i class="bi bi-house-door me-2 text-success"></i>Hostel Fee Demands</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Month</th>
                        <th>Room</th>
                        <th>Due Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($hostelFeeDemands as $demand)
                @php
                    $isHostelOverdue = $demand->status === 'pending' && $demand->due_date && $demand->due_date->isPast();
                @endphp
                <tr>
                    <td>{{ $demand->month }}</td>
                    <td>{{ $demand->allocation?->room?->block?->name ?? 'Hostel' }} / Room {{ $demand->allocation?->room?->room_number ?? '-' }}</td>
                    <td>{{ $demand->due_date ? $demand->due_date->format('d M Y') : '-' }}</td>
                    <td class="{{ $demand->status === 'pending' ? 'text-danger fw-semibold' : 'text-success' }}">Rs. {{ number_format($demand->amount, 0) }}</td>
                    <td>
                        <span class="badge {{ $isHostelOverdue ? 'bg-danger' : ($demand->status === 'paid' ? 'bg-success' : ($demand->status === 'waived' ? 'bg-secondary' : 'bg-warning text-dark')) }}">
                            {{ $isHostelOverdue ? 'Overdue' : ucfirst($demand->status) }}
                        </span>
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

<div class="card">
    <div class="card-header fw-semibold"><i class="bi bi-receipt me-2 text-primary"></i>Payment History</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
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
                    <td>{{ $payment->receipt_number ?? '-' }}</td>
                    <td>{{ optional($payment->feeStructure)->fee_type ?? '-' }}</td>
                    <td>Rs. {{ number_format($payment->amount_paid) }}</td>
                    <td>{{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('d M Y') : '-' }}</td>
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
</div>
@endsection
