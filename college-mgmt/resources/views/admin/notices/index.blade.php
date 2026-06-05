@extends('layouts.admin')
@section('title', 'Notices')
@section('page-title', 'Notice Board')
@section('content')

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h5 class="mb-0 fw-bold">Notice Board</h5>
        <div class="text-muted" style="font-size:.82rem">Manage announcements and notices</div>
    </div>
    <a href="{{ route('admin.notices.create') }}" class="btn btn-primary">
        <i class="bi bi-megaphone me-1"></i>New Notice
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Title</th>
                    <th>Audience</th>
                    <th>Publish Date</th>
                    <th>Expiry</th>
                    <th>Posted By</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($notices as $n)
            <tr>
                <td class="fw-semibold">{{ $n->title }}</td>
                <td>
                    <span class="badge {{ match($n->audience){'all'=>'bg-primary','students'=>'badge-active','teachers'=>'badge-paid','admin'=>'bg-secondary',default=>'bg-secondary'} }}">
                        {{ ucfirst($n->audience) }}
                    </span>
                </td>
                <td>{{ $n->publish_date->format('d M Y') }}</td>
                <td>{{ $n->expiry_date ? $n->expiry_date->format('d M Y') : '<span class="text-muted">–</span>' }}</td>
                <td>{{ $n->user->name }}</td>
                <td>
                    <span class="badge {{ $n->is_published ? 'badge-active' : 'bg-secondary' }}">
                        {{ $n->is_published ? 'Published' : 'Draft' }}
                    </span>
                </td>
                <td>
                    <div class="d-flex gap-1">
                        <a href="{{ route('admin.notices.edit', $n) }}" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                        <button type="button" class="btn btn-sm btn-outline-danger" title="Delete"
                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                            data-action="{{ route('admin.notices.destroy', $n) }}"
                            data-name="{{ $n->title }}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7">
                    <div class="empty-state py-5">
                        <div class="empty-icon"><i class="bi bi-megaphone"></i></div>
                        <div class="mt-2 fw-semibold">No notices posted yet</div>
                        <a href="{{ route('admin.notices.create') }}" class="btn btn-primary btn-sm mt-3">Create Notice</a>
                    </div>
                </td>
            </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($notices->hasPages())
    <div class="card-footer">{{ $notices->links() }}</div>
    @endif
</div>
@endsection
