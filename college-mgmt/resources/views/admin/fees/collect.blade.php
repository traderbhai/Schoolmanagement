@extends('layouts.admin')
@section('title','Collect Fee Payment')
@section('page-title','Collect Fee Payment')
@section('content')
<div class="card" style="max-width:680px">
    <div class="card-header">Record Fee Payment</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.fees.payment') }}">
            @csrf
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Student *</label>
                    <select name="student_id" class="form-select @error('student_id') is-invalid @enderror" required>
                        <option value="">Select student</option>
                        @foreach($students as $s)<option value="{{ $s->id }}" @selected(old('student_id')==$s->id)>{{ $s->user->name }} ({{ $s->enrollment_number }})</option>@endforeach
                    </select>
                    @error('student_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Fee Structure *</label>
                    <select name="fee_structure_id" class="form-select @error('fee_structure_id') is-invalid @enderror" required>
                        <option value="">Select fee type</option>
                        @foreach($structures as $f)<option value="{{ $f->id }}" @selected(old('fee_structure_id')==$f->id)>{{ $f->course->code }} – {{ $f->fee_type }} (₹{{ number_format($f->amount,2) }})</option>@endforeach
                    </select>
                    @error('fee_structure_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-4"><label class="form-label fw-semibold">Amount Paid (₹) *</label><input type="number" name="amount_paid" class="form-control" value="{{ old('amount_paid') }}" min="0" step="0.01" required></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Payment Date *</label><input type="date" name="payment_date" class="form-control" value="{{ old('payment_date', date('Y-m-d')) }}" required></div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Payment Method *</label>
                    <select name="payment_method" class="form-select" required>
                        @foreach(['cash'=>'Cash','online'=>'Online','cheque'=>'Cheque','dd'=>'DD'] as $v=>$l)<option value="{{ $v }}" @selected(old('payment_method')==$v)>{{ $l }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="mb-3"><label class="form-label fw-semibold">Transaction ID</label><input type="text" name="transaction_id" class="form-control" value="{{ old('transaction_id') }}" placeholder="For online payments"></div>
            <div class="mb-3"><label class="form-label fw-semibold">Remarks</label><textarea name="remarks" class="form-control" rows="2">{{ old('remarks') }}</textarea></div>
            <div class="d-flex gap-2"><button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-1"></i>Record Payment</button><a href="{{ route('admin.fees.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
        </form>
    </div>
</div>
@endsection
