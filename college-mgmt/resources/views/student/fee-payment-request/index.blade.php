@extends('layouts.student')
@section('title', 'Fee Payment Submissions')
@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-semibold mb-0">Fee Payment Submissions</h4>
        @if($canSubmitPaymentProof)
            <a href="{{ route('student.fee-payment.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Submit Payment Proof
            </a>
        @endif
    </div>

    @if($student->status !== 'active')
        <div class="alert alert-info mb-4">
            New fee payment proofs are available only for active students. Contact accounts for archived records.
        </div>
    @elseif($demands->isEmpty())
        <div class="alert alert-info mb-4">
            There are no outstanding academic fee demands available for payment proof submission. Hostel fee dues, if any, are shown on Fee Status and handled through the hostel/accounts queue.
        </div>
    @endif

    @if($demands->isNotEmpty())
    <div class="alert alert-warning mb-4">
        <strong>Outstanding Fee Demands:</strong>
        @foreach($demands as $d)
        <span class="badge bg-danger ms-1">{{ $d->term?->name ?? 'Fee Demand' }}: INR {{ number_format((float) $d->final_amount + (float) ($d->penalty_amount ?? 0), 0) }}</span>
        @endforeach
    </div>
    @endif

    @if($requests->isEmpty())
    <div class="alert alert-info d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <div class="fw-semibold mb-1">No payment submissions yet.</div>
            <div class="small mb-0">
                Submitted payment proofs will appear here for accounts verification. If a fee demand is open, submit proof from this page; otherwise review Fee Status for current balances.
            </div>
        </div>
        @if($canSubmitPaymentProof)
            <a href="{{ route('student.fee-payment.create') }}" class="btn btn-sm btn-primary align-self-start align-self-md-center">
                Submit Payment Proof
            </a>
        @else
            <a href="{{ route('student.fees') }}" class="btn btn-sm btn-outline-primary align-self-start align-self-md-center">
                Review Fee Status
            </a>
        @endif
    </div>
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
