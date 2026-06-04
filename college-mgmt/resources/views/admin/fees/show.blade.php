@extends('layouts.admin')
@section('title','Fee Structure')
@section('page-title','Fee Structure Details')
@section('content')
<div class="row g-3">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h5 class="fw-bold">{{ $fee->fee_type }}</h5>
                <div class="fs-3 fw-bold text-success mb-2">₹{{ number_format($fee->amount, 2) }}</div>
                <table class="table table-sm table-borderless small mb-0">
                    <tr><td class="text-muted">Course</td><td>{{ $fee->course->name }}</td></tr>
                    <tr><td class="text-muted">Academic Year</td><td>{{ $fee->academicYear->name }}</td></tr>
                    <tr><td class="text-muted">Semester</td><td>{{ $fee->semester_number ? 'Sem '.$fee->semester_number : 'All Semesters' }}</td></tr>
                    <tr><td class="text-muted">Total Payments</td><td>{{ $fee->payments->count() }}</td></tr>
                    <tr><td class="text-muted">Total Collected</td><td class="fw-semibold text-success">₹{{ number_format($fee->payments->sum('amount_paid'), 2) }}</td></tr>
                </table>
                <a href="{{ route('admin.fees.edit', $fee) }}" class="btn btn-sm btn-outline-primary mt-3">Edit</a>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Payment Records</span>
                <a href="{{ route('admin.fees.collect') }}" class="btn btn-sm btn-success">Collect Payment</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Student</th><th>Receipt</th><th>Amount</th><th>Date</th><th>Method</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse($fee->payments as $p)
                    <tr>
                        <td>{{ $p->student->user->name }}</td>
                        <td><code>{{ $p->receipt_number }}</code></td>
                        <td class="fw-semibold">₹{{ number_format($p->amount_paid, 2) }}</td>
                        <td>{{ $p->payment_date->format('d M Y') }}</td>
                        <td>{{ ucfirst($p->payment_method) }}</td>
                        <td><span class="badge {{ $p->status === 'paid' ? 'bg-success' : 'bg-warning text-dark' }}">{{ ucfirst($p->status) }}</span></td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">No payments recorded.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
