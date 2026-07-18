@extends('layouts.admin')
@section('title', 'Accounts - Outstanding')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h4 class="mb-0"><i class="bi bi-exclamation-triangle me-2 text-danger"></i>Outstanding Fees</h4>
    <a href="{{ route('accounts.export-outstanding', request()->query()) }}" class="btn btn-sm btn-outline-success">
        <i class="bi bi-download me-1"></i>Export Current View
    </a>
</div>

<div class="alert alert-info border-0 shadow-sm py-2 mb-3">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
        <div>
            <div class="fw-semibold">Outstanding follow-up workflow</div>
            <div class="small text-muted">Use this page to move from active fee demands to student follow-up, demand letters, and current-view exports.</div>
            <div class="small text-muted mt-1">
                <span class="badge text-bg-light me-1">Owner: Accounts office</span>
                <span class="badge text-bg-light">Source: active pending, partially paid, and overdue fee demands</span>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-1">
            <span class="badge text-bg-light">1. Review active dues</span>
            <span class="badge text-bg-light">2. Prioritize oldest due date</span>
            <span class="badge text-bg-light">3. Generate demand letter</span>
            <span class="badge text-bg-light">4. Export current view</span>
        </div>
    </div>
</div>

@isset($overdueDemands)
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <div class="fw-semibold">Overdue Fee Demands</div>
            <div class="small text-muted">Filtered Source List ({{ $overdueDemands->total() }})</div>
            <div class="small text-muted">Visible filter summary: Mode: overdue demands</div>
        </div>
        <span class="badge bg-danger-subtle text-danger">mode=overdue_demands</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th scope="col">Student</th>
                    <th scope="col">Program</th>
                    <th scope="col">Term</th>
                    <th scope="col">Owner / Source</th>
                    <th scope="col">Amount</th>
                    <th scope="col">Penalty</th>
                    <th scope="col">Due Date</th>
                    <th scope="col">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($overdueDemands as $demand)
                    <tr>
                        <td>{{ $demand->student?->user?->name ?? 'Student not linked' }}<br><span class="text-muted small">{{ $demand->student?->enrollment_number ?? 'Enrollment not issued' }}</span></td>
                        <td>{{ $demand->student?->program?->name ?? 'Program not assigned' }}</td>
                        <td>{{ $demand->term?->name ?? 'Term not assigned' }}</td>
                        <td>
                            <div class="small text-muted">Owner: Accounts office</div>
                            <div class="small text-muted">Source: Fee demand</div>
                        </td>
                        <td class="text-danger fw-semibold">Rs. {{ number_format($demand->final_amount, 2) }}</td>
                        <td>Rs. {{ number_format($demand->penalty_amount ?? 0, 2) }}</td>
                        <td>{{ $demand->due_date ? $demand->due_date->format('d M Y') : 'Due date not set' }}</td>
                        <td><span class="badge bg-danger">{{ str_replace('_', ' ', $demand->status) }}</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            No overdue fee demands match this source list. Check whether active demands exist, due dates are published, or filters are narrowing the queue.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-transparent">{{ $overdueDemands->links() }}</div>
</div>
@else
@forelse($programs as $prog)
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-transparent">
        <div class="fw-semibold">{{ $prog->name }}</div>
        <div class="small text-muted">Visible filter summary: Active outstanding demands grouped by program.</div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Student</th>
                        <th scope="col">Owner / Source</th>
                        <th scope="col">Amount Due</th>
                        <th scope="col">Oldest Due Date</th>
                        <th scope="col">Open Demands</th>
                        <th scope="col">Last Payment</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($prog->outstanding_students as $s)
                    <tr>
                        <td>{{ $s->user?->name ?? 'Student not linked' }} <br><span class="text-muted small">{{ $s->enrollment_number ?? 'Enrollment not issued' }}</span></td>
                        <td>
                            <div class="small text-muted">Owner: Accounts office</div>
                            <div class="small text-muted">Source: Active fee demands</div>
                        </td>
                        <td class="text-danger fw-bold">Rs. {{ number_format($s->amount_due, 2) }}</td>
                        <td>{{ $s->oldest_due_date ? $s->oldest_due_date->format('d M Y') : 'Due date not set' }}</td>
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
    <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>No outstanding fees from active pending, partially paid, or overdue demands. If a balance is expected, confirm fee demands were generated for active students.</div>
@endforelse
@endisset
@endsection
