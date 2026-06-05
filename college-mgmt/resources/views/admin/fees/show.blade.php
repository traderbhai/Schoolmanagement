@extends('layouts.admin')
@section('title', 'Fee Structure')
@section('page-title', 'Fee Structure Details')
@section('content')

<div class="d-flex align-items-center justify-content-between mb-4">
    <div></div>
    <a href="{{ route('admin.fees.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Fees</a>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <div class="text-muted" style="font-size:.76rem;text-transform:uppercase;letter-spacing:.06em">Fee Type</div>
                <h5 class="fw-bold mt-1">{{ $fee->fee_type }}</h5>
                <div class="fw-bold text-success" style="font-size:2rem">₹{{ number_format($fee->amount, 2) }}</div>

                <table class="table table-sm table-borderless small mb-0 mt-3">
                    <tr><td class="text-muted">Course</td><td class="fw-semibold">{{ $fee->course->name }}</td></tr>
                    <tr><td class="text-muted">Academic Year</td><td>{{ $fee->academicYear->name }}</td></tr>
                    <tr><td class="text-muted">Semester</td><td>{{ $fee->semester_number ? 'Sem '.$fee->semester_number : 'All Semesters' }}</td></tr>
                    <tr><td class="text-muted">Total Payments</td><td>{{ $fee->payments->count() }}</td></tr>
                    <tr><td class="text-muted">Total Collected</td><td class="fw-semibold text-success">₹{{ number_format($fee->payments->sum('amount_paid'), 2) }}</td></tr>
                </table>

                <div class="d-flex gap-2 mt-3">
                    <a href="{{ route('admin.fees.edit', $fee) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>
                    <a href="{{ route('admin.fees.collect') }}" class="btn btn-success btn-sm"><i class="bi bi-cash me-1"></i>Collect</a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Payment Records</span>
                <a href="{{ route('admin.fees.collect') }}" class="btn btn-sm btn-success"><i class="bi bi-cash me-1"></i>Collect Payment</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Student</th><th>Receipt</th><th>Amount</th><th>Date</th><th>Method</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                    @forelse($fee->payments as $p)
                    <tr>
                        <td class="fw-semibold" style="font-size:.875rem">{{ $p->student->user->name }}</td>
                        <td><code>{{ $p->receipt_number }}</code></td>
                        <td class="fw-semibold text-success">₹{{ number_format($p->amount_paid, 2) }}</td>
                        <td style="font-size:.84rem">{{ $p->payment_date->format('d M Y') }}</td>
                        <td>{{ ucfirst($p->payment_method) }}</td>
                        <td><span class="badge {{ $p->status === 'paid' ? 'badge-paid' : 'badge-pending' }}">{{ ucfirst($p->status) }}</span></td>
                        <td>
                            <a href="{{ route('admin.reports.fee-receipt', $p->id) }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:.75rem" aria-label="Download PDF receipt">
                                <i class="bi bi-download"></i> PDF
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7">
                        <div class="empty-state py-4">
                            <div class="empty-icon"><i class="bi bi-receipt"></i></div>
                            <div class="text-muted">No payments recorded yet.</div>
                        </div>
                    </td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
