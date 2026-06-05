@extends('layouts.admin')
@section('title', 'Classrooms')
@section('page-title', 'Classrooms')
@section('content')

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h5 class="mb-0 fw-bold">Classrooms</h5>
        <div class="text-muted" style="font-size:.82rem">Manage rooms and lab facilities</div>
    </div>
    <a href="{{ route('admin.classrooms.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Add Room
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Room No.</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Capacity</th>
                    <th>Building / Floor</th>
                    <th>Features</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($classrooms as $r)
            <tr>
                <td><span class="fw-bold fs-6">{{ $r->room_number }}</span></td>
                <td class="fw-semibold">{{ $r->name }}</td>
                <td>
                    <span class="badge {{ match($r->type){'lecture'=>'bg-primary','lab'=>'bg-warning text-dark','seminar'=>'bg-info text-dark','auditorium'=>'bg-success',default=>'bg-secondary'} }}">
                        {{ ucfirst($r->type) }}
                    </span>
                </td>
                <td>{{ $r->capacity }}</td>
                <td>
                    {{ $r->building ?? '–' }}
                    @if($r->floor) <span class="text-muted">&middot; {{ $r->floor }}</span>@endif
                </td>
                <td>
                    <div class="d-flex gap-1 flex-wrap">
                        @if($r->has_projector)<span class="badge bg-light text-dark border" style="font-size:.72rem"><i class="bi bi-projector me-1"></i>Projector</span>@endif
                        @if($r->has_lab)<span class="badge bg-light text-dark border" style="font-size:.72rem"><i class="bi bi-pc-display me-1"></i>Lab</span>@endif
                    </div>
                </td>
                <td><span class="badge {{ $r->is_active ? 'badge-active' : 'badge-danger' }}">{{ $r->is_active ? 'Active' : 'Inactive' }}</span></td>
                <td>
                    <div class="d-flex gap-1">
                        <a href="{{ route('admin.classrooms.show', $r) }}" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('admin.classrooms.edit', $r) }}" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                        <button type="button" class="btn btn-sm btn-outline-danger" title="Delete"
                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                            data-action="{{ route('admin.classrooms.destroy', $r) }}"
                            data-name="{{ $r->name }}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8">
                    <div class="empty-state py-5">
                        <div class="empty-icon"><i class="bi bi-door-open"></i></div>
                        <div class="mt-2 fw-semibold">No classrooms added yet</div>
                        <a href="{{ route('admin.classrooms.create') }}" class="btn btn-primary btn-sm mt-3">Add Room</a>
                    </div>
                </td>
            </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($classrooms->hasPages())
    <div class="card-footer">{{ $classrooms->links() }}</div>
    @endif
</div>
@endsection
