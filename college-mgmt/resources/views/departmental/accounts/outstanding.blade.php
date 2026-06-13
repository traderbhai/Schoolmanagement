@extends('layouts.admin')
@section('title', 'Accounts - Outstanding')

@section('content')
<h4 class="mb-4"><i class="bi bi-exclamation-triangle me-2 text-danger"></i>Outstanding Fees</h4>

@forelse($programs as $prog)
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-transparent fw-semibold">{{ $prog->name }}</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Student</th>
                        <th>Amount Due</th>
                        <th>Oldest Due Date</th>
                        <th>Open Demands</th>
                        <th>Last Payment</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($prog->outstanding_students as $s)
                    <tr>
                        <td>{{ $s->user?->name ?? '-' }} <br><span class="text-muted small">{{ $s->enrollment_number }}</span></td>
                        <td class="text-danger fw-bold">Rs. {{ number_format($s->amount_due, 2) }}</td>
                        <td>{{ $s->oldest_due_date ? $s->oldest_due_date->format('d M Y') : '-' }}</td>
                        <td>
                            <span class="badge bg-secondary">{{ $s->open_demand_count }} open</span>
                            @if($s->overdue_demand_count > 0)
                                <span class="badge bg-danger ms-1">{{ $s->overdue_demand_count }} overdue</span>
                            @endif
                        </td>
                        <td>{{ $s->last_payment_date ? $s->last_payment_date->format('d M Y') : 'Never' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@empty
    <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>No outstanding fees.</div>
@endforelse
@endsection
