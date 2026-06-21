@extends('layouts.admin')

@section('title', 'Enrollment Confirmations')

@section('content')
@php
    $filterSummary = collect([
        request('program_id') ? 'Program filtered' : null,
        request('batch_id') ? 'Batch filtered' : null,
    ])->filter()->implode(' | ') ?: 'All visible completed enrollments';
@endphp
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-0">Enrollment Confirmations</h2>
        <div class="text-muted small">Applicants converted to student records and ready for Academics handoff</div>
        <div class="small text-muted">Filter: {{ $filterSummary }}</div>
    </div>
</div>

<div class="alert alert-info border-0 shadow-sm mb-4">
    <div class="fw-semibold mb-2"><i class="bi bi-person-check me-1"></i>Enrollment-to-student control sequence</div>
    <div class="d-flex flex-wrap gap-2 small">
        <span class="badge bg-light text-dark">1. Selected applicant</span>
        <span class="badge bg-light text-dark">2. Verified admission payment</span>
        <span class="badge bg-light text-dark">3. Mandatory documents verified</span>
        <span class="badge bg-light text-dark">4. Roll number assigned</span>
        <span class="badge bg-light text-dark">5. Student profile created</span>
        <span class="badge bg-light text-dark">6. Academics handoff reviewed</span>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-2 fw-bold text-success">{{ $totalEnrolled }}</div>
            <div class="small text-muted">Total Enrolled</div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-2 fw-bold text-primary">{{ $thisMonth }}</div>
            <div class="small text-muted">Enrolled This Month</div>
        </div>
    </div>
</div>

<form method="GET" class="row g-2 mb-4">
    <div class="col-sm-4">
        <label class="form-label small">Program</label>
        <select name="program_id" class="form-select form-select-sm">
            <option value="">All Programs</option>
            @foreach($programs as $p)
                <option value="{{ $p->id }}" @selected(request('program_id') == $p->id)>{{ $p->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-sm-4">
        <label class="form-label small">Batch</label>
        <select name="batch_id" class="form-select form-select-sm">
            <option value="">All Batches</option>
            @foreach($batches as $b)
                <option value="{{ $b->id }}" @selected(request('batch_id') == $b->id)>{{ $b->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-sm-2">
        <button class="btn btn-sm btn-primary w-100">Filter</button>
    </div>
    <div class="col-sm-2">
        <a href="{{ route('admission.enrollment.index') }}" class="btn btn-sm btn-outline-secondary w-100">Clear</a>
    </div>
</form>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3 small d-flex flex-wrap gap-2 align-items-center">
        <span class="fw-semibold text-dark">Current view:</span>
        <span class="badge bg-light text-dark">{{ $filterSummary }}</span>
        <span class="badge bg-light text-dark">Rows: {{ $confirmations->total() }}</span>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Enrollment #</th>
                        <th>Student</th>
                        <th>Program</th>
                        <th>Batch</th>
                        <th>Roll #</th>
                        <th>Enrolled At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($confirmations as $c)
                    <tr>
                        <td><span class="font-monospace small">{{ $c->enrollment_number }}</span></td>
                        <td>{{ $c->student->user->name ?? ($c->applicant->user->name ?? 'Student name not linked') }}</td>
                        <td>{{ $c->applicant->program->name ?? 'Program not linked' }}</td>
                        <td>{{ $c->batch->name ?? 'Batch not linked' }}</td>
                        <td>{{ $c->roll_number ?: 'Roll number not assigned' }}</td>
                        <td>{{ $c->confirmed_at?->format('d M Y') ?? 'Confirmation date pending' }}</td>
                        <td>
                            <a href="{{ route('admission.enrollment.show', $c) }}" class="btn btn-sm btn-outline-primary">View</a>
                            <a href="{{ route('admission.enrollment.letter', $c) }}" class="btn btn-sm btn-outline-secondary" aria-label="Print enrollment letter"><i class="bi bi-printer"></i></a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <div class="fw-semibold text-dark mb-1">No completed enrollments match this view</div>
                            <div class="text-muted small mb-3">Check program/batch filters, then review selected applicants whose payment and mandatory document checks are complete.</div>
                            <div class="d-flex justify-content-center gap-2 flex-wrap">
                                @if(request('program_id') || request('batch_id'))
                                    <a href="{{ route('admission.enrollment.index') }}" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
                                @endif
                                <a href="{{ route('admission.applicants.index', ['status' => 'selected']) }}" class="btn btn-sm btn-primary">Open Selected Applicants</a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $confirmations->links() }}</div>
<div class="small text-muted mt-1">Showing {{ $confirmations->firstItem() ?? 0 }}-{{ $confirmations->lastItem() ?? 0 }} of {{ $confirmations->total() }}</div>
@endsection
