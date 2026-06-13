@extends('layouts.admin')
@section('title', 'Department — ' . $department->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.departments.index') }}">Departments</a></li>
    <li class="breadcrumb-item active">{{ $department->name }}</li>
@endsection

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h5 class="mb-0 fw-bold">{{ $department->name }}</h5>
        <span class="badge bg-secondary">{{ $department->code }}</span>
        @if(!$department->is_active) <span class="badge bg-danger ms-1">Inactive</span> @endif
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.departments.edit', $department) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit</a>
        <a href="{{ route('admin.departments.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="card text-center border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold text-primary">{{ $department->courses_count }}</div>
                <div class="text-muted small">Courses</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card text-center border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold text-success">{{ $department->teachers_count }}</div>
                <div class="text-muted small">Faculty</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card text-center border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold text-info">{{ $department->students_count }}</div>
                <div class="text-muted small">Students</div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm" style="max-width:680px">
    <div class="card-header bg-transparent fw-semibold"><i class="bi bi-building me-2 text-primary"></i>Department Details</div>
    <div class="card-body">
        <table class="table table-borderless mb-0" style="font-size:.9rem">
            <tr><th class="text-muted" style="width:180px">Name</th><td>{{ $department->name }}</td></tr>
            <tr><th class="text-muted">Code</th><td><code>{{ $department->code }}</code></td></tr>
            <tr><th class="text-muted">Head of Dept</th><td>{{ $department->head_name ?? '—' }}</td></tr>
            <tr><th class="text-muted">Description</th><td>{{ $department->description ?? '—' }}</td></tr>
            <tr><th class="text-muted">Status</th><td>
                @if($department->is_active)
                    <span class="badge bg-success">Active</span>
                @else
                    <span class="badge bg-danger">Inactive</span>
                @endif
            </td></tr>
            <tr><th class="text-muted">Created</th><td>{{ $department->created_at->format('d M Y') }}</td></tr>
        </table>
    </div>
</div>
@endsection
