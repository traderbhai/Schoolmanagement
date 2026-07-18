@extends('layouts.admin')

@section('title', 'Refund Requests')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0 fw-bold">Refund Requests</h2>
        <small class="text-muted">Manage applicant refund requests</small>
    </div>
    <div class="text-muted small fst-italic">
        <i class="bi bi-info-circle me-1"></i>To create a refund, open an applicant's profile and use the Refund option.
    </div>
</div>

{{-- KPI Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="rounded-3 bg-warning bg-opacity-10 p-3">
                    <i class="bi bi-hourglass-split fs-4 text-warning"></i>
                </div>
                <div>
                    <div class="fs-2 fw-bold text-warning">{{ $counts['pending'] }}</div>
                    <div class="small text-muted">Pending Review</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="rounded-3 bg-success bg-opacity-10 p-3">
                    <i class="bi bi-check-circle fs-4 text-success"></i>
                </div>
                <div>
                    <div class="fs-2 fw-bold text-success">{{ $counts['approved'] }}</div>
                    <div class="small text-muted">Approved</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="rounded-3 bg-primary bg-opacity-10 p-3">
                    <i class="bi bi-send-check fs-4 text-primary"></i>
                </div>
                <div>
                    <div class="fs-2 fw-bold text-primary">{{ $counts['processed'] }}</div>
                    <div class="small text-muted">Processed</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Filter Tabs --}}
<div class="card border-0 shadow-sm mb-0">
    <div class="card-header bg-white border-bottom py-0">
        <ul class="nav nav-tabs border-0">
            <li class="nav-item">
                <a class="nav-link {{ is_null($status) ? 'active' : '' }}"
                   href="{{ route('admission.refunds.index') }}">All refunds</a>
            </li>
            @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'processed' => 'Processed'] as $val => $label)
            <li class="nav-item">
                <a class="nav-link {{ $status === $val ? 'active' : '' }}"
                   href="{{ route('admission.refunds.index', ['status' => $val]) }}">{{ $label }}</a>
            </li>
            @endforeach
        </ul>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mx-3 mt-3 mb-0" role="alert">
        <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
        <button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th scope="col" class="ps-4">Applicant</th>
                    <th scope="col">Application No.</th>
                    <th scope="col">Program</th>
                    <th scope="col" class="text-end">Requested (Rs.)</th>
                    <th scope="col">Reason</th>
                    <th scope="col">Status</th>
                    <th scope="col">Submitted</th>
                    <th scope="col" class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($refunds as $refund)
                <tr>
                    <td class="ps-4">
                        <div class="fw-semibold">{{ $refund->applicant->user->name ?? 'Applicant name missing' }}</div>
                        <div class="small text-muted">{{ $refund->applicant->user->email ?? 'Email not provided' }}</div>
                    </td>
                    <td>
                        <span class="font-monospace small">{{ $refund->applicant->application_number ?? 'Application number missing' }}</span>
                    </td>
                    <td>{{ $refund->applicant->program->name ?? 'Program not assigned' }}</td>
                    <td class="text-end fw-semibold">Rs. {{ number_format($refund->requested_amount, 2) }}</td>
                    <td>{{ $refund->reason_label }}</td>
                    <td>
                        <span class="{{ $refund->status_badge }}">{{ ucfirst($refund->status) }}</span>
                    </td>
                    <td>
                        <span title="{{ $refund->created_at->format('d M Y, h:i A') }}">
                            {{ $refund->created_at->format('d M Y') }}
                        </span>
                    </td>
                    <td class="text-center">
                        <a href="{{ route('admission.refunds.show', $refund) }}"
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye me-1"></i>View
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-5 px-3">
                        <i class="bi bi-inbox fs-2 d-block mb-2 text-muted"></i>
                        <h6 class="fw-semibold mb-1">No refund requests are visible</h6>
                        <p class="text-muted small mb-3">
                            Refund requests appear here only when they match your Admission role scope{{ $status ? ', the selected ' . $status . ' status filter' : '' }},
                            and an applicant has a verified refundable payment or an approved refund workflow.
                        </p>
                        <div class="d-flex justify-content-center gap-2 flex-wrap">
                            <a href="{{ route('admission.refunds.index') }}" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
                            <a href="{{ route('admission.applicants.index') }}" class="btn btn-sm btn-outline-primary">Open Applicants</a>
                            <a href="{{ route('admission.payments.queue') }}" class="btn btn-sm btn-warning">Payment Queue</a>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($refunds->hasPages())
    <div class="card-footer bg-white border-top py-3">
        {{ $refunds->appends(['status' => $status])->links() }}
    </div>
    @endif
</div>
@endsection
