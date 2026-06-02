@extends('layouts.admin')
@section('title','Notices')
@section('page-title','Notice Board')
@section('content')
<div class="d-flex justify-content-end mb-3">
    <a href="{{ route('admin.notices.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>New Notice</a>
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Title</th><th>Audience</th><th>Publish Date</th><th>Expiry</th><th>By</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($notices as $n)
            <tr>
                <td>{{ $notices->firstItem() + $loop->index }}</td>
                <td class="fw-semibold">{{ $n->title }}</td>
                <td><span class="badge bg-info text-dark">{{ ucfirst($n->audience) }}</span></td>
                <td>{{ $n->publish_date->format('d M Y') }}</td>
                <td>{{ $n->expiry_date ? $n->expiry_date->format('d M Y') : '–' }}</td>
                <td>{{ $n->user->name }}</td>
                <td><span class="badge {{ $n->is_published ? 'bg-success' : 'bg-secondary' }}">{{ $n->is_published ? 'Published' : 'Draft' }}</span></td>
                <td>
                    <a href="{{ route('admin.notices.edit', $n) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                    <form method="POST" action="{{ route('admin.notices.destroy', $n) }}" class="d-inline" onsubmit="return confirm('Delete?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">Del</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="8" class="text-center text-muted py-4">No notices.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($notices->hasPages())<div class="card-footer">{{ $notices->links() }}</div>@endif
</div>
@endsection
