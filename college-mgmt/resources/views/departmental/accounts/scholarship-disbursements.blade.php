@extends('layouts.admin')
@section('title', 'Accounts - Scholarship Disbursements')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-award-fill me-2 text-primary"></i>Scholarship Disbursement Queue</h4>
    <a href="{{ route('accounts.reconciliation') }}" class="btn btn-sm btn-outline-primary">Open Reconciliation</a>
</div>

<div class="alert alert-info border-0 shadow-sm py-2 mb-3">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
        <div>
            <div class="fw-semibold">Accounts scholarship workflow</div>
            <div class="small text-muted">Review awarded scholarships before disbursement and keep receipt reconciliation aligned with Admission awards.</div>
            <div class="small text-muted mt-1">
                <span class="badge text-bg-light me-1">Owner: Accounts office</span>
                <span class="badge text-bg-light">Source: awarded applicant scholarships</span>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-1">
            <span class="badge text-bg-light">1. Review award</span>
            <span class="badge text-bg-light">2. Confirm applicant</span>
            <span class="badge text-bg-light">3. Prepare disbursement</span>
            <span class="badge text-bg-light">4. Reconcile reference</span>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-3 fw-bold text-warning">{{ $pending->total() }}</div>
            <div class="text-muted small">Pending Disbursement</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-3 fw-bold text-danger">Rs. {{ number_format($totalPendingAmount, 0) }}</div>
            <div class="text-muted small">Total Pending Amount</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-3 fw-bold text-success">Rs. {{ number_format($totalDisbursed, 0) }}</div>
            <div class="text-muted small">Total Disbursed</div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent">
        <form method="GET" action="{{ route('accounts.scholarship-disbursements') }}" class="row g-2 align-items-end">
            <div class="col-md-8">
                <label class="form-label small text-muted mb-1">Program</label>
                <select aria-label="Program" name="program_id" class="form-select form-select-sm">
                    <option value="">All Programs</option>
                    @foreach($programs as $program)
                        <option value="{{ $program->id }}" @selected((string) request('program_id') === (string) $program->id)>{{ $program->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-sm btn-primary flex-fill">Filter</button>
                <a href="{{ route('accounts.scholarship-disbursements') }}" class="btn btn-sm btn-outline-secondary flex-fill">Reset</a>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Applicant</th>
                        <th scope="col">Program</th>
                        <th scope="col">Scheme</th>
                        <th scope="col" class="text-end">Amount</th>
                        <th scope="col">Awarded</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($pending as $award)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $award->applicant?->user?->name ?? 'Applicant not linked' }}</div>
                            <div class="small text-muted">{{ $award->applicant?->application_number ?? 'Application number missing' }}</div>
                        </td>
                        <td>{{ $award->applicant?->program?->name ?? 'Program not assigned' }}</td>
                        <td>
                            {{ $award->scheme?->name ?? 'Scheme not linked' }}
                            <div class="small text-muted">{{ $award->scheme?->scheme_code ?? 'Scheme code missing' }}</div>
                        </td>
                        <td class="text-end fw-semibold">Rs. {{ number_format($award->awarded_amount, 2) }}</td>
                        <td>{{ $award->awarded_at?->format('d M Y') ?? 'Award date not recorded' }}</td>
                        <td><span class="badge bg-warning text-dark">Awaiting disbursement</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            No awarded scholarships are pending disbursement for this Accounts scope. New awards appear here after Admission records an approved scholarship.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($pending->hasPages())
    <div class="card-footer bg-transparent">{{ $pending->links() }}</div>
    @endif
</div>
@endsection
