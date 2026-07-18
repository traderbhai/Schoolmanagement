@extends('layouts.admin')
@section('title', 'Approval Chain')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('approvals.inbox') }}">Approvals Inbox</a></li>
                    <li class="breadcrumb-item active">Chain View</li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0">Approval Chain</h4>
            <span class="text-muted small">
                {{ ucwords(str_replace('_', ' ', $approval->workflow_type ?? 'general')) }} —
                {{ class_basename($approval->approvable_type) }} #{{ $approval->approvable_id }}
            </span>
        </div>
        <a href="{{ route('approvals.inbox') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Inbox
        </a>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- Chain Definition (expected) --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent border-0 pt-3 pb-0">
            <h6 class="fw-semibold mb-0">Expected Steps</h6>
        </div>
        <div class="card-body">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                @foreach($chainDef as $i => $step)
                @php
                    $histStep = $history->firstWhere('step_order', $i + 1);
                    $color = match($histStep?->status) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'pending'  => 'warning',
                        default    => 'secondary',
                    };
                    $icon = match($histStep?->status) {
                        'approved' => 'check-circle-fill',
                        'rejected' => 'x-circle-fill',
                        'pending'  => 'hourglass-split',
                        default    => 'circle',
                    };
                @endphp
                <div class="text-center" style="min-width:90px">
                    <div class="rounded-circle mx-auto d-flex align-items-center justify-content-center bg-{{ $color }}-subtle border border-{{ $color }}-subtle"
                         style="width:44px;height:44px">
                        <i class="bi bi-{{ $icon }} text-{{ $color }}"></i>
                    </div>
                    <div class="small fw-medium mt-1">{{ $step['label'] }}</div>
                    <div class="text-muted" style="font-size:.7rem">{{ $step['sla_hours'] }}h SLA</div>
                </div>
                @if(!$loop->last)
                <div class="flex-shrink-0 text-muted">→</div>
                @endif
                @endforeach
            </div>
        </div>
    </div>

    {{-- Actual History --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-0 pt-3 pb-0">
            <h6 class="fw-semibold mb-0">Step History</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" class="ps-3">Step</th>
                            <th scope="col">Role</th>
                            <th scope="col">Actioned By</th>
                            <th scope="col">Status</th>
                            <th scope="col">SLA</th>
                            <th scope="col">Date</th>
                            <th scope="col">Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($history as $step)
                        <tr>
                            <td class="ps-3 small fw-medium">Step {{ $step->step_order }}</td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary px-2" style="font-size:.7rem">
                                    {{ ucwords(str_replace('_', ' ', $step->approver_role)) }}
                                </span>
                            </td>
                            <td class="small">{{ $step->approver->name ?? '—' }}</td>
                            <td><span class="{{ $step->status_badge }}">{{ $step->status_label }}</span></td>
                            <td class="small {{ $step->slaBadgeClass() }}">
                                @if($step->due_at)
                                    {{ $step->due_at->format('d M H:i') }}
                                    @if($step->isOverdue()) <i class="bi bi-exclamation-triangle ms-1"></i> @endif
                                @else
                                <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="small text-muted">
                                {{ $step->approved_at?->format('d M Y H:i') ?? ($step->created_at->format('d M Y')) }}
                            </td>
                            <td class="small text-muted">{{ $step->remarks ?? '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No history yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
