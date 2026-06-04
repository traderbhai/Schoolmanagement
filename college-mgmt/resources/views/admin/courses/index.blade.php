@extends('layouts.admin')
@section('title','Courses')
@section('page-title','Courses')
@section('content')
<div class="d-flex justify-content-end mb-3">
    <a href="{{ route('admin.courses.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Add Course</a>
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Name</th><th>Code</th><th>Department</th><th>Duration</th><th>Semesters</th><th>Students</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @foreach($courses as $c)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td class="fw-semibold">{{ $c->name }}</td>
                <td><span class="badge bg-secondary">{{ $c->code }}</span></td>
                <td>{{ $c->department->name }}</td>
                <td>{{ $c->duration_years }} yr</td>
                <td>{{ $c->total_semesters }}</td>
                <td>{{ $c->students_count }}</td>
                <td><span class="badge {{ $c->is_active ? 'bg-success' : 'bg-danger' }}">{{ $c->is_active ? 'Active' : 'Inactive' }}</span></td>
                <td>
                    <a href="{{ route('admin.courses.show', $c) }}" class="btn btn-sm btn-outline-info">View</a>
                    <a href="{{ route('admin.courses.edit', $c) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                    <form method="POST" action="{{ route('admin.courses.destroy', $c) }}" class="d-inline" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Del</button></form>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if($courses->hasPages())<div class="card-footer">{{ $courses->links() }}</div>@endif
</div>
@endsection
