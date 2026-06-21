@extends('layouts.admin')
@section('title', 'Payments - ' . $program->name)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0 fw-bold">{{ $program->name }}</h2>
        <small class="text-muted">Payment Overview</small>
    </div>
    <a href="{{ route('admission.payments.queue', ['program_id' => $program->id]) }}" class="btn btn-warning btn-sm">
        <i class="bi bi-clock me-1"></i>View Pending Queue
    </a>
</div>

{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-3">
                <div class="fs-3 fw-bold text-dark">Rs. {{ number_format($summary['total_expected'], 0) }}</div>
                <div class="small text-muted">Total Expected</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-3">
                <div class="fs-3 fw-bold text-success">Rs. {{ number_format($summary['total_verified'], 0) }}</div>
                <div class="small text-muted">Total Collected (Verified)</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-3">
                <div class="fs-3 fw-bold text-warning">Rs. {{ number_format($summary['total_pending'], 0) }}</div>
                <div class="small text-muted">Pending Verification</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-3">
                <div class="fs-3 fw-bold text-danger">{{ $summary['total_rejected'] }}</div>
                <div class="small text-muted">Rejected</div>
            </div>
        </div>
    </div>
</div>

{{-- Installment-wise Breakdown --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom py-3">
        <h5 class="mb-0 fw-bold">Installment-wise Breakdown</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="bg-light">
                <tr>
                    <th>Installment</th>
                    <th>Amount</th>
                    <th>Total Applicants</th>
                    <th>Paid & Verified</th>
                    <th>Pending</th>
                    <th>Amount Collected</th>
                </tr>
            </thead>
            <tbody>
                @forelse($installmentBreakdown as $row)
                <tr>
                    <td class="fw-semibold">{{ $row['installment']->name }}</td>
                    <td>Rs. {{ number_format($row['installment']->amount, 0) }}</td>
                    <td>{{ $row['total_applicants'] }}</td>
                    <td>
                        <span class="badge bg-success">{{ $row['paid_count'] }}</span>
                    </td>
                    <td>
                        <span class="badge bg-warning text-dark">{{ $row['pending_count'] }}</span>
                    </td>
                    <td class="fw-bold text-success">Rs. {{ number_format($row['amount_collected'], 0) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-3">No installments configured.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Filters + All Payments --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">All Payments</h5>
        <form method="GET" class="d-flex gap-2 align-items-center">
            <select name="installment_id" class="form-select form-select-sm" style="width:auto">
                <option value="">All Installments</option>
                @foreach($installments as $inst)
                    <option value="{{ $inst->id }}" {{ request('installment_id') == $inst->id ? 'selected' : '' }}>
                        {{ $inst->name }}
                    </option>
                @endforeach
            </select>
            <select name="status" class="form-select form-select-sm" style="width:auto">
                <option value="">All Status</option>
                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="verified" {{ request('status') == 'verified' ? 'selected' : '' }}>Verified</option>
                <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
            </select>
            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
        </form>
    </div>
    <div class="card-body p-0">
        @if($payments->isEmpty())
            <div class="text-center py-5 px-3">
                <h6 class="fw-semibold mb-1">No payments are visible for this program</h6>
                <p class="text-muted small mb-3">
                    Payment records appear here only when they match your Admission role scope, this program,
                    and the active installment/status filters.
                </p>
                <div class="d-flex justify-content-center gap-2 flex-wrap">
                    <a href="{{ route('admission.payments.index', $program) }}" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
                    <a href="{{ route('admission.applicants.index', ['program_id' => $program->id]) }}" class="btn btn-sm btn-outline-primary">Open Applicants</a>
                    <a href="{{ route('admission.payments.queue', ['program_id' => $program->id]) }}" class="btn btn-sm btn-warning">View Pending Queue</a>
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
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payments as $payment)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $payment->applicant->user->name ?? 'Applicant name missing' }}</div>
                            <small class="text-muted">{{ $payment->applicant->application_number ?? 'Application number missing' }}</small>
                        </td>
                        <td>{{ $payment->installment->name ?? 'Installment not linked' }}</td>
                        <td>Rs. {{ number_format($payment->amount_paid, 0) }}</td>
                        <td><span class="badge bg-info text-dark">{{ $payment->payment_mode_label }}</span></td>
                        <td>{{ $payment->payment_date->format('d M Y') }}</td>
                        <td><span class="{{ $payment->status_badge }}">{{ ucfirst($payment->status) }}</span></td>
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
