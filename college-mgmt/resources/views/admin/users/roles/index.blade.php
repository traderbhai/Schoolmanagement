@extends('layouts.admin')
@section('title', 'Role Assignments')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Role Assignments</h4>
            <span class="text-muted small">All user-role assignments across the institution</span>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.roles.hierarchy') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-diagram-3 me-1"></i> Hierarchy
            </a>
            <a href="{{ route('admin.users.roles.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-person-plus me-1"></i> Assign Role
            </a>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif
    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">User</th>
                            <th>Role</th>
                            <th>Program Scope</th>
                            <th>Assigned By</th>
                            <th>Active Until</th>
                            <th>Status</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($userRoles as $ur)
                        <tr>
                            <td class="ps-3">
                                <div class="fw-medium">{{ $ur->user->name ?? '—' }}</div>
                                <div class="text-muted small">{{ $ur->user->email ?? '' }}</div>
                            </td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary rounded-pill px-2">
                                    {{ $ur->role->name ?? '—' }}
                                </span>
                            </td>
                            <td>
                                @if($ur->program)
                                <span class="badge bg-info-subtle text-info rounded-pill px-2">
                                    {{ $ur->program->name }}
                                </span>
                                @else
                                <span class="text-muted small">Global</span>
                                @endif
                            </td>
                            <td class="small text-muted">{{ $ur->assignedBy->name ?? 'System' }}</td>
                            <td class="small">
                                @if($ur->active_until)
                                    {{ $ur->active_until->format('d M Y') }}
                                @else
                                    <span class="text-muted">No expiry</span>
                                @endif
                            </td>
                            <td>
                                @if($ur->isActive())
                                <span class="badge bg-success-subtle text-success">Active</span>
                                @else
                                <span class="badge bg-secondary-subtle text-secondary">Expired</span>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <form method="POST" action="{{ route('admin.users.roles.destroy', $ur) }}"
                                      onsubmit="return confirm('Revoke this role assignment?')" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No role assignments found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($userRoles->hasPages())
    <div class="mt-3">
        {{ $userRoles->links() }}
    </div>
    @endif
</div>
@endsection
