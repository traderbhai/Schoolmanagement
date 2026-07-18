@extends('layouts.admin')
@section('title', 'Notices')
@section('page-title', 'Notice Board')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Notices</li>
@endsection

@section('content')

<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h5 class="mb-1 fw-bold">Notice Board</h5>
        <div class="text-muted" style="font-size:.82rem">Publish campus announcements, review visibility, and archive outdated communication without deleting history.</div>
        <div class="mt-2 d-flex flex-wrap gap-2">
            <span class="badge text-bg-light">Owner: Admin / Director</span>
            <span class="badge text-bg-light">Source: official notices with audience, publish date, expiry, and posted-by records</span>
        </div>
    </div>
    <a href="{{ route('admin.notices.create') }}" class="btn btn-primary">
        <i class="bi bi-megaphone me-1"></i>New Notice
    </a>
</div>

<div class="alert alert-light border d-flex flex-wrap align-items-center gap-2 py-2 px-3">
    <span class="fw-semibold text-sm">Notice workflow:</span>
    <span class="text-muted text-sm">1. choose audience</span>
    <span class="text-muted text-sm">2. set publish/expiry window</span>
    <span class="text-muted text-sm">3. publish or keep draft</span>
    <span class="text-muted text-sm">4. archive corrections instead of deleting communication history</span>
</div>

<div class="card mb-3">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('admin.notices.index') }}" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Search notices</label>
                <input aria-label="Title or notice body" type="search" name="search" class="form-control form-control-sm" value="{{ $filters['search'] ?? '' }}" placeholder="Title or notice body">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Audience</label>
                <select aria-label="Audience" name="audience" class="form-select form-select-sm">
                    <option value="">All audiences</option>
                    @foreach(['all' => 'All users', 'students' => 'Students / parents', 'teachers' => 'Teachers', 'admin' => 'Admin / staff'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['audience'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Status</label>
                <select aria-label="Status" name="status" class="form-select form-select-sm">
                    <option value="">All statuses</option>
                    @foreach(['active' => 'Active now', 'published' => 'Published', 'draft' => 'Draft', 'scheduled' => 'Scheduled', 'expired' => 'Expired'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-fill">Apply filters</button>
                <a href="{{ route('admin.notices.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
            </div>
        </form>
        <div class="text-muted small mt-2">
            Showing {{ $notices->total() }} notice(s)
            @if(($filters['search'] ?? '') !== '') for "{{ $filters['search'] }}" @endif
            @if(($filters['audience'] ?? '') !== '') with audience "{{ str_replace('_', ' ', $filters['audience']) }}" @endif
            @if(($filters['status'] ?? '') !== '') and status "{{ str_replace('_', ' ', $filters['status']) }}" @endif.
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center py-2">
        <span class="fw-semibold text-sm">Official notice source list</span>
        <span class="text-muted small">Filtered result total: {{ $notices->total() }}</span>
    </div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col">Title</th>
                    <th scope="col">Audience</th>
                    <th scope="col">Publish Date</th>
                    <th scope="col">Expiry</th>
                    <th scope="col">Posted By</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($notices as $n)
            <tr>
                <td>
                    <div class="fw-semibold">{{ $n->title }}</div>
                    <div class="text-muted small">Source: {{ optional($n->user)->name ?? 'Poster not recorded' }}</div>
                </td>
                <td>
                    <span class="badge {{ match($n->audience){'all'=>'bg-primary','students'=>'badge-active','teachers'=>'badge-paid','admin'=>'bg-secondary',default=>'bg-secondary'} }}">
                        {{ match($n->audience) { 'all' => 'All users', 'students' => 'Students / parents', 'teachers' => 'Teachers', 'admin' => 'Admin / staff', default => ucfirst($n->audience) } }}
                    </span>
                </td>
                <td>{{ $n->publish_date->format('d M Y') }}</td>
                <td>{{ $n->expiry_date ? $n->expiry_date->format('d M Y') : 'No expiry set' }}</td>
                <td>{{ optional($n->user)->name ?? 'Poster not recorded' }}</td>
                <td>
                    <span class="badge {{ $n->is_published ? 'badge-active' : 'bg-secondary' }}">
                        {{ $n->is_published ? 'Published' : 'Draft' }}
                    </span>
                    @if($n->is_published && $n->publish_date->gt(now()))
                        <span class="badge bg-info text-dark">Scheduled</span>
                    @elseif($n->expiry_date && $n->expiry_date->lt(now()))
                        <span class="badge bg-warning text-dark">Expired</span>
                    @elseif($n->is_published)
                        <span class="badge bg-success">Visible now</span>
                    @endif
                </td>
                <td>
                    <div class="d-flex gap-1">
                        <a href="{{ route('admin.notices.show', $n) }}" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('admin.notices.edit', $n) }}" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                        <button type="button" class="btn btn-sm btn-outline-danger" title="Archive"
                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                            data-action="{{ route('admin.notices.destroy', $n) }}"
                            data-name="{{ $n->title }}">
                            <i class="bi bi-archive"></i>
                        </button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7">
                    <div class="empty-state py-5">
                        <div class="empty-icon"><i class="bi bi-megaphone"></i></div>
                        <div class="mt-2 fw-semibold">No notices match the current source filters</div>
                        <div class="text-muted text-sm mt-1">Create a notice if the institute has no announcement yet, or clear filters to review drafts, scheduled notices, expired notices, and audience-specific notices.</div>
                        <div class="d-flex justify-content-center gap-2 mt-3">
                            <a href="{{ route('admin.notices.create') }}" class="btn btn-primary btn-sm">Create Notice</a>
                            <a href="{{ route('admin.notices.index') }}" class="btn btn-outline-secondary btn-sm">Clear Filters</a>
                        </div>
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
