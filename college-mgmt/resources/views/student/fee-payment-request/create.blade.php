@extends('layouts.student')
@section('title', 'Submit Payment Proof')
@section('content')
<div class="container py-4" style="max-width:600px">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('student.fee-payment.index') }}">Payment Submissions</a></li>
            <li class="breadcrumb-item active">Submit Proof</li>
        </ol>
    </nav>
    <h4 class="fw-semibold mb-4">Submit Fee Payment Proof</h4>
    @if($actionBlockedReason)
        <div class="alert alert-warning">
            <i class="bi bi-lock me-1"></i>{{ $actionBlockedReason }}
        </div>
    @endif
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('student.fee-payment.store') }}" enctype="multipart/form-data">
                @csrf
                @if($demands->isNotEmpty())
                <div class="mb-3">
                    <label class="form-label">Against Fee Demand <span class="text-muted">(optional)</span></label>
                    <select aria-label="Fee Demand" name="fee_demand_id" class="form-select" @disabled($actionBlockedReason)>
                        <option value="">Select demand (optional)</option>
                        @foreach($demands as $d)
                        <option value="{{ $d->id }}" @selected(old('fee_demand_id') == $d->id)>
                            {{ $d->term?->name ?? 'Fee Demand' }} - INR {{ number_format((float) $d->final_amount + (float) ($d->penalty_amount ?? 0), 0) }}
                        </option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="mb-3">
                    <label class="form-label">Amount Paid (INR)</label>
                    <input aria-label="Amount" type="number" name="amount" value="{{ old('amount') }}" step="0.01" class="form-control @error('amount') is-invalid @enderror" @disabled($actionBlockedReason)>
                    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">Payment Method</label>
                    <select aria-label="Payment Method" name="payment_method" class="form-select @error('payment_method') is-invalid @enderror" @disabled($actionBlockedReason)>
                        <option value="">Select payment method</option>
                        @foreach(['online'=>'Online (UPI/Net Banking)','neft'=>'NEFT','rtgs'=>'RTGS','dd'=>'Demand Draft','cash'=>'Cash'] as $v=>$l)
                        <option value="{{ $v }}" @selected(old('payment_method') == $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                    @error('payment_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Bank Name <span class="text-muted">(optional)</span></label>
                        <input aria-label="Bank name" type="text" name="bank_name" value="{{ old('bank_name') }}" class="form-control" placeholder="e.g. SBI, HDFC" @disabled($actionBlockedReason)>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Transaction / UTR Reference</label>
                        <input aria-label="UTR / Transaction ID" type="text" name="transaction_ref" value="{{ old('transaction_ref') }}" class="form-control @error('transaction_ref') is-invalid @enderror" placeholder="UTR / Transaction ID" @disabled($actionBlockedReason)>
                        @error('transaction_ref')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Upload Payment Proof <span class="text-muted">(screenshot/receipt, max 5MB)</span></label>
                    <input aria-label="Proof" type="file" name="proof" accept="image/*,.pdf" class="form-control @error('proof') is-invalid @enderror" @disabled($actionBlockedReason)>
                    @error('proof')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary" @disabled($actionBlockedReason)>Submit for Verification</button>
                    <a href="{{ route('student.fee-payment.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
