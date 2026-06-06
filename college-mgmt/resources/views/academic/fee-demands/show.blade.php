@extends('layouts.admin')

@section('title', 'Fee Demand Details')
@section('page-title', 'Fee Demand Details')

@section('content')
<div class="container-fluid py-4">
    <div class="card">
        <div class="card-body">
            <p><strong>Student:</strong> {{ $feeDemand->student->enrollment_number ?? 'N/A' }}</p>
            <p><strong>Term:</strong> {{ $feeDemand->term->name ?? 'N/A' }}</p>
            <p><strong>Total Amount:</strong> ₹{{ number_format($feeDemand->total_amount) }}</p>
            <p><strong>Scholarship Deduction:</strong> ₹{{ number_format($feeDemand->scholarship_deduction) }}</p>
            <p><strong>Final Amount:</strong> ₹{{ number_format($feeDemand->final_amount) }}</p>
            <p><strong>Due Date:</strong> {{ $feeDemand->due_date->format('d M Y') }}</p>
            <p><strong>Status:</strong> {{ ucfirst(str_replace('_',' ',$feeDemand->status)) }}</p>
            @if(!$feeDemand->isPaid())
                <form action="{{ route('academic.fee-demands.mark-paid', $feeDemand) }}" method="POST" class="d-inline">
                    @csrf
                    <button class="btn btn-success">Mark as Paid</button>
                </form>
            @endif
            <a href="{{ route('academic.fee-demands.edit', $feeDemand) }}" class="btn btn-warning">Edit</a>
        </div>
    </div>
</div>
@endsection
