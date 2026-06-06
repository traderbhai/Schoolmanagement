@extends('layouts.admin')

@section('title', 'Fee Demands')
@section('page-title', 'Fee Demands')

@section('content')
<div class="container-fluid py-4">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <div class="mb-3">
        <a href="{{ route('academic.fee-demands.create') }}" class="btn btn-primary">Add Fee Demand</a>
    </div>
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr><th>Student</th><th>Term</th><th>Total</th><th>Final Amount</th><th>Due Date</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @foreach($feeDemands as $demand)
                    <tr>
                        <td>{{ $demand->student->enrollment_number ?? 'N/A' }}</td>
                        <td>{{ $demand->term->name ?? 'N/A' }}</td>
                        <td>₹{{ number_format($demand->total_amount) }}</td>
                        <td>₹{{ number_format($demand->final_amount) }}</td>
                        <td>{{ $demand->due_date->format('d M Y') }}</td>
                        <td><span class="badge bg-{{ $demand->status === 'fully_paid' ? 'success' : ($demand->isOverdue() ? 'danger' : 'warning') }}">{{ ucfirst(str_replace('_',' ',$demand->status)) }}</span></td>
                        <td><a href="{{ route('academic.fee-demands.show', $demand) }}" class="btn btn-sm btn-primary">View</a></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $feeDemands->links() }}
        </div>
    </div>
</div>
@endsection
