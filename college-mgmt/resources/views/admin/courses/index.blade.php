@extends('layouts.admin')
@section('title', 'Courses')
@section('page-title', 'Courses')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Courses</li>
@endsection

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h5 class="mb-0 fw-bold">Courses</h5>
        <div class="text-muted" style="font-size:.82rem">Manage academic programmes</div>
    </div>
    <a href="{{ route('admin.courses.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Add Course
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col">Code</th>
                    <th scope="col">Name</th>
                    <th scope="col">Department</th>
                    <th scope="col">Duration</th>
                    <th scope="col">Total Students</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($courses as $c)
            <tr>
                <td><span class="badge bg-primary">{{ $c->code }}</span></td>
                <td class="fw-semibold">{{ $c->name }}</td>
                <td>{{ $c->department->name }}</td>
                <td>{{ $c->duration_years }} yr / {{ $c->total_semesters }} sem</td>
                <td>{{ $c->students_count }}</td>
                <td>
                    <span class="badge {{ $c->is_active ? 'badge-active' : 'badge-danger' }}">{{ $c->is_active ? 'Active' : 'Inactive' }}</span>
                </td>
                <td>
                    <div class="d-flex gap-1">
                        <a href="{{ route('admin.courses.show', $c) }}" class="btn btn-sm btn-outline-secondary" title="View" aria-label="View course {{ $c->name }}"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('admin.courses.edit', $c) }}" class="btn btn-sm btn-outline-primary" title="Edit" aria-label="Edit course {{ $c->name }}"><i class="bi bi-pencil"></i></a>
                        <button type="button" class="btn btn-sm btn-outline-danger" title="Delete"
                            aria-label="Delete course {{ $c->name }}"
                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                            data-action="{{ route('admin.courses.destroy', $c) }}"
                            data-name="{{ $c->name }}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7">
                    <div class="empty-state py-5">
                        <div class="empty-icon"><i class="bi bi-journal-bookmark"></i></div>
                        <div class="mt-2 fw-semibold">No courses found</div>
                        <a href="{{ route('admin.courses.create') }}" class="btn btn-primary btn-sm mt-3">Add Course</a>
                    </div>
                </td>
            </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($courses->hasPages())
    <div class="card-footer">{{ $courses->links() }}</div>
    @endif
</div>
@endsection
