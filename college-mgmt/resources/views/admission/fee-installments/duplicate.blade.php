@extends('layouts.admin')
@section('title', 'Duplicate Installments')
@section('page-title', 'Duplicate Fee Installments')

@section('content')
<div class="container-fluid py-4" style="max-width:600px">
    <a href="{{ route('admission.fee-installments.index', $program) }}" class="text-muted small mb-3 d-inline-block"><i class="bi bi-arrow-left"></i> Back to Installments</a>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold">Duplicate Installments — {{ $program->name }}</div>
        <div class="card-body">
            <p class="text-muted small">Copy all installments from one batch to another, with an optional date offset.</p>
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif
            <form action="{{ route('admission.fee-installments.duplicate', $program) }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Copy From Batch <span class="text-danger">*</span></label>
                    <select aria-label="From Batch" name="from_batch_id" class="form-select" required>
                        <option value="">Select source batch</option>
                        @foreach($batches as $batch)
                            <option value="{{ $batch->id }}">{{ $batch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Copy To Batch <span class="text-danger">*</span></label>
                    <select aria-label="To Batch" name="to_batch_id" class="form-select" required>
                        <option value="">Select target batch</option>
                        @foreach($batches as $batch)
                            <option value="{{ $batch->id }}">{{ $batch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Shift Due Dates by (days)</label>
                    <input aria-label="Installment due-date offset in days" type="number" name="days_offset" class="form-control" value="365" placeholder="e.g. 365 for next year">
                    <div class="form-text">Due dates will be moved forward by this many days. Leave 0 to copy as-is.</div>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Duplicate Installments</button>
                    <a href="{{ route('admission.fee-installments.index', $program) }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
