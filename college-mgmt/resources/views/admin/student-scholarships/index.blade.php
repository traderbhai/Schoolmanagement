@extends('layouts.admin')

@section('title', 'Student Scholarship Applications')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="mb-0 fw-bold"><i class="bi bi-award me-2 text-primary"></i>Student Scholarship Applications</h2>
        <small class="text-muted">Review enrolled-student scholarship requests, awards, and disbursements.</small>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-2">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-2 fw-bold text-warning">{{ $stats['pending'] }}</div>
            <div class="small text-muted">Pending</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-2 fw-bold text-info">{{ $stats['shortlisted'] }}</div>
            <div class="small text-muted">Shortlisted</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-2 fw-bold text-success">{{ $stats['approved'] }}</div>
            <div class="small text-muted">Approved</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-2 fw-bold text-primary">{{ $stats['disbursed'] }}</div>
            <div class="small text-muted">Disbursed</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-2 fw-bold text-success">Rs. {{ number_format($stats['approved_amount'], 0) }}</div>
            <div class="small text-muted">Approved or Disbursed Value</div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.student-scholarships.index') }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    @foreach(['pending', 'shortlisted', 'approved', 'rejected', 'disbursed'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Program</label>
                <select name="program_id" class="form-select form-select-sm">
                    <option value="">All Programs</option>
                    @foreach($programs as $program)
                        <option value="{{ $program->id }}" @selected(request('program_id') == $program->id)>{{ $program->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Student</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Name or email">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('admin.student-scholarships.index') }}" class="btn btn-outline-secondary btn-sm flex-fill">Clear</a>
            </div>
        </form>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        <div class="fw-semibold mb-1">Please fix the scholarship action.</div>
        <ul class="mb-0 small">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent fw-semibold">Applications ({{ $applications->total() }})</div>

    @if($applications->isEmpty())
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-folder2-open fs-1"></i>
            <p class="mt-2 mb-0">No scholarship applications match the current filters.</p>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Student</th>
                        <th>Scheme</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th style="min-width:360px">Staff Action</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($applications as $application)
                    @php
                        $badge = match($application->status) {
                            'approved', 'disbursed' => 'success',
                            'rejected' => 'danger',
                            'shortlisted' => 'info',
                            default => 'warning text-dark',
                        };
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $application->student->user->name ?? 'Student' }}</div>
                            <div class="small text-muted">
                                {{ $application->student->enrollment_number ?? 'No enrollment number' }}
                                @if($application->student?->program)
                                    &bull; {{ $application->student->program->name }}
                                @endif
                            </div>
                            <div class="small text-muted">{{ $application->student->user->email ?? '' }}</div>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $application->scheme->name ?? 'Scholarship' }}</div>
                            <div class="small text-muted">
                                {{ $application->scheme?->scheme_code }}
                                @if($application->scheme)
                                    &bull; Max Rs. {{ number_format((float) $application->scheme->max_amount, 0) }}
                                    &bull; Seats {{ $application->scheme->seatsRemaining() ?? 'Unlimited' }}
                                @endif
                            </div>
                            <div class="small text-muted">CGPA: {{ $application->cgpa_at_application ?? 'Not available' }}</div>
                        </td>
                        <td class="small text-muted">{{ \Illuminate\Support\Str::limit($application->reason, 160) }}</td>
                        <td>
                            <span class="badge bg-{{ $badge }}">{{ ucfirst($application->status) }}</span>
                            @if($application->disbursed_amount)
                                <div class="small text-muted mt-1">Rs. {{ number_format((float) $application->disbursed_amount, 2) }}</div>
                            @endif
                            @if($application->reviewer)
                                <div class="small text-muted">By {{ $application->reviewer->name }}</div>
                            @endif
                        </td>
                        <td>
                            @if($application->status === 'pending')
                                <form method="POST" action="{{ route('admin.student-scholarships.shortlist', $application) }}" class="d-flex gap-2 mb-2">
                                    @csrf
                                    @method('PATCH')
                                    <input type="text" name="review_note" class="form-control form-control-sm" placeholder="Optional shortlist note">
                                    <button class="btn btn-sm btn-outline-info">Shortlist</button>
                                </form>
                            @endif

                            @if(in_array($application->status, ['pending', 'shortlisted'], true))
                                <form method="POST" action="{{ route('admin.student-scholarships.approve', $application) }}" class="d-flex gap-2 mb-2">
                                    @csrf
                                    @method('PATCH')
                                    <input type="number" name="disbursed_amount" class="form-control form-control-sm" min="1" step="0.01" placeholder="Amount" required>
                                    <input type="text" name="review_note" class="form-control form-control-sm" placeholder="Approval note">
                                    <button class="btn btn-sm btn-success">Approve</button>
                                </form>
                            @endif

                            @if($application->status === 'approved')
                                <form method="POST" action="{{ route('admin.student-scholarships.disburse', $application) }}" class="d-flex gap-2 mb-2">
                                    @csrf
                                    @method('PATCH')
                                    <input type="text" name="disbursement_ref" class="form-control form-control-sm" placeholder="UTR / reference" required>
                                    <input type="text" name="review_note" class="form-control form-control-sm" placeholder="Optional note">
                                    <button class="btn btn-sm btn-primary">Disburse</button>
                                </form>
                            @endif

                            @if($application->status !== 'disbursed' && $application->status !== 'rejected')
                                <form method="POST" action="{{ route('admin.student-scholarships.reject', $application) }}" class="d-flex gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <input type="text" name="review_note" class="form-control form-control-sm" placeholder="Rejection reason" required>
                                    <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Reject this scholarship application?')">Reject</button>
                                </form>
                            @else
                                <div class="small text-muted">{{ $application->review_note ?: 'No staff note recorded.' }}</div>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($applications->hasPages())
        <div class="card-footer bg-transparent">{{ $applications->links() }}</div>
    @endif
</div>
@endsection
