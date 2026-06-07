@extends('layouts.student')
@section('title', 'Leave Applications')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-semibold mb-0">Leave Applications</h4>
        <a href="{{ route('student.leave.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Apply for Leave
        </a>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    @if($leaves->isEmpty())
    <div class="alert alert-info">No leave applications yet.</div>
    @else
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Reason</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Days</th>
                    <th>Status</th>
                    <th>Remarks</th>
                    <th>Applied</th>
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
                    <td class="text-muted small">{{ $leave->review_remarks ?? '—' }}</td>
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
