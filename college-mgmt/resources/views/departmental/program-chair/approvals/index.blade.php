@extends('layouts.admin')

@section('title', 'Pending Approvals — Program Chair')

@section('content')
<div class="mb-4">
    <h2 class="fw-bold mb-1">Pending Capacity Sign-offs</h2>
    <p class="text-muted mb-0">Final approval considering program seat capacity</p>
</div>

<div class="card border-0 shadow-sm">
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
                            <th scope="col">Program</th>
                            <th scope="col">Capacity Info</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($approvals as $approval)
                        <tr>
                            <td class="fw-semibold">{{ $approval->approvable->user->name ?? '—' }}</td>
                            <td>{{ $approval->approvable->program->name ?? '—' }}</td>
                            <td>
                                @php
                                    $seat = \App\Models\SeatMatrix::where('program_id', $approval->approvable->program_id)->first();
                                    $filled = \App\Models\Applicant::where('program_id', $approval->approvable->program_id)
                                        ->whereIn('status', ['offer_accepted', 'enrolled'])->count();
                                @endphp
                                @if($seat)
                                    <span class="badge bg-info">{{ $filled }} / {{ $seat->total_seats }} seats</span>
                                @else
                                    <span class="badge bg-secondary">No capacity defined</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#approveModal{{ $approval->id }}">
                                    <i class="bi bi-check"></i> Approve request
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
                                            <h5 class="modal-title">Approve Applicant</h5>
                                            <button aria-label="Close dialog" type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form action="{{ route('chair.approve', $approval) }}" method="POST">
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
                                        <form action="{{ route('chair.reject', $approval) }}" method="POST">
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
