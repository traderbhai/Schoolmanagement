@extends('layouts.admin')
@section('title', 'Departments')
@section('page-title', 'Departments')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Departments</li>
@endsection

@section('content')

@include('admin.partials.setup-sequence')

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h5 class="mb-0 fw-bold">Departments</h5>
        <div class="text-muted" style="font-size:.82rem">Manage academic departments</div>
    </div>
    <a href="{{ route('admin.departments.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Add Department
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Name</th>
                    <th scope="col">Code</th>
                    <th scope="col">Head of Dept</th>
                    <th scope="col">Students</th>
                    <th scope="col">Courses</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($departments as $d)
            <tr>
                <td class="text-muted">{{ $loop->iteration }}</td>
                <td class="fw-semibold">{{ $d->name }}</td>
                <td><span class="badge bg-light text-dark border fw-semibold">{{ $d->code }}</span></td>
                <td>{{ $d->head_name ?? '–' }}</td>
                <td>{{ $d->students_count }}</td>
                <td>{{ $d->courses_count }}</td>
                <td>
                    <div class="d-flex gap-1">
                        <a href="{{ route('admin.departments.edit', $d) }}" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                        <button type="button" class="btn btn-sm btn-outline-danger" title="Delete"
                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                            data-action="{{ route('admin.departments.destroy', $d) }}"
                            data-name="{{ $d->name }}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7">
                    <div class="empty-state py-5">
                        <div class="empty-icon"><i class="bi bi-building"></i></div>
                        <div class="mt-2 fw-semibold">No departments yet</div>
                        <a href="{{ route('admin.departments.create') }}" class="btn btn-primary btn-sm mt-3"><i class="bi bi-plus-circle me-1"></i>Add Department</a>
                    </div>
                </td>
            </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($departments->hasPages())
    <div class="card-footer">{{ $departments->links() }}</div>
    @endif
</div>
@endsection
