@extends('layouts.admin')
@section('title', 'Fee Management')
@section('page-title', 'Fee Management')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Fee Management</li>
@endsection

@section('content')

<div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
    <div>
        <h5 class="mb-0 fw-bold">Fee Management</h5>
        <div class="text-muted" style="font-size:.82rem">Manage fee structures and payments</div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.fees.payment-requests.index') }}" class="btn btn-outline-primary">
            <i class="bi bi-upload me-1"></i>Payment Proofs
        </a>
        <a href="{{ route('admin.fees.report') }}" class="btn btn-outline-secondary">
            <i class="bi bi-graph-up me-1"></i>Fee Report
        </a>
        <a href="{{ route('admin.fees.collect') }}" class="btn btn-success">
            <i class="bi bi-cash me-1"></i>Collect Payment
        </a>
        <a href="{{ route('admin.fees.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Add Fee Structure
        </a>
    </div>
</div>

{{-- KPI Cards --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card kpi-blue">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="kpi-label">Total Due</div>
                    <div class="kpi-value mt-1">Rs. {{ number_format($totalDue, 2) }}</div>
                    <div class="text-muted" style="font-size:.75rem">Current academic year</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-receipt-cutoff"></i></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card kpi-green">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="kpi-label">Total Collected</div>
                    <div class="kpi-value mt-1">Rs. {{ number_format($totalCollected, 2) }}</div>
                    <div class="text-muted" style="font-size:.75rem">Confirmed payments</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-check-circle-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card kpi-amber">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="kpi-label">Pending Amount</div>
                    <div class="kpi-value mt-1">Rs. {{ number_format($totalPending, 2) }}</div>
                    <div class="text-muted" style="font-size:.75rem">{{ $overdueStudents }} student(s) overdue</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-exclamation-circle-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card kpi-red">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="kpi-label">Collection Rate</div>
                    <div class="kpi-value mt-1">{{ $collectionRate }}%</div>
                    <div class="progress mt-2" style="height:5px;border-radius:3px;background:rgba(0,0,0,.1)">
                        <div class="progress-bar bg-white" style="width:{{ $collectionRate }}%"></div>
                    </div>
                </div>
                <div class="kpi-icon"><i class="bi bi-graph-up-arrow"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- Left: Fee Structures --}}
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span class="fw-semibold">Fee Structures</span>
                <div class="d-flex gap-2 align-items-center">
                    <form method="GET" action="{{ route('admin.fees.index') }}" class="d-flex gap-2">
                        <select aria-label="Course" name="course_id" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:140px">
                            <option value="">All Courses</option>
                            @foreach($courses as $course)
                                <option value="{{ $course->id }}" @selected(request('course_id') == $course->id)>{{ $course->name }}</option>
                            @endforeach
                        </select>
                        @if(request('course_id'))
                            <a href="{{ route('admin.fees.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                        @endif
                    </form>
                    <a href="{{ route('admin.fees.create') }}" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus me-1"></i>Add
                    </a>
                </div>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Course</th>
                            <th scope="col">Fee Type</th>
                            <th scope="col">Semester</th>
                            <th scope="col" class="text-end">Amount</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($feeStructures as $f)
                    <tr>
                        <td class="text-muted">{{ $feeStructures->firstItem() + $loop->index }}</td>
                        <td class="fw-semibold">{{ $f->course->name ?? '-' }}</td>
                        <td><span class="badge bg-light text-dark border">{{ $f->fee_type }}</span></td>
                        <td>{{ $f->semester_number ? 'Sem '.$f->semester_number : 'All' }}</td>
                        <td class="text-end fw-semibold" style="color:#059669">Rs. {{ number_format($f->amount, 2) }}</td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="{{ route('admin.fees.show', $f) }}" class="btn btn-sm btn-outline-secondary" title="View" aria-label="View fee structure details"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('admin.fees.edit', $f) }}" class="btn btn-sm btn-outline-primary" title="Edit" aria-label="Edit fee structure"><i class="bi bi-pencil"></i></a>
                                <button type="button" class="btn btn-sm btn-outline-danger" title="Delete"
                                    data-bs-toggle="modal" data-bs-target="#deleteModal"
                                    data-action="{{ route('admin.fees.destroy', $f) }}"
                                    data-name="{{ $f->fee_type }} - {{ $f->course->name ?? '' }}"
                                    aria-label="Delete fee structure">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6">
                            <div class="empty-state py-5">
                                <div class="empty-icon"><i class="bi bi-cash-stack"></i></div>
                                <div class="mt-2 fw-semibold">No fee structures defined yet</div>
                                <a href="{{ route('admin.fees.create') }}" class="btn btn-primary btn-sm mt-3">Add Fee Structure</a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($feeStructures->hasPages())
            <div class="card-footer">{{ $feeStructures->links() }}</div>
            @endif
        </div>
    </div>

    {{-- Right: Recent Payments --}}
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span class="fw-semibold">Recent Payments</span>
                <a href="{{ route('admin.fees.report') }}" class="btn btn-sm btn-outline-secondary">View payment report</a>
            </div>
            <div class="card-body p-0">
                @if($recentPayments->isEmpty())
                <div class="empty-state py-4">
                    <div class="empty-icon"><i class="bi bi-inbox"></i></div>
                    <div class="mt-2 text-muted small">No payments recorded yet</div>
                </div>
                @else
                <ul class="list-group list-group-flush">
                    @foreach($recentPayments as $p)
                    <li class="list-group-item px-3 py-2">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-semibold" style="font-size:.88rem">{{ $p->student->user->name ?? '-' }}</div>
                                <div class="text-muted" style="font-size:.75rem">
                                    {{ $p->feeStructure->fee_type ?? '-' }} &bull;
                                    {{ $p->payment_date ? $p->payment_date->format('d M Y') : '-' }}
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-success" style="font-size:.88rem">Rs. {{ number_format($p->amount_paid, 2) }}</div>
                                @if($p->status === 'paid')
                                    <span class="badge-paid" style="font-size:.7rem">Paid</span>
                                @else
                                    <span class="badge-pending" style="font-size:.7rem">Pending</span>
                                @endif
                            </div>
                        </div>
                    </li>
                    @endforeach
                </ul>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
