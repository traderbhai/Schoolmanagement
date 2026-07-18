@extends('layouts.admin')

@section('title', 'Enrollment - ' . $confirmation->enrollment_number)

@section('content')
<div class="mb-3">
    <a href="{{ route('admission.enrollment.index') }}" class="text-muted small"><i class="bi bi-arrow-left"></i> All Enrollments</a>
    <h2 class="fw-bold mb-0 mt-1">Enrollment Confirmation</h2>
    <div class="text-muted small font-monospace">{{ $confirmation->enrollment_number }}</div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="alert alert-info border-0 shadow-sm mb-4">
    <div class="fw-semibold mb-1"><i class="bi bi-arrow-left-right me-1"></i>Enrollment handoff context</div>
    <div class="small text-muted">This confirmation is the Admission-to-Academics handoff source. Use it to verify roll number, batch, term, student profile link, and final enrollment letter before Academics onboarding starts.</div>
</div>

<div class="row g-4">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header fw-semibold bg-transparent">Enrollment Details</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6"><div class="small text-muted">Enrollment Number</div><div class="fw-bold font-monospace">{{ $confirmation->enrollment_number }}</div></div>
                    <div class="col-sm-6"><div class="small text-muted">Roll Number</div><div class="fw-medium">{{ $confirmation->roll_number ?: 'Roll number not assigned' }}</div></div>
                    <div class="col-sm-6"><div class="small text-muted">Status</div>
                        <span class="badge {{ $confirmation->status === 'completed' ? 'bg-success' : ($confirmation->status === 'failed' ? 'bg-danger' : 'bg-warning text-dark') }}">
                            {{ ucfirst($confirmation->status) }}
                        </span>
                    </div>
                    <div class="col-sm-6"><div class="small text-muted">Confirmed At</div><div class="fw-medium">{{ $confirmation->confirmed_at?->format('d M Y, H:i') ?? 'Confirmation date pending' }}</div></div>
                    <div class="col-sm-6"><div class="small text-muted">Confirmed By</div><div class="fw-medium">{{ $confirmation->confirmedBy->name ?? 'Confirming staff not recorded' }}</div></div>
                    <div class="col-sm-6"><div class="small text-muted">Program</div><div class="fw-medium">{{ $confirmation->applicant->program->name ?? 'Program not linked' }}</div></div>
                    <div class="col-sm-6"><div class="small text-muted">Batch</div><div class="fw-medium">{{ $confirmation->batch->name ?? 'Batch not linked' }}</div></div>
                    <div class="col-sm-6"><div class="small text-muted">Term</div><div class="fw-medium">{{ $confirmation->term->name ?? 'Term not assigned' }}</div></div>
                    @if($confirmation->notes)
                    <div class="col-12"><div class="small text-muted">Notes</div><div class="fw-medium">{{ $confirmation->notes }}</div></div>
                    @else
                    <div class="col-12"><div class="small text-muted">Notes</div><div class="fw-medium text-muted">No enrollment handoff note recorded</div></div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header fw-semibold bg-transparent">Student</div>
            <div class="card-body">
                <div class="mb-2 fw-semibold">{{ $confirmation->student->user->name ?? ($confirmation->applicant->user->name ?? 'Student name not linked') }}</div>
                <div class="text-muted small mb-3">{{ $confirmation->student->user->email ?? 'Student email not linked' }}</div>
                @if($confirmation->student)
                <a href="{{ route('admin.students.show', $confirmation->student) }}" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-person me-1"></i> View Student Profile
                </a>
                @else
                <div class="alert alert-warning small mb-0">Student profile is not linked yet. Recheck the enrollment status before printing official letters.</div>
                @endif
            </div>
        </div>
        <div class="d-grid gap-2">
            <a href="{{ route('admission.enrollment.letter', $confirmation) }}" class="btn btn-outline-secondary">
                <i class="bi bi-printer me-1"></i> Print Enrollment Letter
            </a>
            <a href="{{ route('admission.applicants.show', $confirmation->applicant) }}" class="btn btn-outline-secondary">
                <i class="bi bi-file-text me-1"></i> View Original Application
            </a>
        </div>
    </div>
</div>
@endsection
