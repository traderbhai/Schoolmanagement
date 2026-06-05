@extends('layouts.admin')
@section('title','Activity Log')
@section('page-title','Activity Log')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Activity Log</li>
@endsection
@section('content')

<div class="card mb-3" style="box-shadow:var(--shadow-sm)">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-1" style="color:var(--clr-text-muted)">Action</label>
                <select name="action" class="form-select form-select-sm">
                    <option value="">All Actions</option>
                    @foreach(['created','updated','deleted','login','logout'] as $a)
                    <option value="{{ $a }}" @selected(request('action')==$a)>{{ ucfirst($a) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-1" style="color:var(--clr-text-muted)">User</label>
                <input type="text" name="search" class="form-control form-control-sm" value="{{ request('search') }}" placeholder="Search user…">
            </div>
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-1" style="color:var(--clr-text-muted)">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}">
            </div>
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-1" style="color:var(--clr-text-muted)">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('admin.activity-log') }}" class="btn btn-outline-secondary btn-sm ms-1">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card" style="box-shadow:var(--shadow-sm)">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-clock-history me-2 text-primary"></i>System Activity</span>
        <span class="badge bg-secondary">{{ $logs->total() }} entries</span>
    </div>
    <div class="card-body p-0">
        @if($logs->isEmpty())
        <div class="empty-state py-5">
            <div class="empty-icon"><i class="bi bi-clock-history" style="font-size:3rem;color:var(--clr-text-muted)"></i></div>
            <p class="text-muted small mt-2 mb-0">No activity recorded yet.</p>
        </div>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:.85rem">
                <thead class="table-light">
                    <tr>
                        <th>Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($logs as $log)
                @php
                $actionBadge = match($log->action) {
                    'created'  => 'badge-active',
                    'updated'  => 'badge-info',
                    'deleted'  => 'badge-danger',
                    'login'    => 'bg-success text-white',
                    'logout'   => 'bg-secondary text-white',
                    default    => 'bg-secondary text-white',
                };
                @endphp
                <tr>
                    <td class="text-muted" style="white-space:nowrap">
                        <span title="{{ $log->created_at }}">{{ $log->created_at->diffForHumans() }}</span>
                    </td>
                    <td>{{ $log->user->name ?? '—' }}</td>
                    <td><span class="badge {{ $actionBadge }}">{{ ucfirst($log->action) }}</span></td>
                    <td>{{ $log->description }}</td>
                    <td class="text-muted font-monospace" style="font-size:.75rem">{{ $log->ip_address }}</td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-3 py-2">{{ $logs->links() }}</div>
        @endif
    </div>
</div>
@endsection
