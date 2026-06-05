@extends('layouts.admin')
@section('title', 'Collect Fee Payment')
@section('page-title', 'Collect Fee Payment')
@section('content')

<div class="card" style="max-width:720px">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-cash me-2 text-success"></i>Record Fee Payment</span>
        <a href="{{ route('admin.fees.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.fees.payment') }}">
            @csrf
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Student <span class="text-danger">*</span></label>
                    <select name="student_id" class="form-select @error('student_id') is-invalid @enderror" required>
                        <option value="">Select student…</option>
                        @foreach($students as $s)<option value="{{ $s->id }}" @selected(old('student_id')==$s->id)>{{ $s->user->name }} ({{ $s->enrollment_number }})</option>@endforeach
                    </select>
                    <div class="form-text">Student making the payment.</div>
                    @error('student_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Fee Structure <span class="text-danger">*</span></label>
                    <select name="fee_structure_id" class="form-select @error('fee_structure_id') is-invalid @enderror" required>
                        <option value="">Select fee type…</option>
                        @foreach($structures as $f)<option value="{{ $f->id }}" @selected(old('fee_structure_id')==$f->id)>{{ $f->course->code }} – {{ $f->fee_type }} (₹{{ number_format($f->amount,2) }})</option>@endforeach
                    </select>
                    <div class="form-text">Fee category being paid.</div>
                    @error('fee_structure_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Amount Paid (₹) <span class="text-danger">*</span></label>
                    <input type="number" name="amount_paid" class="form-control @error('amount_paid') is-invalid @enderror" value="{{ old('amount_paid') }}" min="0" step="0.01" required>
                    <div class="form-text">Actual amount received.</div>
                    @error('amount_paid')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                    <input type="date" name="payment_date" class="form-control @error('payment_date') is-invalid @enderror" value="{{ old('payment_date', date('Y-m-d')) }}" required>
                    @error('payment_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                    <select name="payment_method" class="form-select @error('payment_method') is-invalid @enderror" required>
                        @foreach(['cash'=>'Cash','online'=>'Online','cheque'=>'Cheque','dd'=>'Demand Draft'] as $v=>$l)
                            <option value="{{ $v }}" @selected(old('payment_method')==$v)>{{ $l }}</option>
                        @endforeach
                    </select>
                    @error('payment_method')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Transaction ID</label>
                    <input type="text" name="transaction_id" class="form-control" value="{{ old('transaction_id') }}" placeholder="For online payments">
                    <div class="form-text">Reference/transaction number (optional).</div>
                </div>
                <div class="col-12">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" class="form-control" rows="2">{{ old('remarks') }}</textarea>
                    <div class="form-text">Any additional notes about this payment.</div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4 pt-2 border-top">
                <button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-1"></i>Record Payment</button>
                <a href="{{ route('admin.fees.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
