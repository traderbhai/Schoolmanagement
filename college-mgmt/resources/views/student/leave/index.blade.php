@extends('layouts.student')
@section('title', 'Leave Applications')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-semibold mb-0">Leave Applications</h4>
        @if($canApplyForLeave)
            <a href="{{ route('student.leave.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Apply for Leave
            </a>
        @else
            <span class="badge bg-secondary">Active students only</span>
        @endif
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}<button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    @if($leaves->isEmpty())
    <div class="alert alert-info">
        <div class="fw-semibold mb-1">No leave applications submitted yet</div>
        <div class="small">
            Apply before planned absence, medical leave, or urgent family leave. After submission, your request stays pending until the program office or academic reviewer approves or rejects it.
        </div>
        @if($canApplyForLeave)
            <a href="{{ route('student.leave.create') }}" class="btn btn-sm btn-outline-primary mt-2">Apply for Leave</a>
        @endif
    </div>
    @else
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th scope="col">Reason</th>
                    <th scope="col">From</th>
                    <th scope="col">To</th>
                    <th scope="col">Days</th>
                    <th scope="col">Status</th>
                    <th scope="col">Remarks</th>
                    <th scope="col">Applied</th>
                </tr>
            </thead>
            <tbody>
                @foreach($leaves as $leave)
                <tr>
                    <td class="fw-semibold">{{ $leave->reason }}</td>
                    <td>{{ $leave->from_date->format('d M Y') }}</td>
                    <td>{{ $leave->to_date->format('d M Y') }}</td>
                    <td>{{ $leave->getDaysCount() }}</td>
                    <td>
                        @if($leave->status === 'pending')
                            <span class="badge bg-warning text-dark">Pending</span>
                        @elseif($leave->status === 'approved')
                            <span class="badge bg-success">Approved</span>
                        @else
                            <span class="badge bg-danger">Rejected</span>
                        @endif
                    </td>
                    <td class="text-muted small">{{ $leave->review_remarks ?? 'No reviewer remarks yet' }}</td>
                    <td class="text-muted small">{{ $leave->created_at->format('d M Y') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $leaves->links() }}
    @endif
</div>
@endsection
