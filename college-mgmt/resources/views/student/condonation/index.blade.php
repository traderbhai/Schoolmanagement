@extends('layouts.student')
@section('title', 'Attendance Condonation')
@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-semibold mb-0">Attendance Condonation Requests</h4>
        @if($canRequestCondonation)
        <a href="{{ route('student.condonation.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>New Request
        </a>
        @else
            <span class="badge bg-secondary">Active students only</span>
        @endif
    </div>
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-warning">{{ session('error') }}</div>
    @endif
    @unless($canRequestCondonation)
        <div class="alert alert-secondary">
            <i class="bi bi-lock me-1"></i>New attendance condonation requests are locked because your student profile is not active. Existing requests remain visible for history.
        </div>
    @endunless
    @if($condonations->isEmpty())
    <div class="alert alert-info">No condonation requests submitted yet.</div>
    @else
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr><th>Subject</th><th>Reason</th><th>Status</th><th>Remarks</th><th>Submitted</th></tr>
            </thead>
            <tbody>
                @foreach($condonations as $c)
                <tr>
                    <td class="fw-semibold">{{ $c->subject->name ?? '—' }}</td>
                    <td class="text-muted small">{{ Str::limit($c->reason, 60) }}</td>
                    <td>
                        @if($c->status === 'pending') <span class="badge bg-warning text-dark">Pending</span>
                        @elseif($c->status === 'approved') <span class="badge bg-success">Approved ({{ $c->sessions_condoned }} sessions)</span>
                        @else <span class="badge bg-danger">Rejected</span>
                        @endif
                    </td>
                    <td class="text-muted small">{{ $c->remarks ?? '—' }}</td>
                    <td class="text-muted small">{{ $c->created_at->format('d M Y') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $condonations->links() }}
    @endif
</div>
@endsection
