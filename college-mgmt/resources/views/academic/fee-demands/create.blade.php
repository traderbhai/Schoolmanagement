@extends('layouts.admin')

@section('title', 'Create Fee Demand')
@section('page-title', 'Create Fee Demand')

@section('content')
<div class="container-fluid py-4">
    <div class="card">
        <div class="card-body">
            <form action="{{ route('academic.fee-demands.store') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Student</label>
                        <select aria-label="Student" name="student_id" class="form-select" required>
                            <option value="">Select Student</option>
                            @foreach($students as $student)
                                <option value="{{ $student->id }}" {{ old('student_id')==$student->id?'selected':'' }}>
                                    {{ $student->enrollment_number ?? 'Enrollment pending' }} - {{ $student->user?->name ?? 'Student name missing' }}
                                    @if($student->status !== 'active') ({{ str($student->status)->headline() }}) @endif
                                </option>
                            @endforeach
                        </select>
                        @error('student_id')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Term</label>
                        <select aria-label="Term" name="term_id" class="form-select" required>
                            <option value="">Select Term</option>
                            @foreach($terms as $term)
                                <option value="{{ $term->id }}" {{ old('term_id')==$term->id?'selected':'' }}>{{ $term->name }}</option>
                            @endforeach
                        </select>
                        @error('term_id')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Total Amount (₹)</label>
                        <input aria-label="Total Amount" type="number" name="total_amount" class="form-control" step="0.01" min="0" value="{{ old('total_amount') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Scholarship Deduction (₹)</label>
                        <input aria-label="Scholarship Deduction" type="number" name="scholarship_deduction" class="form-control" step="0.01" min="0" value="{{ old('scholarship_deduction', 0) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Due Date</label>
                        <input aria-label="Due Date" type="date" name="due_date" class="form-control" value="{{ old('due_date') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select aria-label="Status" name="status" class="form-select" required>
                            <option value="pending">Pending</option>
                            <option value="partially_paid">Partially Paid</option>
                            <option value="fully_paid">Fully Paid</option>
                            <option value="overdue">Overdue</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Create Fee Demand</button>
                    <a href="{{ route('academic.fee-demands.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
