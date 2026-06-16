@extends('layouts.student')
@section('title', 'Fee Payment Submissions')
@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-semibold mb-0">Fee Payment Submissions</h4>
        <a href="{{ route('student.fee-payment.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Submit Payment Proof
        </a>
    </div>

    @if($demands->isNotEmpty())
    <div class="alert alert-warning mb-4">
        <strong>Outstanding Fee Demands:</strong>
        @foreach($demands as $d)
        <span class="badge bg-danger ms-1">{{ $d->term?->name ?? 'Fee Demand' }}: INR {{ number_format((float) $d->final_amount + (float) ($d->penalty_amount ?? 0), 0) }}</span>
        @endforeach
    </div>
    @endif

    @if($requests->isEmpty())
    <div class="alert alert-info">No payment submissions yet.</div>
    @else
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr><th>Amount</th><th>Method</th><th>Transaction Ref</th><th>Submitted</th><th>Status</th><th>Notes</th></tr>
            </thead>
            <tbody>
                @foreach($requests as $r)
                <tr>
                    <td class="fw-semibold">INR {{ number_format($r->amount,0) }}</td>
                    <td>{{ ucfirst($r->payment_method) }}</td>
                    <td class="text-muted small">{{ $r->transaction_ref ?: '—' }}</td>
                    <td class="text-muted small">{{ $r->submitted_at->format('d M Y') }}</td>
                    <td>
                        @if($r->status === 'pending') <span class="badge bg-warning text-dark">Pending Verification</span>
                        @elseif($r->status === 'verified') <span class="badge bg-success">Verified</span>
                        @else <span class="badge bg-danger">Rejected</span>
                        @endif
                    </td>
                    <td class="text-muted small">{{ $r->notes ?: '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $requests->links() }}
    @endif
</div>
@endsection
