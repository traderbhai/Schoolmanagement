@extends('layouts.admin')

@section('title', 'Pending Approvals — Dean Academics')

@section('content')
<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold mb-1">Pending Approvals</h2>
            <p class="text-muted mb-0">Applicants cleared by HOD awaiting final clearance</p>
        </div>
        <div class="badge bg-success fs-6">{{ $approved_count }} Approved</div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3">
        <form method="GET" class="d-flex gap-2 align-items-end">
            <div class="flex-grow-1">
                <label class="form-label small text-muted">Filter by Program</label>
                <select aria-label="Program" name="program_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Programs</option>
                    @foreach($programs as $prog)
                        <option value="{{ $prog->id }}" {{ request('program_id') == $prog->id ? 'selected' : '' }}>
                            {{ $prog->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    <div class="card-body">
        @if($approvals->isEmpty())
            <div class="alert alert-info mb-0">
                <i class="bi bi-check-circle me-1"></i> No pending approvals.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th scope="col">Applicant</th>
                            <th scope="col">Application Number</th>
                            <th scope="col">Program</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($approvals as $approval)
                        <tr>
                            <td class="fw-semibold">{{ $approval->approvable->user->name ?? 'Applicant name missing' }}</td>
                            <td class="font-monospace small">{{ $approval->approvable->application_number }}</td>
                            <td>{{ $approval->approvable->program->name ?? 'Program not assigned' }}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#approveModal{{ $approval->id }}">
                                    <i class="bi bi-check"></i> Approve & Generate Offer
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $approval->id }}">
                                    <i class="bi bi-x"></i> Reject request
                                </button>
                            </td>

                            <!-- Approve Modal -->
                            <div class="modal fade" id="approveModal{{ $approval->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Approve & Generate Offer Letter</h5>
                                            <button aria-label="Close dialog" type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form action="{{ route('dean.approve', $approval) }}" method="POST">
                                            @csrf
                                            <div class="modal-body">
                                                <label for="remarks{{ $approval->id }}" class="form-label">Remarks (optional)</label>
                                                <textarea name="remarks" id="remarks{{ $approval->id }}" class="form-control" rows="3"></textarea>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-success">Approve request</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Reject Modal -->
                            <div class="modal fade" id="rejectModal{{ $approval->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Reject Approval</h5>
                                            <button aria-label="Close dialog" type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form action="{{ route('dean.reject', $approval) }}" method="POST">
                                            @csrf
                                            <div class="modal-body">
                                                <label for="reason{{ $approval->id }}" class="form-label">Reason <span class="text-danger">*</span></label>
                                                <textarea name="rejection_reason" id="reason{{ $approval->id }}" class="form-control" rows="3" required></textarea>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger">Reject request</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $approvals->render() }}
            </div>
        @endif
    </div>
</div>

@endsection
