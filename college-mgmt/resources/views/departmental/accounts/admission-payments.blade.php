@extends('layouts.admin')
@section('title', 'Accounts - Admission Payments')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-cash-coin me-2 text-primary"></i>Admission Payment Verification</h4>
    <a href="{{ route('admission.payments.queue') }}" class="btn btn-sm btn-outline-primary">Go to CRM Payment Queue</a>
</div>

<div class="alert alert-info border-0 shadow-sm py-2 mb-3">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
        <div>
            <div class="fw-semibold">Admission payment verification workflow</div>
            <div class="small text-muted">Use this queue to review applicant payment proof before offer, seat, or enrollment workflows depend on payment status.</div>
            <div class="small text-muted mt-1">
                <span class="badge text-bg-light me-1">Owner: Accounts office</span>
                <span class="badge text-bg-light">Source: pending Admission payment submissions</span>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-1">
            <span class="badge text-bg-light">1. Open proof queue</span>
            <span class="badge text-bg-light">2. Check applicant/program</span>
            <span class="badge text-bg-light">3. Verify or reject in CRM</span>
            <span class="badge text-bg-light">4. Reconcile after verification</span>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent">
        <div class="fw-semibold">Filtered Source List ({{ $payments->total() }})</div>
        <div class="small text-muted">Visible filter summary: Pending admission payments requiring accounts verification.</div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Applicant</th><th>Program</th><th>Owner / Source</th><th>Amount</th><th>Date</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                @forelse($payments as $pay)
                    <tr>
                        <td>{{ $pay->applicant?->user?->name ?? 'Applicant not linked' }}</td>
                        <td>{{ $pay->applicant?->program?->name ?? 'Program not assigned' }}</td>
                        <td>
                            <div class="small text-muted">Owner: Accounts office</div>
                            <div class="small text-muted">Source: Admission payment</div>
                        </td>
                        <td>Rs. {{ number_format($pay->amount_paid ?? $pay->amount ?? 0, 2) }}</td>
                        <td>{{ $pay->created_at?->format('d M Y') ?? 'Submission date not recorded' }}</td>
                        <td><span class="badge bg-warning text-dark">{{ ucfirst($pay->status) }}</span></td>
                        <td>
                            <a href="{{ route('admission.payments.index', $pay->applicant?->program_id ?? 0) }}" class="btn btn-sm btn-outline-primary">Open source</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            No pending admission payments require Accounts review. If a payment is expected, check the Admission CRM payment queue, applicant program assignment, or submitted proof status.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($payments->hasPages())
    <div class="card-footer bg-transparent">{{ $payments->links() }}</div>
    @endif
</div>
@endsection
