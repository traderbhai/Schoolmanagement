@extends('layouts.admin')
@section('title', 'Subjects')
@section('page-title', 'Subjects')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Subjects</li>
@endsection

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h5 class="mb-0 fw-bold">Subjects</h5>
        <div class="text-muted" style="font-size:.82rem">Manage subject catalogue</div>
    </div>
    <a href="{{ route('admin.subjects.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Add Subject
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
                    <th scope="col">Credits</th>
                    <th scope="col">Type</th>
                    <th scope="col">Hrs/Week</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($subjects as $s)
            <tr>
                <td><code>{{ $s->code }}</code></td>
                <td class="fw-semibold">{{ $s->name }}</td>
                <td>{{ $s->department->code }}</td>
                <td><span class="badge bg-primary">{{ $s->credits }} cr</span></td>
                <td>
                    <span class="badge {{ match($s->type){'theory'=>'bg-info text-dark','practical'=>'bg-warning text-dark','tutorial'=>'bg-secondary',default=>'bg-secondary'} }}">
                        {{ ucfirst($s->type) }}
                    </span>
                </td>
                <td>{{ $s->hours_per_week }}</td>
                <td><span class="badge {{ $s->is_active ? 'badge-active' : 'badge-danger' }}">{{ $s->is_active ? 'Active' : 'Inactive' }}</span></td>
                <td>
                    <div class="d-flex gap-1">
                        <a href="{{ route('admin.subjects.edit', $s) }}" class="btn btn-sm btn-outline-primary" title="Edit" aria-label="Edit subject {{ $s->name }}"><i class="bi bi-pencil"></i></a>
                        <button type="button" class="btn btn-sm btn-outline-danger" title="Delete"
                            aria-label="Delete subject {{ $s->name }}"
                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                            data-action="{{ route('admin.subjects.destroy', $s) }}"
                            data-name="{{ $s->name }}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8">
                    <div class="empty-state py-5">
                        <div class="empty-icon"><i class="bi bi-book"></i></div>
                        <div class="mt-2 fw-semibold">No subjects found</div>
                        <a href="{{ route('admin.subjects.create') }}" class="btn btn-primary btn-sm mt-3">Add Subject</a>
                    </div>
                </td>
            </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($subjects->hasPages())
    <div class="card-footer">{{ $subjects->links() }}</div>
    @endif
</div>
@endsection
