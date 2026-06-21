@extends('layouts.admin')

@section('title', 'Enroll Applicant')

@section('content')
<div class="mb-3">
    <a href="{{ route('admission.applicants.show', $applicant) }}" class="text-muted small"><i class="bi bi-arrow-left"></i> Back to Applicant</a>
    <h2 class="fw-bold mb-0 mt-1">Confirm Enrollment</h2>
    <div class="text-muted small">{{ $applicant->application_number }} - {{ $applicant->program->name ?? 'Program not assigned' }}</div>
</div>

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="alert alert-info border-0 shadow-sm mb-4">
    <div class="fw-semibold mb-2"><i class="bi bi-diagram-3 me-1"></i>Enrollment confirmation sequence</div>
    <div class="d-flex flex-wrap gap-2 small">
        <span class="badge bg-light text-dark">1. Confirm selected status</span>
        <span class="badge bg-light text-dark">2. Verify payment</span>
        <span class="badge bg-light text-dark">3. Verify mandatory documents</span>
        <span class="badge bg-light text-dark">4. Assign roll number</span>
        <span class="badge bg-light text-dark">5. Create student profile</span>
        <span class="badge bg-light text-dark">6. Trigger Academics handoff</span>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header fw-semibold bg-transparent d-flex justify-content-between align-items-center">
        <span>Enrollment Readiness</span>
        @if($canEnroll)
            <span class="badge bg-success">Ready for enrollment</span>
        @else
            <span class="badge bg-warning text-dark">Action required</span>
        @endif
    </div>
    <div class="card-body">
        @if($canEnroll)
            <div class="alert alert-success d-flex align-items-start gap-2 py-2">
                <i class="bi bi-check-circle mt-1"></i>
                <div>
                    <div class="fw-semibold">Ready for enrollment</div>
                    <div class="small">All required admission checks are complete. Confirming enrollment will create the student profile and move this user into the student portal.</div>
                </div>
            </div>
        @else
            <div class="alert alert-warning d-flex align-items-start gap-2 py-2">
                <i class="bi bi-exclamation-triangle mt-1"></i>
                <div>
                    <div class="fw-semibold">Enrollment is locked until every required check is complete.</div>
                    <div class="small">Resolve the items below before confirming enrollment. These checks protect admissions, accounts, and student records from incomplete onboarding.</div>
                </div>
            </div>
        @endif

        <ul class="list-unstyled mb-0">
            <li class="mb-2">
                @if($isSelected)
                    <span class="badge bg-success me-2"><i class="bi bi-check-lg"></i></span> Applicant status is <strong>Selected</strong>
                @else
                    <span class="badge bg-danger me-2"><i class="bi bi-x-lg"></i></span> Applicant status must be <strong>Selected</strong> (current: {{ $applicant->status_label }})
                @endif
            </li>
            <li class="mb-2">
                @if($hasVerifiedPayment)
                    <span class="badge bg-success me-2"><i class="bi bi-check-lg"></i></span> Verified admission payment exists
                @else
                    <span class="badge bg-danger me-2"><i class="bi bi-x-lg"></i></span> No verified payment found
                @endif
            </li>
            <li class="mb-2">
                @if($hasVerifiedMandatoryDocs)
                    <span class="badge bg-success me-2"><i class="bi bi-check-lg"></i></span>
                    Mandatory documents verified
                    <span class="text-muted small">{{ $verifiedMandatoryDocumentCount }}/{{ $mandatoryDocumentCount }} required</span>
                @else
                    <span class="badge bg-danger me-2"><i class="bi bi-x-lg"></i></span>
                    Mandatory documents are incomplete
                    <span class="text-muted small">{{ $verifiedMandatoryDocumentCount }}/{{ $mandatoryDocumentCount }} required verified</span>
                @endif
            </li>
            <li class="mb-2">
                @if(!$alreadyEnrolled)
                    <span class="badge bg-success me-2"><i class="bi bi-check-lg"></i></span> No existing enrollment
                @else
                    <span class="badge bg-danger me-2"><i class="bi bi-x-lg"></i></span> Already enrolled
                @endif
            </li>
        </ul>

        @if(!$hasVerifiedMandatoryDocs && $missingMandatoryDocuments->isNotEmpty())
            <div class="mt-3 border rounded p-3 bg-light">
                <div class="small fw-semibold text-muted mb-2">Missing verified mandatory documents</div>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($missingMandatoryDocuments as $document)
                        <span class="badge bg-white text-dark border">{{ $document->name }}</span>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header fw-semibold bg-transparent">Applicant Summary</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4"><div class="small text-muted">Name</div><div class="fw-medium">{{ $applicant->user->name ?? ($applicant->personal_data['name'] ?? 'Applicant name not recorded') }}</div></div>
            <div class="col-md-4"><div class="small text-muted">Email</div><div class="fw-medium">{{ $applicant->user->email ?? ($applicant->personal_data['email'] ?? 'Applicant email not recorded') }}</div></div>
            <div class="col-md-4"><div class="small text-muted">Program</div><div class="fw-medium">{{ $applicant->program->name ?? 'Program not assigned' }}</div></div>
            <div class="col-md-4"><div class="small text-muted">Batch</div><div class="fw-medium">{{ $applicant->batch->name ?? 'Batch not assigned' }}</div></div>
            <div class="col-md-4"><div class="small text-muted">Application #</div><div class="fw-medium font-monospace">{{ $applicant->application_number }}</div></div>
            <div class="col-md-4"><div class="small text-muted">Status</div><div><span class="{{ $applicant->status_badge }}">{{ $applicant->status_label }}</span></div></div>
        </div>
    </div>
</div>

@if($alreadyEnrolled)
    <div class="alert alert-warning">This applicant is already enrolled. Use the Enrollment Confirmation page or Academics student lifecycle for any next step.</div>
@else
<form action="{{ route('admission.enrollment.store', $applicant) }}" method="POST">
    @csrf

    @if(!$canEnroll)
    <div class="alert alert-warning">
        <i class="bi bi-lock me-1"></i>
        Enrollment is disabled until selected status, verified admission payment, mandatory document verification, and duplicate-enrollment checks all pass.
    </div>
    @endif

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header fw-semibold bg-transparent">Enrollment Details</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Roll Number <span class="text-danger">*</span></label>
                    <input type="text" name="roll_number" class="form-control @error('roll_number') is-invalid @enderror"
                           value="{{ old('roll_number') }}" placeholder="e.g. PGDM-24-001" required>
                    @error('roll_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Specialization</label>
                    <select name="specialization_id" class="form-select">
                        <option value="">No specialization</option>
                        @foreach($specializations as $s)
                            <option value="{{ $s->id }}" @selected(old('specialization_id') == $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Term</label>
                    <select name="term_id" class="form-select">
                        <option value="">Term not selected</option>
                        @foreach($terms as $t)
                            <option value="{{ $t->id }}" @selected(old('term_id') == $t->id)>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Notes</label>
                    <textarea name="notes" rows="3" class="form-control" placeholder="Optional enrollment handoff note">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-success px-4" @disabled(!$canEnroll)>
            <i class="bi bi-person-check me-1"></i> Confirm Enrollment
        </button>
        <a href="{{ route('admission.applicants.show', $applicant) }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
@endif
@endsection
