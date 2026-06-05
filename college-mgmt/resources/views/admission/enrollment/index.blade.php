@extends('layouts.admin')

@section('title', 'Enrollment Confirmations')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-0">Enrollment Confirmations</h2>
        <div class="text-muted small">Applicants converted to students</div>
    </div>
</div>

{{-- Stats --}}
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

{{-- Filters --}}
<form method="GET" class="row g-2 mb-4">
    <div class="col-sm-4">
        <select name="program_id" class="form-select form-select-sm">
            <option value="">All Programs</option>
            @foreach($programs as $p)
                <option value="{{ $p->id }}" @selected(request('program_id') == $p->id)>{{ $p->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-sm-4">
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
                        <td>{{ $c->student->user->name ?? ($c->applicant->user->name ?? '—') }}</td>
                        <td>{{ $c->applicant->program->name ?? '—' }}</td>
                        <td>{{ $c->batch->name ?? '—' }}</td>
                        <td>{{ $c->roll_number }}</td>
                        <td>{{ $c->confirmed_at?->format('d M Y') ?? '—' }}</td>
                        <td>
                            <a href="{{ route('admission.enrollment.show', $c) }}" class="btn btn-sm btn-outline-primary">View</a>
                            <a href="{{ route('admission.enrollment.letter', $c) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted py-5">No enrollments found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $confirmations->links() }}</div>
@endsection
