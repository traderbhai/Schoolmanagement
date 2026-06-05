@extends('layouts.admin')
@section('title', 'Applicants')

@section('content')
<div class="container-fluid p-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Applications</h4>
            <p class="text-muted small mb-0">Manage all applicants</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createApplicantModal">
            <i class="bi bi-person-plus me-2"></i>Add Applicant
        </button>
    </div>

    {{-- Filters --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Program</label>
                    <select name="program_id" class="form-select form-select-sm">
                        <option value="">All Programs</option>
                        @foreach($programs as $p)
                            <option value="{{ $p->id }}" {{ request('program_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Batch</label>
                    <select name="batch_id" class="form-select form-select-sm">
                        <option value="">All Batches</option>
                        @foreach($batches as $b)
                            <option value="{{ $b->id }}" {{ request('batch_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        @foreach($statuses as $s)
                            <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $s)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary btn-sm me-2">Filter</button>
                    <a href="{{ route('admin.applicants.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Table --}}
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Application #</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Program</th>
                        <th>Batch</th>
                        <th>Status</th>
                        <th>Applied At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($applicants as $applicant)
                    <tr>
                        <td><code class="small">{{ $applicant->application_number }}</code></td>
                        <td class="fw-semibold">{{ $applicant->user?->name ?? '—' }}</td>
                        <td class="text-muted small">{{ $applicant->user?->email ?? '—' }}</td>
                        <td>{{ $applicant->program->name }}</td>
                        <td>{{ $applicant->batch?->name ?? '—' }}</td>
                        <td><span class="{{ $applicant->status_badge }}">{{ $applicant->status_label }}</span></td>
                        <td class="text-muted small">{{ $applicant->applied_at?->format('d M Y') ?? 'Not submitted' }}</td>
                        <td>
                            <a href="{{ route('admin.applicants.show', $applicant) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No applicants found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($applicants->hasPages())
        <div class="card-footer">
            {{ $applicants->links() }}
        </div>
        @endif
    </div>

</div>

{{-- Create Applicant Modal --}}
<div class="modal fade" id="createApplicantModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Applicant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('admin.applicants.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Phone</label>
                        <input type="tel" name="phone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Program <span class="text-danger">*</span></label>
                        <select name="program_id" class="form-select" required>
                            <option value="">— Select Program —</option>
                            @foreach($programs as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Batch</label>
                        <select name="batch_id" class="form-select">
                            <option value="">— Select Batch (optional) —</option>
                            @foreach($batches as $b)
                                <option value="{{ $b->id }}">{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p class="text-muted small"><i class="bi bi-info-circle me-1"></i>Default password will be: <code>password123</code></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Applicant</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
