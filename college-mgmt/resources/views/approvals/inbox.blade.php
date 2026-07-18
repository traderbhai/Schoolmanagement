@extends('layouts.admin')
@section('title', 'Approvals Inbox')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Approvals Inbox</h4>
            <span class="text-muted small">All pending approvals assigned to your roles</span>
        </div>
        @if($overdueCount > 0)
        <span class="badge bg-danger px-3 py-2 fs-6">
            <i class="bi bi-exclamation-triangle me-1"></i>{{ $overdueCount }} Overdue
        </span>
        @endif
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif
    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- Filters --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Status</label>
                    <select aria-label="Status" name="status" class="form-select form-select-sm">
                        <option value="pending" {{ request('status','pending') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                        <option value="" {{ request('status') === '' ? 'selected' : '' }}>All</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Workflow Type</label>
                    <select aria-label="Workflow Type" name="workflow_type" class="form-select form-select-sm">
                        <option value="">All types</option>
                        @foreach($workflowTypes as $type)
                        <option value="{{ $type }}" {{ request('workflow_type') === $type ? 'selected' : '' }}>
                            {{ ucwords(str_replace('_', ' ', $type)) }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    <a href="{{ route('approvals.inbox') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
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
                            <th scope="col" class="ps-3">Subject</th>
                            <th scope="col">Workflow</th>
                            <th scope="col">Step</th>
                            <th scope="col">Role</th>
                            <th scope="col">SLA / Due</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($approvals as $approval)
                        <tr class="{{ $approval->isOverdue() ? 'table-danger' : '' }}">
                            <td class="ps-3">
                                @php $subject = $approval->approvable; @endphp
                                @if($subject)
                                <div class="fw-medium small">{{ class_basename($approval->approvable_type) }} #{{ $approval->approvable_id }}</div>
                                @if(method_exists($subject, 'getNameAttribute') || isset($subject->name))
                                <div class="text-muted" style="font-size:.72rem">{{ $subject->name ?? '' }}</div>
                                @elseif(isset($subject->user))
                                <div class="text-muted" style="font-size:.72rem">{{ $subject->user->name ?? '' }}</div>
                                @endif
                                @else
                                <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary px-2" style="font-size:.7rem">
                                    {{ ucwords(str_replace('_', ' ', $approval->workflow_type ?? 'general')) }}
                                </span>
                            </td>
                            <td class="small text-muted">
                                Step {{ $approval->step_order }}
                                @if($approval->isEscalated())
                                <span class="badge bg-warning text-dark ms-1" style="font-size:.65rem">Escalated</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary px-2" style="font-size:.7rem">
                                    {{ ucwords(str_replace('_', ' ', $approval->approver_role)) }}
                                </span>
                            </td>
                            <td class="small {{ $approval->slaBadgeClass() }}">
                                @if($approval->due_at)
                                    @if($approval->due_at->isPast())
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    Overdue {{ $approval->due_at->diffForHumans() }}
                                    @else
                                    Due {{ $approval->due_at->diffForHumans() }}
                                    @endif
                                @else
                                <span class="text-muted">No SLA</span>
                                @endif
                            </td>
                            <td>
                                <span class="{{ $approval->status_badge }}">{{ $approval->status_label }}</span>
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-flex gap-1 justify-content-end">
                        <a href="{{ route('approvals.chain', $approval) }}"
                           class="btn btn-sm btn-outline-secondary py-0 px-2" aria-label="View approval chain {{ $approval->id }}">
                                        <i class="bi bi-diagram-2"></i>
                                    </a>
                                    @if($approval->status === 'pending')
                                    <button type="button" class="btn btn-sm btn-outline-success py-0 px-2"
                                            data-bs-toggle="modal" data-bs-target="#approveModal{{ $approval->id }}">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2"
                                            data-bs-toggle="modal" data-bs-target="#rejectModal{{ $approval->id }}">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                    @endif
                                </div>
                            </td>
                        </tr>

                        {{-- Approve Modal --}}
                        @if($approval->status === 'pending')
                        <div class="modal fade" id="approveModal{{ $approval->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h6 class="modal-title fw-semibold">Approve Step {{ $approval->step_order }}</h6>
                                        <button aria-label="Close dialog" type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST" action="{{ route('approvals.approve', $approval) }}">
                                        @csrf
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label small">Remarks (optional)</label>
                                                <textarea aria-label="Approval decision notes" name="remarks" class="form-control form-control-sm" rows="3"
                                                          placeholder="Add any notes..."></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-success btn-sm">
                                                <i class="bi bi-check-lg me-1"></i>Approve step
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        {{-- Reject Modal --}}
                        <div class="modal fade" id="rejectModal{{ $approval->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h6 class="modal-title fw-semibold text-danger">Reject Step {{ $approval->step_order }}</h6>
                                        <button aria-label="Close dialog" type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST" action="{{ route('approvals.reject', $approval) }}">
                                        @csrf
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold">Reason for rejection <span class="text-danger">*</span></label>
                                                <textarea aria-label="Approval rejection reason" name="rejection_reason" class="form-control form-control-sm" rows="3"
                                                          placeholder="State the reason for rejection..." required></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <i class="bi bi-x-lg me-1"></i>Reject step
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        @endif

                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="bi bi-check-circle me-2 text-success"></i>
                                No approvals found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($approvals->hasPages())
    <div class="mt-3">{{ $approvals->links() }}</div>
    @endif

    {{-- Chain Definitions Reference --}}
    <div class="mt-4">
        <button class="btn btn-sm btn-outline-secondary" type="button"
                data-bs-toggle="collapse" data-bs-target="#chainRef">
            <i class="bi bi-info-circle me-1"></i>Workflow Chain Reference
        </button>
        <div class="collapse mt-2" id="chainRef">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="row g-3">
                        @foreach($chainDefinitions as $type => $steps)
                        <div class="col-md-4">
                            <div class="fw-semibold small text-uppercase mb-1" style="font-size:.7rem;letter-spacing:.07em">
                                {{ str_replace('_', ' ', $type) }}
                            </div>
                            @foreach($steps as $i => $step)
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="badge bg-light text-dark border" style="font-size:.65rem;min-width:20px">{{ $i+1 }}</span>
                                <span class="small">{{ $step['label'] }}</span>
                                <span class="text-muted" style="font-size:.7rem">({{ $step['sla_hours'] }}h)</span>
                            </div>
                            @endforeach
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
