@extends('layouts.admin')
@section('title', 'Leave Management')
@section('page-title', 'Leave Management')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Leave Management</li>
@endsection

@section('content')

{{-- KPI Cards --}}
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="kpi-card kpi-amber">
            <div class="kpi-icon"><i class="bi bi-hourglass-split"></i></div>
            <div class="kpi-body">
                <div class="kpi-value">{{ $counts['pending'] }}</div>
                <div class="kpi-label">Pending</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-check-circle"></i></div>
            <div class="kpi-body">
                <div class="kpi-value">{{ $counts['approved'] }}</div>
                <div class="kpi-label">Approved</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-x-circle"></i></div>
            <div class="kpi-body">
                <div class="kpi-value">{{ $counts['rejected'] }}</div>
                <div class="kpi-label">Rejected</div>
            </div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-sm-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="pending"  @selected(request('status')=='pending')>Pending</option>
                    <option value="approved" @selected(request('status')=='approved')>Approved</option>
                    <option value="rejected" @selected(request('status')=='rejected')>Rejected</option>
                </select>
            </div>
            <div class="col-sm-4">
                <select name="teacher_id" class="form-select form-select-sm">
                    <option value="">All Teachers</option>
                    @foreach($teachers as $t)
                        <option value="{{ $t->id }}" @selected(request('teacher_id')==$t->id)>{{ $t->user->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('admin.leaves.index') }}" class="btn btn-sm btn-outline-secondary ms-1">Clear</a>
            </div>
        </form>
    </div>
</div>

{{-- Table --}}
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Teacher</th>
                    <th>Leave Type</th>
                    <th>From – To</th>
                    <th>Days</th>
                    <th>Status</th>
                    <th>Reason</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($leaves as $leave)
            <tr>
                <td class="fw-semibold">{{ $leave->teacher?->user?->name ?? '—' }}</td>
                <td>{{ ucfirst($leave->leave_type) }}</td>
                <td style="font-size:.83rem">{{ $leave->from_date->format('d M Y') }} – {{ $leave->to_date->format('d M Y') }}</td>
                <td>{{ $leave->days }}</td>
                <td>
                    @if($leave->status === 'pending')
                        <span class="badge badge-pending">Pending</span>
                    @elseif($leave->status === 'approved')
                        <span class="badge badge-active">Approved</span>
                    @else
                        <span class="badge badge-danger">Rejected</span>
                    @endif
                </td>
                <td style="font-size:.83rem;max-width:180px">{{ Str::limit($leave->reason, 60) }}</td>
                <td>
                    <div class="d-flex gap-1 flex-wrap">
                        <a href="{{ route('admin.leaves.show', $leave) }}" class="btn btn-sm btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                        @if($leave->status === 'pending')
                        <form method="POST" action="{{ route('admin.leaves.approve', $leave) }}">
                            @csrf @method('PATCH')
                            <button class="btn btn-sm btn-success" title="Approve"><i class="bi bi-check-lg"></i></button>
                        </form>
                        <form method="POST" action="{{ route('admin.leaves.reject', $leave) }}">
                            @csrf @method('PATCH')
                            <button class="btn btn-sm btn-warning" title="Reject"><i class="bi bi-x-lg"></i></button>
                        </form>
                        @endif
                        <button class="btn btn-sm btn-outline-danger"
                            data-confirm-delete="true"
                            data-action="{{ route('admin.leaves.destroy', $leave) }}"
                            data-name="{{ ($leave->teacher?->user?->name ?? 'Unknown') }}'s leave">
                            <i class="bi bi-trash3"></i>
                        </button>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="7">
                <div class="empty-state py-4">
                    <div class="empty-icon"><i class="bi bi-calendar-x"></i></div>
                    <div class="text-muted">No leave applications found.</div>
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
