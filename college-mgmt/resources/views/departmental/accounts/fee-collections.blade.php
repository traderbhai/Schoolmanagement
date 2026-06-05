@extends('layouts.admin')
@section('title', 'Accounts — Fee Collections')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-receipt me-2 text-primary"></i>Fee Collections</h4>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-sm-2">
        <select name="program_id" class="form-select form-select-sm">
            <option value="">All Programs</option>
            @foreach($programs as $p)<option value="{{ $p->id }}" @selected(request('program_id')==$p->id)>{{ $p->name }}</option>@endforeach
        </select>
    </div>
    <div class="col-sm-2">
        <select name="batch_id" class="form-select form-select-sm">
            <option value="">All Batches</option>
            @foreach($batches as $b)<option value="{{ $b->id }}" @selected(request('batch_id')==$b->id)>{{ $b->name }}</option>@endforeach
        </select>
    </div>
    <div class="col-sm-2">
        <select name="status" class="form-select form-select-sm">
            <option value="">All Status</option>
            <option value="paid" @selected(request('status')=='paid')>Paid</option>
            <option value="pending" @selected(request('status')=='pending')>Pending</option>
        </select>
    </div>
    <div class="col-sm-2"><input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}"></div>
    <div class="col-sm-2"><input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}"></div>
    <div class="col-auto"><button class="btn btn-sm btn-primary">Filter</button></div>
    <div class="col-auto"><a href="{{ route('accounts.fee-collections') }}" class="btn btn-sm btn-outline-secondary">Clear</a></div>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Receipt</th><th>Student</th><th>Program</th><th>Amount</th><th>Date</th><th>Method</th><th>Status</th></tr>
                </thead>
                <tbody>
                @forelse($payments as $pay)
                    <tr>
                        <td><code>{{ $pay->receipt_number }}</code></td>
                        <td>{{ $pay->student?->user?->name ?? '—' }}</td>
                        <td>{{ $pay->student?->program?->name ?? '—' }}</td>
                        <td>₹{{ number_format($pay->amount_paid, 2) }}</td>
                        <td>{{ $pay->payment_date?->format('d M Y') }}</td>
                        <td>{{ ucfirst($pay->payment_method ?? '—') }}</td>
                        <td><span class="badge bg-{{ $pay->status === 'paid' ? 'success' : 'warning' }}">{{ ucfirst($pay->status) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">No payments found.</td></tr>
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
