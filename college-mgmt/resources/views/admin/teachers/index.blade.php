@extends('layouts.admin')
@section('title','Teachers')
@section('page-title','Teachers')
@section('content')
<div class="card mb-3">
    <div class="card-body py-2">
        <form class="row g-2" method="GET">
            <div class="col-md-3"><input type="text" name="search" class="form-control form-control-sm" placeholder="Name or Employee ID..." value="{{ request('search') }}"></div>
            <div class="col-md-3">
                <select name="department_id" class="form-select form-select-sm">
                    <option value="">All Departments</option>
                    @foreach($departments as $d)<option value="{{ $d->id }}" @selected(request('department_id')==$d->id)>{{ $d->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-primary btn-sm">Search</button></div>
            <div class="col-auto ms-auto"><a href="{{ route('admin.teachers.create') }}" class="btn btn-success btn-sm"><i class="bi bi-person-plus me-1"></i>Add Teacher</a></div>
        </form>
    </div>
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Name</th><th>Employee ID</th><th>Dept</th><th>Designation</th><th>Type</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($teachers as $t)
            <tr>
                <td>{{ $teachers->firstItem() + $loop->index }}</td>
                <td>
                    <a href="{{ route('admin.teachers.show', $t) }}" class="text-decoration-none fw-semibold">{{ $t->user->name }}</a>
                    <div class="text-muted small">{{ $t->user->email }}</div>
                </td>
                <td><code>{{ $t->employee_id }}</code></td>
                <td>{{ $t->department->code }}</td>
                <td>{{ $t->designation ?? '–' }}</td>
                <td>{{ ucfirst(str_replace('_',' ',$t->employment_type)) }}</td>
                <td><span class="badge {{ $t->status === 'active' ? 'bg-success' : 'bg-warning text-dark' }}">{{ ucfirst($t->status) }}</span></td>
                <td>
                    <a href="{{ route('admin.teachers.show', $t) }}" class="btn btn-sm btn-outline-info">View</a>
                    <a href="{{ route('admin.teachers.edit', $t) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="8" class="text-center text-muted py-4">No teachers found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($teachers->hasPages())
    <div class="card-footer">{{ $teachers->links() }}</div>
    @endif
</div>
@endsection
