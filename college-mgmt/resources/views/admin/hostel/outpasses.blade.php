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
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

{{-- Status Filter Tabs --}}
<ul class="nav nav-tabs mb-3">
    @foreach([''=>'All','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','returned'=>'Returned'] as $val => $label)
        <li class="nav-item">
            <a class="nav-link {{ request('status', '') === $val ? 'active' : '' }}" href="{{ route('admin.hostel.outpasses', ['status' => $val]) }}">{{ $label }}</a>
        </li>
    @endforeach
</ul>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Student</th>
                    <th>Room</th>
                    <th>Reason</th>
                    <th>Out DateTime</th>
                    <th>Expected Return</th>
                    <th>Actual Return</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($outpasses as $op)
                    @php $statusColors = ['pending'=>'warning','approved'=>'success','rejected'=>'danger','returned'=>'info']; @endphp
                    <tr>
                        <td>{{ $op->student?->user?->name ?? '—' }}</td>
                        <td>
                            {{ $op->allocation?->room?->block?->name ?? '' }}
                            {{ $op->allocation?->room?->room_number ? '/ '.$op->allocation->room->room_number : '—' }}
                        </td>
                        <td><span title="{{ $op->reason }}">{{ Str::limit($op->reason, 40) }}</span></td>
                        <td>{{ $op->out_datetime?->format('d M Y H:i') }}</td>
                        <td>{{ $op->expected_return?->format('d M Y H:i') }}</td>
                        <td>{{ $op->actual_return?->format('d M Y H:i') ?? '—' }}</td>
                        <td><span class="badge bg-{{ $statusColors[$op->status] ?? 'secondary' }}">{{ ucfirst($op->status) }}</span></td>
                        <td>
                            @if($op->status === 'pending')
                                <form method="POST" action="{{ route('admin.hostel.outpasses.approve', $op) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-xs btn-success btn-sm">Approve</button>
                                </form>
                                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $op->id }}">Reject</button>
                            @elseif($op->status === 'approved')
                                <form method="POST" action="{{ route('admin.hostel.outpasses.return', $op) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-info">Mark Returned</button>
                                </form>
                            @endif
                        </td>
                    </tr>

                    {{-- Reject Modal --}}
                    <div class="modal fade" id="rejectModal{{ $op->id }}" tabindex="-1">
                        <div class="modal-dialog modal-sm">
                            <div class="modal-content">
                                <form method="POST" action="{{ route('admin.hostel.outpasses.reject', $op) }}">
                                    @csrf
                                    <div class="modal-header"><h6 class="modal-title">Reject Outpass</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                    <div class="modal-body">
                                        <label class="form-label">Remarks</label>
                                        <textarea name="remarks" class="form-control" rows="2"></textarea>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No outpass requests found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($outpasses->hasPages())
        <div class="card-footer">{{ $outpasses->withQueryString()->links() }}</div>
    @endif
</div>

@endsection
