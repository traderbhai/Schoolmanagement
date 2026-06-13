@extends('layouts.admin')
@section('title', 'Hostel Complaints')
@section('page-title', 'Hostel Complaints')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.hostel.index') }}">Hostel</a></li>
    <li class="breadcrumb-item active">Complaints</li>
@endsection

@section('content')

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

{{-- Filters --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-sm-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    @foreach(['open','in_progress','resolved','closed'] as $s)
                        <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-3">
                <select name="priority" class="form-select form-select-sm">
                    <option value="">All Priorities</option>
                    @foreach(['low','medium','high'] as $p)
                        <option value="{{ $p }}" @selected(request('priority') === $p)>{{ ucfirst($p) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary">Filter</button>
                <a href="{{ route('admin.hostel.complaints') }}" class="btn btn-sm btn-outline-secondary ms-1">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Priority</th>
                    <th>Category</th>
                    <th>Title</th>
                    <th>Student</th>
                    <th>Block/Room</th>
                    <th>Status</th>
                    <th>Assigned To</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $priorityColors  = ['low'=>'success','medium'=>'warning','high'=>'danger'];
                    $statusColors    = ['open'=>'warning','in_progress'=>'primary','resolved'=>'success','closed'=>'secondary'];
                @endphp
                @forelse($complaints as $c)
                    <tr style="cursor:pointer" onclick="openComplaint({{ $c->id }})">
                        <td><span class="badge bg-{{ $priorityColors[$c->priority] ?? 'secondary' }}">{{ ucfirst($c->priority) }}</span></td>
                        <td>{{ ucfirst(str_replace('_',' ',$c->category)) }}</td>
                        <td>{{ Str::limit($c->title, 40) }}</td>
                        <td>{{ $c->student?->user?->name ?? '—' }}</td>
                        <td>{{ $c->block?->name ?? '' }} {{ $c->room?->room_number ? '/ '.$c->room->room_number : '' }}</td>
                        <td><span class="badge bg-{{ $statusColors[$c->status] ?? 'secondary' }}">{{ ucfirst(str_replace('_',' ',$c->status)) }}</span></td>
                        <td>{{ $c->assignedTo?->name ?? '—' }}</td>
                        <td onclick="event.stopPropagation()">
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#complaintModal{{ $c->id }}">Update</button>
                        </td>
                    </tr>

                    {{-- Complaint Detail/Update Modal --}}
                    <div class="modal fade" id="complaintModal{{ $c->id }}" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">{{ $c->title }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row mb-3">
                                        <div class="col-sm-6">
                                            <small class="text-muted">Student</small>
                                            <div>{{ $c->student?->user?->name ?? '—' }}</div>
                                        </div>
                                        <div class="col-sm-6">
                                            <small class="text-muted">Category / Priority</small>
                                            <div>{{ ucfirst(str_replace('_',' ',$c->category)) }} / <span class="badge bg-{{ $priorityColors[$c->priority] ?? 'secondary' }}">{{ ucfirst($c->priority) }}</span></div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <small class="text-muted">Description</small>
                                        <p class="mb-0">{{ $c->description }}</p>
                                    </div>
                                    @if($c->resolution_notes)
                                        <div class="mb-3">
                                            <small class="text-muted">Resolution Notes</small>
                                            <p class="mb-0">{{ $c->resolution_notes }}</p>
                                        </div>
                                    @endif
                                    <hr>
                                    <form method="POST" action="{{ route('admin.hostel.complaints.update', $c) }}">
                                        @csrf @method('PUT')
                                        <div class="row g-2">
                                            <div class="col-sm-4">
                                                <label class="form-label">Status</label>
                                                <select name="status" class="form-select" required>
                                                    @foreach(['open','in_progress','resolved','closed'] as $s)
                                                        <option value="{{ $s }}" @selected($c->status === $s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-sm-8">
                                                <label class="form-label">Assign To</label>
                                                <select name="assigned_to" class="form-select">
                                                    <option value="">— Unassigned —</option>
                                                    @foreach($users as $u)
                                                        <option value="{{ $u->id }}" @selected($c->assigned_to == $u->id)>{{ $u->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Resolution Notes</label>
                                                <textarea name="resolution_notes" class="form-control" rows="3">{{ $c->resolution_notes }}</textarea>
                                            </div>
                                        </div>
                                        <div class="mt-3 text-end">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary ms-2">Save</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No complaints found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($complaints->hasPages())
        <div class="card-footer">{{ $complaints->withQueryString()->links() }}</div>
    @endif
</div>

@push('scripts')
<script>
function openComplaint(id) {
    const modal = document.getElementById('complaintModal' + id);
    if (modal) new bootstrap.Modal(modal).show();
}
</script>
@endpush

@endsection
