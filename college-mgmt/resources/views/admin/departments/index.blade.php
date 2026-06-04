@extends('layouts.admin')
@section('title','Departments')
@section('page-title','Departments')
@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>All Departments</span>
        <a href="{{ route('admin.departments.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i>Add Department</a>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Name</th><th>Code</th><th>Head</th><th>Courses</th><th>Teachers</th><th>Students</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @foreach($departments as $d)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td class="fw-semibold">{{ $d->name }}</td>
                <td><span class="badge bg-secondary">{{ $d->code }}</span></td>
                <td>{{ $d->head_name ?? '–' }}</td>
                <td>{{ $d->courses_count }}</td>
                <td>{{ $d->teachers_count }}</td>
                <td>{{ $d->students_count }}</td>
                <td><span class="badge {{ $d->is_active ? 'bg-success' : 'bg-danger' }}">{{ $d->is_active ? 'Active' : 'Inactive' }}</span></td>
                <td>
                    <a href="{{ route('admin.departments.edit', $d) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                    <form method="POST" action="{{ route('admin.departments.destroy', $d) }}" class="d-inline" onsubmit="return confirm('Delete?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">Del</button>
                    </form>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if($departments->hasPages())
    <div class="card-footer">{{ $departments->links() }}</div>
    @endif
</div>
@endsection
