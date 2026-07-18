@extends('layouts.admin')
@section('title', 'Accounts - Fee Collections')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-receipt me-2 text-primary"></i>Fee Collections</h4>
</div>

<div class="alert alert-info border-0 shadow-sm py-2 mb-3">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
        <div>
            <div class="fw-semibold">Collection source-list workflow</div>
            <div class="small text-muted">Use this list to verify posted student payments, review filters, and export the exact collection view currently on screen.</div>
            <div class="small text-muted mt-1">
                <span class="badge text-bg-light me-1">Owner: Accounts office</span>
                <span class="badge text-bg-light">Source: verified fee payment records</span>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-1">
            <span class="badge text-bg-light">1. Filter program/batch/status</span>
            <span class="badge text-bg-light">2. Check receipt and student</span>
            <span class="badge text-bg-light">3. Review paid/pending state</span>
            <span class="badge text-bg-light">4. Export current view</span>
        </div>
    </div>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-sm-2">
        <select aria-label="Program" name="program_id" class="form-select form-select-sm">
            <option value="">All Programs</option>
            @foreach($programs as $p)<option value="{{ $p->id }}" @selected(request('program_id')==$p->id)>{{ $p->name }}</option>@endforeach
        </select>
    </div>
    <div class="col-sm-2">
        <select aria-label="Batch" name="batch_id" class="form-select form-select-sm">
            <option value="">All Batches</option>
            @foreach($batches as $b)<option value="{{ $b->id }}" @selected(request('batch_id')==$b->id)>{{ $b->name }}</option>@endforeach
        </select>
    </div>
    <div class="col-sm-2">
        <select aria-label="Status" name="status" class="form-select form-select-sm">
            <option value="">All Status</option>
            <option value="paid" @selected(request('status')=='paid')>Paid</option>
            <option value="pending" @selected(request('status')=='pending')>Pending</option>
        </select>
    </div>
    <div class="col-sm-2"><input aria-label="Date From" type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}"></div>
    <div class="col-sm-2"><input aria-label="Date To" type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}"></div>
    <div class="col-auto"><button class="btn btn-sm btn-primary">Filter</button></div>
    <div class="col-auto"><a href="{{ route('accounts.fee-collections') }}" class="btn btn-sm btn-outline-secondary">Clear</a></div>
    <div class="col-auto">
        <a href="{{ route('accounts.export-fee-collections', request()->query()) }}" class="btn btn-sm btn-outline-success">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent">
        <div class="fw-semibold">Filtered Source List ({{ $payments->total() }})</div>
        <div class="small text-muted">Visible filter summary: {{ collect(request()->only(['program_id', 'batch_id', 'status', 'date_from', 'date_to']))->filter(fn ($value) => $value !== null && $value !== '')->map(fn ($value, $key) => str($key)->headline() . ': ' . $value)->join(' | ') ?: 'Showing all scoped fee collections.' }}</div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th scope="col">Receipt</th><th scope="col">Student</th><th scope="col">Program</th><th scope="col">Owner / Source</th><th scope="col">Amount</th><th scope="col">Date</th><th scope="col">Method</th><th scope="col">Status</th></tr>
                </thead>
                <tbody>
                @forelse($payments as $pay)
                    <tr>
                        <td><code>{{ $pay->receipt_number ?: 'Receipt not issued' }}</code></td>
                        <td>{{ $pay->student?->user?->name ?? 'Student not linked' }}</td>
                        <td>{{ $pay->student?->program?->name ?? 'Program not assigned' }}</td>
                        <td>
                            <div class="small text-muted">Owner: Accounts office</div>
                            <div class="small text-muted">Source: Fee payment</div>
                        </td>
                        <td>Rs. {{ number_format($pay->amount_paid, 2) }}</td>
                        <td>{{ $pay->payment_date?->format('d M Y') ?? 'Payment date not recorded' }}</td>
                        <td>{{ ucfirst($pay->payment_method ?? 'Method not recorded') }}</td>
                        <td><span class="badge bg-{{ $pay->status === 'paid' ? 'success' : 'warning' }}">{{ ucfirst($pay->status) }}</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            No fee collections match this source list. Check program, batch, status, and date filters, or confirm that fee payments have been posted before exporting.
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
