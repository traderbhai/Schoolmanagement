@extends('layouts.admin')
@section('title', 'Audit Log Entry #' . $log->id)

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('admin.audit.index') }}">Audit Log</a></li>
                    <li class="breadcrumb-item active">#{{ $log->id }}</li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0">Audit Entry #{{ $log->id }}</h4>
            <span class="text-muted small">{{ $log->created_at->format('d M Y, H:i:s') }}</span>
        </div>
        <a href="{{ route('admin.audit.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>

    <div class="row g-4">
        <div class="col-md-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h6 class="fw-semibold mb-0">Event Details</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5 text-muted small">Action</dt>
                        <dd class="col-7">
                            @php
                                $actionColors = [
                                    'role_assigned'          => 'success',
                                    'role_revoked'           => 'danger',
                                    'permission_changed'     => 'warning',
                                    'feature_access_updated' => 'info',
                                ];
                                $color = $actionColors[$log->action] ?? 'secondary';
                            @endphp
                            <span class="badge bg-{{ $color }}-subtle text-{{ $color }} rounded-pill px-2">
                                {{ str_replace('_', ' ', $log->action) }}
                            </span>
                        </dd>

                        <dt class="col-5 text-muted small">Actor</dt>
                        <dd class="col-7">
                            <div class="fw-medium">{{ $log->actor->name ?? 'System' }}</div>
                            <div class="text-muted small">{{ $log->actor->email ?? '' }}</div>
                        </dd>

                        <dt class="col-5 text-muted small">Target Type</dt>
                        <dd class="col-7 small">{{ $log->target_type ? class_basename($log->target_type) : '—' }}</dd>

                        <dt class="col-5 text-muted small">Target ID</dt>
                        <dd class="col-7 small">{{ $log->target_id ?? '—' }}</dd>

                        <dt class="col-5 text-muted small">Timestamp</dt>
                        <dd class="col-7 small">{{ $log->created_at->format('d M Y H:i:s') }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h6 class="fw-semibold mb-0">Change Data</h6>
                </div>
                <div class="card-body">
                    @if($log->changes && is_array($log->changes) && count($log->changes))
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Field</th>
                                <th scope="col">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($log->changes as $key => $value)
                            <tr>
                                <td class="fw-medium small text-muted">{{ $key }}</td>
                                <td class="small">
                                    @if(is_array($value))
                                        <code>{{ json_encode($value, JSON_PRETTY_PRINT) }}</code>
                                    @else
                                        {{ $value }}
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @else
                    <div class="text-muted small fst-italic">No change data recorded.</div>
                    @endif

                    {{-- Raw JSON --}}
                    <div class="mt-3">
                        <button class="btn btn-sm btn-outline-secondary" type="button"
                                data-bs-toggle="collapse" data-bs-target="#rawJson">
                            <i class="bi bi-code me-1"></i>Raw JSON
                        </button>
                        <div class="collapse mt-2" id="rawJson">
                            <pre class="bg-light p-3 rounded small" style="max-height:300px;overflow-y:auto">{{ json_encode($log->changes, JSON_PRETTY_PRINT) }}</pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
