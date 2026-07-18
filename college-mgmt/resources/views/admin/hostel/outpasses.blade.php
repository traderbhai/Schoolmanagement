@extends('layouts.admin')
@section('title', 'Outpass Requests')
@section('page-title', 'Outpass Requests')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.hostel.index') }}">Hostel</a></li>
    <li class="breadcrumb-item active">Outpasses</li>
@endsection

@section('content')

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

{{-- Status Filter Tabs --}}
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <ul class="nav nav-tabs mb-0">
        @foreach([''=>'All','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','returned'=>'Returned'] as $val => $label)
            <li class="nav-item">
                <a class="nav-link {{ request('status', '') === $val ? 'active' : '' }}" href="{{ route('admin.hostel.outpasses', ['status' => $val]) }}">{{ $label }}</a>
            </li>
        @endforeach
    </ul>
    <a href="{{ route('admin.hostel.outpasses.export', request()->query()) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i>Export Current View</a>
</div>
<div class="text-muted small mb-2">Showing {{ $outpasses->total() }} outpass record(s){{ request('status') ? ' filtered by status: '.request('status') : '' }}.</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col">Student</th>
                    <th scope="col">Room</th>
                    <th scope="col">Reason</th>
                    <th scope="col">Out DateTime</th>
                    <th scope="col">Expected Return</th>
                    <th scope="col">Actual Return</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($outpasses as $op)
                    @php
                        $statusColors = ['pending'=>'warning','approved'=>'success','rejected'=>'danger','returned'=>'info'];
                        $isExpiredPending = $op->status === 'pending' && $op->out_datetime && $op->out_datetime->isPast();
                        $canMarkReturned = $op->status === 'approved' && (! $op->out_datetime || now()->greaterThanOrEqualTo($op->out_datetime));
                    @endphp
                    <tr>
                        <td>{{ $op->student?->user?->name ?? 'Student not linked' }}</td>
                        <td>
                            {{ $op->allocation?->room?->block?->name ?? 'Block not linked' }}
                            {{ $op->allocation?->room?->room_number ? '/ '.$op->allocation->room->room_number : '/ Room not linked' }}
                        </td>
                        <td><span title="{{ $op->reason }}">{{ Str::limit($op->reason, 40) }}</span></td>
                        <td>{{ $op->out_datetime?->format('d M Y H:i') }}</td>
                        <td>{{ $op->expected_return?->format('d M Y H:i') }}</td>
                        <td>{{ $op->actual_return?->format('d M Y H:i') ?? 'Return not marked' }}</td>
                        <td><span class="badge bg-{{ $statusColors[$op->status] ?? 'secondary' }}">{{ ucfirst($op->status) }}</span></td>
                        <td>
                            @if($op->status === 'pending')
                                @if($isExpiredPending)
                                    <span class="badge bg-secondary me-1">Expired</span>
                                @else
                                    <form method="POST" action="{{ route('admin.hostel.outpasses.approve', $op) }}" class="d-inline" onsubmit="return confirm('Approve hostel outpass for {{ addslashes($op->student->user->name ?? 'this student') }}? Confirm reason, expected return, guardian/campus policy, and active hostel allocation before allowing exit.')">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-success btn-sm">Approve outpass</button>
                                    </form>
                                @endif
                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $op->id }}">Reject outpass</button>
                            @elseif($op->status === 'approved')
                                @if($canMarkReturned)
                                    <form method="POST" action="{{ route('admin.hostel.outpasses.return', $op) }}" class="d-inline" onsubmit="return confirm('Mark {{ addslashes($op->student->user->name ?? 'this student') }} as returned from outpass? Confirm physical return, actual time, and any late/escalation notes before closing the movement record.')">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-info">Mark Returned</button>
                                    </form>
                                @else
                                    <span class="text-muted small">Not out yet</span>
                                @endif
                            @endif
                        </td>
                    </tr>

                    {{-- Reject Modal --}}
                    <div class="modal fade" id="rejectModal{{ $op->id }}" tabindex="-1">
                        <div class="modal-dialog modal-sm">
                            <div class="modal-content">
                                <form method="POST" action="{{ route('admin.hostel.outpasses.reject', $op) }}">
                                    @csrf
                                    <div class="modal-header"><h6 class="modal-title">Reject Outpass</h6><button aria-label="Close dialog" type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                    <div class="modal-body">
                                        <label class="form-label">Remarks</label>
                                        <textarea aria-label="Remarks" name="remarks" class="form-control" rows="2"></textarea>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-danger btn-sm">Reject outpass</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            No outpass requests match this view. Clear the status tab or wait for hostel students to submit leave, visit, medical, or interview outpass requests.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($outpasses->hasPages())
        <div class="card-footer">{{ $outpasses->withQueryString()->links() }}</div>
    @endif
</div>

@endsection
