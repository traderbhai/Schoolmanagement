@extends('layouts.admin')
@section('title', 'Semesters')
@section('page-title', 'Semesters')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Semesters</li>
@endsection

@section('content')

@include('admin.partials.setup-sequence')

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h5 class="mb-0 fw-bold">Semesters</h5>
        <div class="text-muted" style="font-size:.82rem">Manage semester periods</div>
    </div>
    <a href="{{ route('admin.semesters.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Add Semester
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Name</th>
                    <th scope="col">Academic Year</th>
                    <th scope="col">Start</th>
                    <th scope="col">End</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($semesters as $s)
            <tr>
                <td class="text-muted">{{ $loop->iteration }}</td>
                <td class="fw-semibold">{{ $s->name }}</td>
                <td>{{ $s->academicYear->name }}</td>
                <td>{{ $s->start_date->format('d M Y') }}</td>
                <td>{{ $s->end_date->format('d M Y') }}</td>
                <td>
                    @if($s->is_current)
                        <span class="badge badge-active"><i class="bi bi-check-circle me-1"></i>Current</span>
                    @else
                        <span class="badge bg-secondary">–</span>
                    @endif
                </td>
                <td>
                    <div class="d-flex gap-1">
                        <a href="{{ route('admin.semesters.edit', $s) }}" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                        <button type="button" class="btn btn-sm btn-outline-danger" title="Delete"
                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                            data-action="{{ route('admin.semesters.destroy', $s) }}"
                            data-name="{{ $s->name }}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7">
                    <div class="empty-state py-5">
                        <div class="empty-icon"><i class="bi bi-calendar3"></i></div>
                        <div class="mt-2 fw-semibold">No semesters added yet</div>
                        <a href="{{ route('admin.semesters.create') }}" class="btn btn-primary btn-sm mt-3">Add Semester</a>
                    </div>
                </td>
            </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($semesters->hasPages())
    <div class="card-footer">{{ $semesters->links() }}</div>
    @endif
</div>
@endsection
