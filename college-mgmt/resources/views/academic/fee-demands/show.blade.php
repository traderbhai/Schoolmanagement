@extends('layouts.admin')

@section('title', 'Fee Demand Details')
@section('page-title', 'Fee Demand Details')

@section('content')
<div class="container-fluid py-4">
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent fw-semibold">Fee Demand Information</div>
                <div class="card-body">
                    <p><strong>Student:</strong> {{ $feeDemand->student->user->name ?? $feeDemand->student->enrollment_number ?? 'Student name missing' }}</p>
                    <p><strong>Enrollment No:</strong> {{ $feeDemand->student->enrollment_number ?? 'Enrollment number pending' }}</p>
                    <p><strong>Program:</strong> {{ $feeDemand->student->program->name ?? 'Program not linked' }}</p>
                    <p><strong>Term:</strong> {{ $feeDemand->term->name ?? 'Term not linked' }}</p>
                    <p><strong>Total Amount:</strong> Rs. {{ number_format($feeDemand->total_amount, 2) }}</p>
                    <p><strong>Scholarship Deduction:</strong> Rs. {{ number_format($feeDemand->scholarship_deduction ?? 0, 2) }}</p>
                    <p><strong>Final Amount:</strong> Rs. {{ number_format($feeDemand->final_amount, 2) }}</p>
                    <p><strong>Penalty Amount:</strong>
                        @if(($feeDemand->penalty_amount ?? 0) > 0)
                            <span class="text-danger fw-semibold">Rs. {{ number_format($feeDemand->penalty_amount, 2) }}</span>
                        @else
                            <span class="text-muted">No penalty</span>
                        @endif
                    </p>
                    <p><strong>Due Date:</strong>
                        @if($feeDemand->due_date)
                            <span class="{{ $feeDemand->due_date->isPast() && !$feeDemand->isPaid() ? 'text-danger fw-semibold' : '' }}">
                                {{ $feeDemand->due_date?->format('d M Y') ?? 'Due date not published' }}
                                @if($feeDemand->due_date->isPast() && !$feeDemand->isPaid())
                                    <i class="bi bi-exclamation-circle-fill ms-1"></i>
                                @endif
                            </span>
                        @else
                            <span class="text-muted">Due date not published</span>
                        @endif
                    </p>
                    <p><strong>Status:</strong>
                        <span class="badge bg-{{ $feeDemand->status === 'fully_paid' ? 'success' : ($feeDemand->status === 'partially_paid' ? 'info' : 'warning') }}">
                            {{ ucfirst(str_replace('_', ' ', $feeDemand->status)) }}
                        </span>
                    </p>

                    <div class="d-flex gap-2 mt-3">
                        @if(!$feeDemand->isPaid())
                            <form action="{{ route('academic.fee-demands.mark-paid', $feeDemand) }}" method="POST" class="d-inline">
                                @csrf
                                <button class="btn btn-success btn-sm">Mark as Paid</button>
                            </form>
                        @endif
                        <a href="{{ route('academic.fee-demands.edit', $feeDemand) }}" class="btn btn-warning btn-sm">Edit</a>
                        <a href="{{ route('academic.fee-demands.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent fw-semibold">Payment History</div>
                <div class="card-body p-0">
                    @if($payments->isEmpty())
                        <div class="p-4 text-center text-muted">
                            No payments are linked to this demand yet. Record a verified payment or payment proof before marking the demand as paid.
                        </div>
                    @else
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Date</th>
                                    <th scope="col" class="text-end">Amount</th>
                                    <th scope="col">Method</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($payments as $p)
                                <tr>
                                    <td class="small">{{ $p->payment_date?->format('d M Y') ?? 'Payment date pending' }}</td>
                                    <td class="text-end small">Rs. {{ number_format($p->amount_paid, 2) }}</td>
                                    <td class="small text-muted">{{ $p->payment_method ? ucfirst($p->payment_method) : 'Payment method pending' }}</td>
                                    <td>
                                        <span class="badge bg-{{ $p->status === 'paid' ? 'success' : 'warning' }}">
                                            {{ ucfirst($p->status) }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
