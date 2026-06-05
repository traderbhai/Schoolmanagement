@extends('layouts.admin')
@section('title', 'Teachers')
@section('page-title', 'Teachers')
@section('content')

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h5 class="mb-0 fw-bold">Teachers</h5>
        <div class="text-muted" style="font-size:.82rem">Manage faculty members and assignments</div>
    </div>
    <a href="{{ route('admin.teachers.create') }}" class="btn btn-primary">
        <i class="bi bi-person-plus me-1"></i>Add Teacher
    </a>
</div>

<div class="card mb-4">
    <div class="card-body py-3">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-md-4">
                <label class="form-label form-label-sm mb-1">Search</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Name or Employee ID…" value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label form-label-sm mb-1">Department</label>
                <select name="department_id" class="form-select form-select-sm">
                    <option value="">All Departments</option>
                    @foreach($departments as $d)
                        <option value="{{ $d->id }}" @selected(request('department_id')==$d->id)>{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-primary btn-sm">Search</button>
                @if(request()->hasAny(['search','department_id']))
                    <a href="{{ route('admin.teachers.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Teacher</th>
                    <th>Employee ID</th>
                    <th>Department</th>
                    <th>Designation</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($teachers as $t)
            <tr>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:36px;height:36px;border-radius:50%;background:#10b981;color:white;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:.82rem;flex-shrink:0">{{ strtoupper(substr($t->user->name,0,1)) }}</div>
                        <div>
                            <a href="{{ route('admin.teachers.show', $t) }}" class="text-decoration-none fw-semibold" style="color:var(--clr-text)">{{ $t->user->name }}</a>
                            <div class="text-muted" style="font-size:.78rem">{{ $t->user->email }}</div>
                        </div>
                    </div>
                </td>
                <td><code>{{ $t->employee_id }}</code></td>
                <td>{{ $t->department->code }}</td>
                <td>{{ $t->designation ?? '–' }}</td>
                <td><span class="badge bg-light text-dark border">{{ ucfirst(str_replace('_',' ',$t->employment_type)) }}</span></td>
                <td>
                    <span class="badge {{ $t->status === 'active' ? 'badge-active' : 'badge-pending' }}">{{ ucfirst($t->status) }}</span>
                </td>
                <td>
                    <div class="d-flex gap-1">
                        <a href="{{ route('admin.teachers.show', $t) }}" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('admin.teachers.edit', $t) }}" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                        <button type="button" class="btn btn-sm btn-outline-danger" title="Delete"
                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                            data-action="{{ route('admin.teachers.destroy', $t) }}"
                            data-name="{{ $t->user->name }}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7">
                    <div class="empty-state py-5">
                        <div class="empty-icon"><i class="bi bi-person-badge"></i></div>
                        <div class="mt-2 fw-semibold">No teachers found</div>
                        <a href="{{ route('admin.teachers.create') }}" class="btn btn-primary btn-sm mt-3"><i class="bi bi-person-plus me-1"></i>Add Teacher</a>
                    </div>
                </td>
            </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($teachers->hasPages())
    <div class="card-footer">{{ $teachers->links() }}</div>
    @endif
</div>
@endsection
