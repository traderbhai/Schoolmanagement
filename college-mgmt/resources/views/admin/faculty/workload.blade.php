@extends('layouts.admin')
@section('title', 'Faculty Workload Report')
@section('page-title', 'Faculty Workload Report')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Faculty Workload Report</li>
@endsection

@section('content')

<div class="d-flex align-items-center justify-content-between mb-3">
    <div class="text-muted" style="font-size:.85rem"><i class="bi bi-info-circle me-1"></i>Sorted by weekly periods (descending). CSV export coming in Sprint 8.</div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Teacher Name</th>
                    <th>Department</th>
                    <th>Designation</th>
                    <th>Weekly Periods</th>
                    <th>Total Students</th>
                    <th>Leaves This Month</th>
                    <th>Pending Leaves</th>
                </tr>
            </thead>
            <tbody>
            @forelse($teachers as $teacher)
            <tr>
                <td class="fw-semibold">
                    <a href="{{ route('admin.teachers.show', $teacher) }}" class="text-decoration-none">
                        {{ $teacher->user->name }}
                    </a>
                </td>
                <td>{{ $teacher->department->name ?? '–' }}</td>
                <td>{{ $teacher->designation ?? '–' }}</td>
                <td>
                    <span class="badge {{ $teacher->weekly_periods > 20 ? 'bg-danger' : ($teacher->weekly_periods > 10 ? 'badge-active' : 'bg-secondary') }}">
                        {{ $teacher->weekly_periods }}
                    </span>
                </td>
                <td>{{ $teacher->total_students }}</td>
                <td>
                    @if($teacher->approved_leaves_month > 0)
                        <span class="badge badge-pending">{{ $teacher->approved_leaves_month }}</span>
                    @else
                        <span class="text-muted">0</span>
                    @endif
                </td>
                <td>
                    @if($teacher->pending_leaves > 0)
                        <span class="badge badge-danger">{{ $teacher->pending_leaves }}</span>
                    @else
                        <span class="text-muted">0</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="7">
                <div class="empty-state py-4">
                    <div class="empty-icon"><i class="bi bi-people"></i></div>
                    <div class="text-muted">No faculty members found.</div>
                </div>
            </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
