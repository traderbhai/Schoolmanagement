@extends('layouts.admin')
@section('title', 'Parents')
@section('page-title', 'Parents')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Parents</li>
@endsection

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h5 class="mb-0 fw-bold">Parents</h5>
        <div class="text-muted" style="font-size:.82rem">Manage parent accounts and student links</div>
    </div>
    <a href="{{ route('admin.parents.create') }}" class="btn btn-primary">
        <i class="bi bi-person-plus me-1"></i>Add Parent
    </a>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex gap-2">
            <input aria-label="Parent search" type="search" name="search" class="form-control form-control-sm" style="max-width:300px"
                placeholder="Search by name, email or phone..." value="{{ request('search') }}">
            <button class="btn btn-sm btn-outline-secondary">Search</button>
            @if(request('search'))
            <a href="{{ route('admin.parents.index') }}" class="btn btn-sm btn-outline-danger">Clear</a>
            @endif
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Email</th>
                    <th scope="col">Phone</th>
                    <th scope="col">Relation</th>
                    <th scope="col">Children</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($parents as $parent)
            <tr>
                <td class="fw-semibold">{{ $parent->user->name }}</td>
                <td>{{ $parent->user->email }}</td>
                <td>{{ $parent->phone ?? '—' }}</td>
                <td><span class="badge bg-secondary">{{ ucfirst($parent->relation) }}</span></td>
                <td>{{ $parent->students->count() }}</td>
                <td>
                    <div class="d-flex gap-1">
                        <a href="{{ route('admin.parents.show', $parent) }}" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('admin.parents.edit', $parent) }}" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                        <button type="button" class="btn btn-sm btn-outline-danger" title="Delete"
                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                            data-action="{{ route('admin.parents.destroy', $parent) }}"
                            data-name="{{ $parent->user->name }}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6">
                    <div class="empty-state py-5">
                        <div class="empty-icon"><i class="bi bi-people-fill"></i></div>
                        <div class="mt-2 fw-semibold">No parents added yet</div>
                        <a href="{{ route('admin.parents.create') }}" class="btn btn-primary btn-sm mt-3">Add Parent</a>
                    </div>
                </td>
            </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($parents->hasPages())
    <div class="card-footer">{{ $parents->links() }}</div>
    @endif
</div>

{{-- Delete Modal --}}
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Parent</h5>
                <button aria-label="Close dialog" type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">Delete <strong id="deleteName"></strong>? This will also delete their login account.</div>
            <div class="modal-footer">
                <form id="deleteForm" method="POST">
                    @csrf @method('DELETE')
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('deleteModal').addEventListener('show.bs.modal', function(e) {
    var btn = e.relatedTarget;
    document.getElementById('deleteForm').action = btn.dataset.action;
    document.getElementById('deleteName').textContent = btn.dataset.name;
});
</script>
@endpush
