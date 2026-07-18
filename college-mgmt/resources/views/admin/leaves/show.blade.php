@extends('layouts.admin')
@section('title', 'Leave Application')
@section('page-title', 'Leave Application')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.leaves.index') }}">Leaves</a></li>
    <li class="breadcrumb-item active">Application #{{ $leave->id }}</li>
@endsection

@section('content')

@php
    $requesterName = $leave->teacher?->user?->name ?? $leave->student?->user?->name ?? '-';
    $requesterType = $leave->teacher_id ? 'Teacher' : ($leave->student_id ? 'Student' : '-');
@endphp

<div class="d-flex justify-content-end mb-3">
    <a href="{{ route('admin.leaves.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><strong>Leave Details</strong></div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0" style="font-size:.88rem">
                    <tr><td class="text-muted" style="width:40%">Requester</td><td class="fw-semibold">{{ $requesterName }}</td></tr>
                    <tr><td class="text-muted">Requester Type</td><td>{{ $requesterType }}</td></tr>
                    <tr><td class="text-muted">Leave Type</td><td>{{ ucfirst($leave->leave_type) }}</td></tr>
                    <tr><td class="text-muted">From Date</td><td>{{ $leave->from_date->format('d M Y') }}</td></tr>
                    <tr><td class="text-muted">To Date</td><td>{{ $leave->to_date->format('d M Y') }}</td></tr>
                    <tr><td class="text-muted">Days</td><td>{{ $leave->days }}</td></tr>
                    <tr><td class="text-muted">Status</td><td>
                        @if($leave->status === 'pending')
                            <span class="badge badge-pending">Pending</span>
                        @elseif($leave->status === 'approved')
                            <span class="badge badge-active">Approved</span>
                        @else
                            <span class="badge badge-danger">Rejected</span>
                        @endif
                    </td></tr>
                    @if($leave->approver)
                    <tr><td class="text-muted">Actioned By</td><td>{{ $leave->approver->name }}</td></tr>
                    <tr><td class="text-muted">Actioned At</td><td>{{ $leave->approved_at?->format('d M Y H:i') }}</td></tr>
                    @endif
                </table>
                <div class="mt-3">
                    <div class="text-muted mb-1" style="font-size:.82rem">Reason</div>
                    <div class="p-2 rounded" style="background:var(--clr-surface-alt);font-size:.88rem">{{ $leave->reason }}</div>
                </div>
                @if($leave->admin_remarks)
                <div class="mt-3">
                    <div class="text-muted mb-1" style="font-size:.82rem">Admin Remarks</div>
                    <div class="p-2 rounded" style="background:var(--clr-surface-alt);font-size:.88rem">{{ $leave->admin_remarks }}</div>
                </div>
                @endif
            </div>
        </div>
    </div>

    @if($leave->status === 'pending')
    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header text-success fw-semibold"><i class="bi bi-check-circle me-1"></i>Approve Leave</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.leaves.approve', $leave) }}">
                    @csrf @method('PATCH')
                    <div class="mb-3">
                        <label class="form-label form-label-sm">Admin Remarks (optional)</label>
                        <textarea aria-label="Leave approval remarks" name="admin_remarks" class="form-control form-control-sm" rows="3" placeholder="Any remarks..."></textarea>
                    </div>
                    <button class="btn btn-success btn-sm w-100"><i class="bi bi-check-lg me-1"></i>Approve leave</button>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header text-danger fw-semibold"><i class="bi bi-x-circle me-1"></i>Reject Leave</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.leaves.reject', $leave) }}">
                    @csrf @method('PATCH')
                    <div class="mb-3">
                        <label class="form-label form-label-sm">Reason for Rejection</label>
                        <textarea aria-label="Leave rejection reason" name="admin_remarks" class="form-control form-control-sm" rows="3" placeholder="Reason for rejection..."></textarea>
                    </div>
                    <button class="btn btn-danger btn-sm w-100"><i class="bi bi-x-lg me-1"></i>Reject leave</button>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>

@endsection
