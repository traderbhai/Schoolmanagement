@extends('layouts.teacher')
@section('title', 'My Leave Applications')
@section('page-title', 'My Leave Applications')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('teacher.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Leave</li>
@endsection

@section('content')

@if(!empty($profileMissing))
    <div class="alert alert-warning">
        <strong>Teacher profile not linked.</strong>
        Your login has the Teacher role, but no teacher profile is attached yet. Contact administration to link your profile before applying for leave.
    </div>
@endif

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <div class="text-muted small">
        Submit planned, medical, duty, or earned leave here. Pending requests can be cancelled before review; approved or rejected requests stay as audit history.
    </div>
    @if($canApplyForLeave)
        <a href="{{ route('teacher.leaves.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Apply for Leave</a>
    @else
        <span class="badge bg-secondary">Active teachers only</span>
    @endif
</div>

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col">Leave Type</th>
                    <th scope="col">From</th>
                    <th scope="col">To</th>
                    <th scope="col">Days</th>
                    <th scope="col">Reason</th>
                    <th scope="col">Status</th>
                    <th scope="col">Remarks</th>
                    <th scope="col">Applied</th>
                    <th aria-label="Actions" scope="col"></th>
                </tr>
            </thead>
            <tbody>
            @forelse($leaves as $leave)
            <tr>
                <td class="fw-semibold">{{ ucfirst($leave->leave_type) }}</td>
                <td>{{ $leave->from_date->format('d M Y') }}</td>
                <td>{{ $leave->to_date->format('d M Y') }}</td>
                <td>{{ $leave->days }}</td>
                <td style="font-size:.83rem">{{ Str::limit($leave->reason, 60) }}</td>
                <td>
                    @if($leave->status === 'pending')
                        <span class="badge badge-pending">Pending</span>
                    @elseif($leave->status === 'approved')
                        <span class="badge badge-active">Approved</span>
                    @else
                        <span class="badge badge-danger">Rejected</span>
                    @endif
                </td>
                <td style="font-size:.83rem">{{ $leave->admin_remarks ? Str::limit($leave->admin_remarks, 50) : 'No reviewer remarks yet' }}</td>
                <td style="font-size:.83rem">{{ $leave->created_at->format('d M Y') }}</td>
                <td>
                    @if($canApplyForLeave && $leave->status === 'pending')
                        <button class="btn btn-sm btn-outline-danger" aria-label="Cancel leave application"
                        data-confirm-delete="true"
                        data-action="{{ route('teacher.leaves.destroy', $leave) }}"
                        data-name="this leave application">
                        <i class="bi bi-x-lg"></i>
                    </button>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="9">
                <div class="empty-state py-4">
                    <div class="empty-icon"><i class="bi bi-calendar-x"></i></div>
                    <div class="fw-semibold text-dark mb-1">No teacher leave applications submitted yet</div>
                    <div class="text-muted small mx-auto" style="max-width:560px">
                        Use this page when you need planned leave, medical leave, duty leave, or other approved absence.
                        Your request is reviewed by the academic administration; pending requests can be cancelled before review.
                    </div>
                    @if($canApplyForLeave)
                        <a href="{{ route('teacher.leaves.create') }}" class="btn btn-sm btn-primary mt-2">Apply for Leave</a>
                    @else
                        <div class="text-muted small mt-2">Leave submission is enabled only after your active teacher profile is linked.</div>
                    @endif
                </div>
            </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($leaves->hasPages())
    <div class="card-footer">{{ $leaves->links() }}</div>
    @endif
</div>

@endsection
