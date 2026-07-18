@extends('layouts.admin')
@section('title', 'Fee Report')
@section('page-title', 'Fee Report')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.fees.index') }}">Fee Management</a></li>
    <li class="breadcrumb-item active">Fee Report</li>
@endsection

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h5 class="mb-0 fw-bold">Fee Report</h5>
        <div class="text-muted" style="font-size:.82rem">View and filter all fee payment records</div>
    </div>
    <a href="{{ route('admin.fees.collect') }}" class="btn btn-success">
        <i class="bi bi-cash me-1"></i>Collect Payment
    </a>
</div>

{{-- Filter Card --}}
<div class="card mb-4">
    <div class="card-header fw-semibold"><i class="bi bi-funnel me-1"></i>Filters</div>
    <div class="card-body">
        <form method="GET" action="{{ route('admin.fees.report') }}" class="row g-3">
            <div class="col-md-3">
                <label class="form-label small">Course</label>
                <select aria-label="Course" name="course_id" class="form-select form-select-sm">
                    <option value="">All Courses</option>
                    @foreach($courses as $course)
                        <option value="{{ $course->id }}" @selected(request('course_id') == $course->id)>{{ $course->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Academic Year</label>
                <select aria-label="Academic Year" name="academic_year_id" class="form-select form-select-sm">
                    <option value="">All Years</option>
                    @foreach($academicYears as $yr)
                        <option value="{{ $yr->id }}" @selected(request('academic_year_id') == $yr->id)>{{ $yr->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Status</label>
                <select aria-label="Status" name="status" class="form-select form-select-sm">
                    <option value="all" @selected(!request('status') || request('status') === 'all')>All</option>
                    <option value="paid" @selected(request('status') === 'paid')>Paid</option>
                    <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Date From</label>
                <input aria-label="Date From" type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Date To</label>
                <input aria-label="Date To" type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label small">Search Student</label>
                <input aria-label="Student name filter" type="text" name="student_name" class="form-control form-control-sm" placeholder="Student name…" value="{{ request('student_name') }}">
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Apply Filters</button>
                <a href="{{ route('admin.fees.report') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                <a href="{{ route('admin.fees.export') }}" class="btn btn-sm btn-outline-success"><i class="bi bi-download me-1"></i>Export CSV</a>
            </div>
        </form>
    </div>
</div>

{{-- Summary KPI Cards --}}
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="kpi-card kpi-blue">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="kpi-label">Total Amount</div>
                    <div class="kpi-value mt-1">₹{{ number_format($totalAmount, 2) }}</div>
                    <div class="text-muted" style="font-size:.75rem">In filtered results</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-cash-stack"></i></div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="kpi-card kpi-green">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="kpi-label">Paid</div>
                    <div class="kpi-value mt-1">{{ $paidCount }}</div>
                    <div class="text-muted" style="font-size:.75rem">Confirmed payments</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-check-circle-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="kpi-card kpi-amber">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="kpi-label">Pending</div>
                    <div class="kpi-value mt-1">{{ $pendingCount }}</div>
                    <div class="text-muted" style="font-size:.75rem">Awaiting payment</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-hourglass-split"></i></div>
            </div>
        </div>
    </div>
</div>

{{-- Payments Table --}}
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold">Payment Records</span>
        <span class="text-muted small">
            <i class="bi bi-info-circle me-1"></i>CSV export available in Sprint 8
        </span>
    </div>
    <div class="card-body p-0">
        @if($payments->isEmpty())
        <div class="empty-state py-5">
            <div class="empty-icon"><i class="bi bi-inbox"></i></div>
            <div class="mt-2 fw-semibold">No payment records found</div>
            <div class="text-muted small">Try adjusting your filters</div>
        </div>
        @else
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Student</th>
                        <th scope="col">Enrollment</th>
                        <th scope="col">Course</th>
                        <th scope="col">Fee Type</th>
                        <th scope="col" class="text-end">Amount</th>
                        <th scope="col">Date</th>
                        <th scope="col">Method</th>
                        <th scope="col">Status</th>
                        <th aria-label="Actions" scope="col"></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($payments as $p)
                <tr>
                    <td class="fw-semibold">{{ $p->student->user->name ?? '—' }}</td>
                    <td class="font-monospace small">{{ $p->student->enrollment_number ?? '—' }}</td>
                    <td class="small">{{ $p->student->course->name ?? '—' }}</td>
                    <td><span class="badge bg-light text-dark border">{{ $p->feeStructure->fee_type ?? '—' }}</span></td>
                    <td class="text-end fw-semibold">₹{{ number_format($p->amount_paid, 2) }}</td>
                    <td class="small">{{ $p->payment_date ? $p->payment_date->format('d M Y') : '—' }}</td>
                    <td class="small text-muted">{{ $p->payment_method ? ucfirst($p->payment_method) : '—' }}</td>
                    <td>
                        @if($p->status === 'paid')
                            <span class="badge-paid">Paid</span>
                        @elseif($p->status === 'pending')
                            <span class="badge-pending">Pending</span>
                        @else
                            <span class="badge bg-secondary">{{ ucfirst($p->status) }}</span>
                        @endif
                    </td>
                    <td>
                        @if($p->status === 'paid')
                        <a href="{{ route('admin.fees.receipt', $p) }}" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:.75rem">
                            <i class="bi bi-receipt me-1"></i>Receipt
                        </a>
                        @endif
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
    @if($payments->hasPages())
    <div class="card-footer">{{ $payments->links() }}</div>
    @endif
</div>
@endsection
