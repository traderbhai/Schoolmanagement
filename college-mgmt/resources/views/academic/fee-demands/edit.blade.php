@extends('layouts.admin')

@section('title', 'Edit Fee Demand')
@section('page-title', 'Edit Fee Demand')

@section('content')
<div class="container-fluid py-4">
    <div class="card">
        <div class="card-body">
            <form action="{{ route('academic.fee-demands.update', $feeDemand) }}" method="POST">
                @csrf @method('PUT')
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Student</label>
                        <input aria-label="Student enrollment number" type="text" class="form-control" value="{{ $feeDemand->student->enrollment_number }}" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Term</label>
                        <input aria-label="Fee demand term" type="text" class="form-control" value="{{ $feeDemand->term->name }}" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Total Amount (₹)</label>
                        <input aria-label="Total Amount" type="number" name="total_amount" class="form-control" step="0.01" min="0" value="{{ old('total_amount', $feeDemand->total_amount) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Scholarship Deduction (₹)</label>
                        <input aria-label="Scholarship Deduction" type="number" name="scholarship_deduction" class="form-control" step="0.01" min="0" value="{{ old('scholarship_deduction', $feeDemand->scholarship_deduction) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Due Date</label>
                        <input aria-label="Due Date" type="date" name="due_date" class="form-control" value="{{ old('due_date', $feeDemand->due_date?->format('Y-m-d')) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select aria-label="Status" name="status" class="form-select" required>
                            @foreach(['pending','partially_paid','fully_paid','overdue'] as $s)
                                <option value="{{ $s }}" {{ old('status',$feeDemand->status)==$s?'selected':'' }}>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Update Fee Demand</button>
                    <a href="{{ route('academic.fee-demands.show', $feeDemand) }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
