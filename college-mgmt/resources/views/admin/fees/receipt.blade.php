@extends('layouts.admin')
@section('title', 'Fee Receipt – ' . $payment->receipt_number)
@section('page-title', 'Fee Receipt')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.fees.index') }}">Fee Management</a></li>
    <li class="breadcrumb-item active">Receipt</li>
@endsection

@section('content')
<style>
@media print {
    .no-print { display: none !important; }
    .card { box-shadow: none !important; border: 1px solid #dee2e6 !important; }
    body { font-size: 13px; }
}
</style>

<div class="no-print d-flex gap-2 mb-4 align-items-center">
    <button onclick="window.print()" class="btn btn-primary">
        <i class="bi bi-printer me-1"></i>Print Receipt
    </button>
    <a href="{{ route('admin.fees.collect') }}" class="btn btn-outline-secondary">
        <i class="bi bi-cash me-1"></i>Collect Another
    </a>
    <a href="{{ route('admin.fees.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Fees
    </a>
</div>

<div class="card" style="max-width:680px">
    <div class="card-body p-4">

        {{-- Header --}}
        <div class="text-center mb-4 pb-3 border-bottom">
            <h4 class="fw-bold mb-1">EduManage Institute</h4>
            <div class="text-muted small">Official Fee Receipt</div>
        </div>

        {{-- Receipt Meta --}}
        <div class="row mb-3">
            <div class="col-6">
                <div class="text-muted small">Receipt Number</div>
                <div class="fw-bold font-monospace">{{ $payment->receipt_number }}</div>
            </div>
            <div class="col-6 text-end">
                <div class="text-muted small">Date</div>
                <div class="fw-semibold">{{ $payment->payment_date ? $payment->payment_date->format('d M Y') : now()->format('d M Y') }}</div>
            </div>
        </div>

        {{-- Student Details --}}
        <div class="bg-light rounded p-3 mb-3">
            <div class="fw-semibold mb-2" style="font-size:.85rem;color:#6c757d;text-transform:uppercase;letter-spacing:.05em">Student Details</div>
            <div class="row">
                <div class="col-6">
                    <div class="text-muted small">Name</div>
                    <div class="fw-bold">{{ $payment->student->user->name ?? '—' }}</div>
                </div>
                <div class="col-6">
                    <div class="text-muted small">Enrollment No.</div>
                    <div class="fw-semibold font-monospace">{{ $payment->student->enrollment_number ?? '—' }}</div>
                </div>
                <div class="col-12 mt-2">
                    <div class="text-muted small">Course</div>
                    <div class="fw-semibold">{{ $payment->student->course->name ?? ($payment->feeStructure->course->name ?? '—') }}</div>
                </div>
            </div>
        </div>

        {{-- Payment Details --}}
        <div class="mb-3">
            <div class="fw-semibold mb-2" style="font-size:.85rem;color:#6c757d;text-transform:uppercase;letter-spacing:.05em">Payment Details</div>
            <table class="table table-sm table-bordered mb-0">
                <tbody>
                    <tr>
                        <td class="text-muted" style="width:40%">Fee Type</td>
                        <td class="fw-semibold">{{ $payment->feeStructure->fee_type ?? '—' }}</td>
                    </tr>
                    @if($payment->feeStructure && $payment->feeStructure->semester_number)
                    <tr>
                        <td class="text-muted">Semester</td>
                        <td>{{ $payment->feeStructure->semester_number }}</td>
                    </tr>
                    @endif
                    @if($payment->feeStructure && $payment->feeStructure->academicYear)
                    <tr>
                        <td class="text-muted">Academic Year</td>
                        <td>{{ $payment->feeStructure->academicYear->name }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="text-muted">Payment Method</td>
                        <td>{{ ucfirst($payment->payment_method) }}</td>
                    </tr>
                    @if($payment->transaction_id)
                    <tr>
                        <td class="text-muted">Transaction Ref.</td>
                        <td class="font-monospace">{{ $payment->transaction_id }}</td>
                    </tr>
                    @endif
                    @if($payment->remarks)
                    <tr>
                        <td class="text-muted">Remarks</td>
                        <td>{{ $payment->remarks }}</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>

        {{-- Amount --}}
        <div class="d-flex align-items-center justify-content-between p-3 rounded mb-4"
             style="background:linear-gradient(135deg,#059669,#047857);color:#fff">
            <div>
                <div class="opacity-75 small">Amount Paid</div>
                <div class="fw-bold" style="font-size:1.6rem">₹{{ number_format($payment->amount_paid, 2) }}</div>
                <div class="opacity-75 small">Rupees {{ number_format($payment->amount_paid, 2) }} only</div>
            </div>
            <div>
                <span class="badge-paid px-3 py-2" style="font-size:.9rem">PAID</span>
            </div>
        </div>

        {{-- Footer --}}
        <div class="text-center text-muted border-top pt-3" style="font-size:.78rem">
            This is a computer-generated receipt and does not require a physical signature.<br>
            Generated on {{ now()->format('d M Y, H:i') }}
        </div>
    </div>
</div>
@endsection
