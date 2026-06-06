@extends('layouts.student')
@section('title', 'My Grievances')
@section('page-title', 'Grievances')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('student.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Grievances</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <p class="text-muted mb-0" style="font-size:.88rem">
            Submit and track complaints or concerns about academic, fee, or facility matters.
        </p>
    </div>
    <a href="{{ route('student.grievances.create') }}" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-lg me-1"></i>New Grievance
    </a>
</div>

@if($grievances->isEmpty())
<div class="card" style="box-shadow:var(--shadow-sm)">
    <div class="card-body text-center py-5">
        <i class="bi bi-shield-check" style="font-size:3rem;color:var(--clr-text-muted)"></i>
        <h5 class="mt-3" style="color:var(--clr-text-muted)">No Grievances Submitted</h5>
        <p class="mb-3" style="color:var(--clr-text-muted);font-size:.88rem">
            If you have a complaint or concern, submit a grievance and we'll look into it.
        </p>
        <a href="{{ route('student.grievances.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Submit a Grievance
        </a>
    </div>
</div>
@else
<div class="card" style="box-shadow:var(--shadow-sm)">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Subject</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($grievances as $g)
                    <tr>
                        <td class="ps-3 fw-medium small">{{ $g->subject }}</td>
                        <td>
                            <span class="badge bg-secondary-subtle text-secondary" style="font-size:.7rem">
                                {{ ucfirst($g->category) }}
                            </span>
                        </td>
                        <td>{!! $g->priority_badge !!}</td>
                        <td>{!! $g->status_badge !!}</td>
                        <td class="text-muted small">{{ $g->created_at->format('d M Y') }}</td>
                        <td>
                            <a href="{{ route('student.grievances.show', $g) }}"
                               class="btn btn-sm btn-outline-secondary py-0 px-2">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@if($grievances->hasPages())
<div class="mt-3">{{ $grievances->links() }}</div>
@endif
@endif
@endsection
