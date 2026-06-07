@extends('layouts.student')
@section('title', 'My Grievances')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-chat-square-text me-2 text-primary"></i>My Grievances</h4>
    <a href="{{ route('student.grievances.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>Submit New Grievance
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($grievances as $g)
                    @php
                        $prioBadge = match($g->priority) { 'urgent'=>'danger','high'=>'warning','normal'=>'primary',default=>'secondary' };
                        $statBadge = match($g->status) { 'open'=>'warning','under_review'=>'info','escalated'=>'danger','resolved'=>'success',default=>'secondary' };
                    @endphp
                    <tr>
                        <td class="fw-semibold small">{{ $g->title }}</td>
                        <td class="small text-muted">{{ ucfirst($g->category) }}</td>
                        <td><span class="badge bg-{{ $prioBadge }}">{{ ucfirst($g->priority) }}</span></td>
                        <td><span class="badge bg-{{ $statBadge }}">{{ ucwords(str_replace('_',' ',$g->status)) }}</span></td>
                        <td class="small text-muted">{{ $g->created_at->format('d M Y') }}</td>
                        <td><a href="{{ route('student.grievances.show', $g) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No grievances submitted yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
