@extends('layouts.admin')
@section('title', 'Audit Log')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Audit Log</h4>
            <span class="text-muted small">All privileged actions with actor, timestamp, and change details</span>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- Filters --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('admin.audit.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Action</label>
                    <select name="action" class="form-select form-select-sm">
                        <option value="">All actions</option>
                        @foreach($actions as $action)
                        <option value="{{ $action }}" {{ request('action') === $action ? 'selected' : '' }}>
                            {{ $action }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Date From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm"
                           value="{{ request('date_from') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Date To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm"
                           value="{{ request('date_to') }}">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    <a href="{{ route('admin.audit.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Timestamp</th>
                            <th>Actor</th>
                            <th>Action</th>
                            <th>Target</th>
                            <th>Summary</th>
                            <th class="text-end pe-3">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                        @php
                            $actionColors = [
                                'role_assigned'       => 'success',
                                'role_revoked'        => 'danger',
                                'permission_changed'  => 'warning',
                                'feature_access_updated' => 'info',
                            ];
                            $color = $actionColors[$log->action] ?? 'secondary';
                        @endphp
                        <tr>
                            <td class="ps-3 small text-muted text-nowrap">
                                {{ $log->created_at->format('d M Y H:i') }}
                            </td>
                            <td>
                                <div class="fw-medium small">{{ $log->actor->name ?? 'System' }}</div>
                                <div class="text-muted" style="font-size:.72rem">{{ $log->actor->email ?? '' }}</div>
                            </td>
                            <td>
                                <span class="badge bg-{{ $color }}-subtle text-{{ $color }} rounded-pill px-2">
                                    {{ str_replace('_', ' ', $log->action) }}
                                </span>
                            </td>
                            <td class="small text-muted">
                                {{ $log->target_type ? class_basename($log->target_type) : '—' }}
                                @if($log->target_id) #{{ $log->target_id }} @endif
                            </td>
                            <td class="small">
                                @if(is_array($log->changes) && isset($log->changes['summary']))
                                    {{ $log->changes['summary'] }}
                                @elseif(is_array($log->changes))
                                    {{ implode(', ', array_map(fn($v, $k) => "$k: $v", $log->changes, array_keys($log->changes))) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <a href="{{ route('admin.audit.show', $log) }}"
                                   class="btn btn-sm btn-outline-secondary py-0 px-2">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No audit logs found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($logs->hasPages())
    <div class="mt-3">
        {{ $logs->links() }}
    </div>
    @endif
</div>
@endsection
