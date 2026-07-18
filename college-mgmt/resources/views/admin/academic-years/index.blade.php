@extends('layouts.admin')
@section('title', 'Academic Years')
@section('page-title', 'Academic Years')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Academic Years</li>
@endsection

@section('content')

@include('admin.partials.setup-sequence')

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h5 class="mb-0 fw-bold">Academic Years</h5>
        <div class="text-muted" style="font-size:.82rem">Manage academic year records</div>
    </div>
    <a href="{{ route('admin.academic-years.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Add Academic Year
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Name</th>
                    <th scope="col">Start Date</th>
                    <th scope="col">End Date</th>
                    <th scope="col">Semesters</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($years as $y)
            <tr>
                <td class="text-muted">{{ $loop->iteration }}</td>
                <td class="fw-semibold">{{ $y->name }}</td>
                <td>{{ $y->start_date->format('d M Y') }}</td>
                <td>{{ $y->end_date->format('d M Y') }}</td>
                <td>{{ $y->semesters_count }}</td>
                <td>
                    @if($y->is_current)
                        <span class="badge badge-active"><i class="bi bi-check-circle me-1"></i>Current</span>
                    @else
                        <span class="badge bg-secondary">Past</span>
                    @endif
                </td>
                <td>
                    <div class="d-flex gap-1">
                        <a href="{{ route('admin.academic-years.show', $y) }}" class="btn btn-sm btn-outline-secondary" title="View" aria-label="View academic year {{ $y->name }}"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('admin.academic-years.edit', $y) }}" class="btn btn-sm btn-outline-primary" title="Edit" aria-label="Edit academic year {{ $y->name }}"><i class="bi bi-pencil"></i></a>
                        <button type="button" class="btn btn-sm btn-outline-danger" title="Delete"
                            aria-label="Delete academic year {{ $y->name }}"
                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                            data-action="{{ route('admin.academic-years.destroy', $y) }}"
                            data-name="{{ $y->name }}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7">
                    <div class="empty-state py-5">
                        <div class="empty-icon"><i class="bi bi-calendar-range"></i></div>
                        <div class="mt-2 fw-semibold">No academic years added yet</div>
                        <a href="{{ route('admin.academic-years.create') }}" class="btn btn-primary btn-sm mt-3">Add Academic Year</a>
                    </div>
                </td>
            </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($years->hasPages())
    <div class="card-footer">{{ $years->links() }}</div>
    @endif
</div>
@endsection
