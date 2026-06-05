@extends('layouts.admin')
@section('title', 'Accounts — Admission Payments')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-cash-coin me-2 text-primary"></i>Admission Payment Verification</h4>
    <a href="{{ route('admission.payments.queue') }}" class="btn btn-sm btn-outline-primary">Go to CRM Payment Queue</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Applicant</th><th>Program</th><th>Amount</th><th>Date</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                @forelse($payments as $pay)
                    <tr>
                        <td>{{ $pay->applicant?->user?->name ?? '—' }}</td>
                        <td>{{ $pay->applicant?->program?->name ?? '—' }}</td>
                        <td>₹{{ number_format($pay->amount ?? 0, 2) }}</td>
                        <td>{{ $pay->created_at?->format('d M Y') }}</td>
                        <td><span class="badge bg-warning text-dark">{{ ucfirst($pay->status) }}</span></td>
                        <td>
                            <a href="{{ route('admission.payments.index', $pay->applicant?->program_id ?? 0) }}" class="btn btn-sm btn-outline-primary">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">No pending admission payments.</td></tr>
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
