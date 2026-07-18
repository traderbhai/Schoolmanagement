@extends('layouts.admin')
@section('title', 'Student Grievances')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-chat-square-text me-2 text-primary"></i>Student Grievances</h4>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

{{-- Filters --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-sm-3">
                <label class="form-label form-label-sm mb-1">Status</label>
                <select aria-label="Status" name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach(['open','under_review','escalated','resolved','closed'] as $s)
                        <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucwords(str_replace('_',' ',$s)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-3">
                <label class="form-label form-label-sm mb-1">Priority</label>
                <select aria-label="Priority" name="priority" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach(['low','normal','high','urgent'] as $p)
                        <option value="{{ $p }}" @selected(request('priority') === $p)>{{ ucfirst($p) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-3">
                <button type="submit" class="btn btn-sm btn-outline-secondary me-1">Filter</button>
                <a href="{{ route('hod.grievances.index') }}" class="btn btn-sm btn-outline-light text-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Student</th>
                        <th scope="col">Category</th>
                        <th scope="col">Title</th>
                        <th scope="col">Priority</th>
                        <th scope="col">Status</th>
                        <th scope="col">Date</th>
                        <th aria-label="Actions" scope="col"></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($grievances as $g)
                    @php
                        $prioBadge = match($g->priority) {
                            'urgent' => 'danger',
                            'high'   => 'warning',
                            'normal' => 'primary',
                            default  => 'secondary',
                        };
                        $statBadge = match($g->status) {
                            'open'         => 'warning',
                            'under_review' => 'info',
                            'escalated'    => 'danger',
                            'resolved'     => 'success',
                            default        => 'secondary',
                        };
                    @endphp
                    <tr>
                        <td class="small fw-semibold">{{ $g->student?->user?->name ?? '—' }}</td>
                        <td class="small text-muted">{{ ucfirst($g->category) }}</td>
                        <td class="small">{{ $g->title }}</td>
                        <td><span class="badge bg-{{ $prioBadge }}">{{ ucfirst($g->priority) }}</span></td>
                        <td><span class="badge bg-{{ $statBadge }}">{{ ucwords(str_replace('_',' ',$g->status)) }}</span></td>
                        <td class="small text-muted">{{ $g->created_at->format('d M Y') }}</td>
                        <td>
                            <a href="{{ route('hod.grievances.show', $g) }}" class="btn btn-sm btn-outline-primary">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No grievances found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $grievances->links() }}</div>
@endsection
