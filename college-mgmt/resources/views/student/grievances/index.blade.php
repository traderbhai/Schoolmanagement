@extends('layouts.student')
@section('title', 'My Grievances')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h4 class="mb-0"><i class="bi bi-chat-square-text me-2 text-primary"></i>My Grievances</h4>
    <a href="{{ route('student.grievances.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>Submit New Grievance
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="text-uppercase text-muted fw-semibold mb-1" style="font-size:.72rem;letter-spacing:.04em">Grievance Priority</div>
            <h5 class="fw-bold mb-1">{{ $grievancePriority['title'] }}</h5>
            <p class="text-muted mb-0">{{ $grievancePriority['body'] }}</p>
        </div>
        <a href="{{ $grievancePriority['route'] }}" class="btn btn-sm {{ $grievancePriority['level'] === 'danger' ? 'btn-danger' : ($grievancePriority['level'] === 'warning' ? 'btn-warning' : 'btn-primary') }}">
            <i class="bi bi-arrow-right-circle me-1"></i>{{ $grievancePriority['action'] }}
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Title</th>
                        <th scope="col">Category</th>
                        <th scope="col">Priority</th>
                        <th scope="col">Status</th>
                        <th scope="col">Submitted</th>
                        <th scope="col" class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($grievances as $g)
                    <tr>
                        <td class="fw-semibold small">{{ $g->title }}</td>
                        <td class="small text-muted">{{ ucfirst($g->category) }}</td>
                        <td>{!! $g->priority_badge !!}</td>
                        <td>{!! $g->status_badge !!}</td>
                        <td class="small text-muted">{{ $g->created_at->format('d M Y') }}</td>
                        <td class="text-end"><a href="{{ route('student.grievances.show', $g) }}" class="btn btn-sm btn-outline-primary">View</a></td>
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
