@extends('layouts.admin')
@section('title', 'Accounts — Dashboard')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-cash-stack me-2 text-primary"></i>Accounts Dashboard</h4>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body">
                <div class="fs-2 fw-bold text-primary">₹{{ number_format($totalBilled, 0) }}</div>
                <div class="text-muted small">Total Billed</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body">
                <div class="fs-2 fw-bold text-success">₹{{ number_format($totalCollected, 0) }}</div>
                <div class="text-muted small">Collected</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body">
                <div class="fs-2 fw-bold text-danger">₹{{ number_format($outstanding, 0) }}</div>
                <div class="text-muted small">Outstanding</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body">
                <div class="fs-2 fw-bold text-warning">{{ $overdue }}</div>
                <div class="text-muted small">Overdue Accounts</div>
            </div>
        </div>
    </div>
</div>

@if($totalBilled > 0)
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between mb-1">
            <span class="fw-semibold">Collection Progress</span>
            <span>{{ round(($totalCollected/$totalBilled)*100) }}%</span>
        </div>
        <div class="progress" style="height:20px">
            <div class="progress-bar bg-success" style="width:{{ min(100, ($totalCollected/$totalBilled)*100) }}%"></div>
        </div>
    </div>
</div>
@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold">Recent Payments</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Student</th><th>Amount</th><th>Date</th><th>Method</th></tr></thead>
                        <tbody>
                        @forelse($recentPayments as $p)
                            <tr>
                                <td>{{ $p->student?->user?->name ?? '—' }}</td>
                                <td>₹{{ number_format($p->amount_paid, 0) }}</td>
                                <td>{{ $p->payment_date?->format('d M Y') }}</td>
                                <td>{{ ucfirst($p->payment_method ?? '—') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">No payments yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold">Program Collection</div>
            <div class="card-body">
                @foreach($programs as $prog)
                <div class="mb-2">
                    <div class="d-flex justify-content-between small"><span>{{ $prog->name }}</span><span>₹{{ number_format($prog->collected,0) }}</span></div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
