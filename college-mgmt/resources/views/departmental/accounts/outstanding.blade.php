@extends('layouts.admin')
@section('title', 'Accounts - Outstanding')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h4 class="mb-0"><i class="bi bi-exclamation-triangle me-2 text-danger"></i>Outstanding Fees</h4>
    <a href="{{ route('accounts.export-outstanding', request()->query()) }}" class="btn btn-sm btn-outline-success">
        <i class="bi bi-download me-1"></i>Export Current View
    </a>
</div>

@isset($overdueDemands)
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <div class="fw-semibold">Overdue Fee Demands</div>
            <div class="small text-muted">Filtered Source List ({{ $overdueDemands->total() }})</div>
        </div>
        <span class="badge bg-danger-subtle text-danger">mode=overdue_demands</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Student</th>
                    <th>Program</th>
                    <th>Term</th>
                    <th>Amount</th>
                    <th>Penalty</th>
                    <th>Due Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($overdueDemands as $demand)
                    <tr>
                        <td>{{ $demand->student?->user?->name ?? '-' }}<br><span class="text-muted small">{{ $demand->student?->enrollment_number }}</span></td>
                        <td>{{ $demand->student?->program?->name ?? '-' }}</td>
                        <td>{{ $demand->term?->name ?? '-' }}</td>
                        <td class="text-danger fw-semibold">Rs. {{ number_format($demand->final_amount, 2) }}</td>
                        <td>Rs. {{ number_format($demand->penalty_amount ?? 0, 2) }}</td>
                        <td>{{ $demand->due_date ? $demand->due_date->format('d M Y') : '-' }}</td>
                        <td><span class="badge bg-danger">{{ str_replace('_', ' ', $demand->status) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-3">No overdue fee demands.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-transparent">{{ $overdueDemands->links() }}</div>
</div>
@else
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
@endisset
@endsection
