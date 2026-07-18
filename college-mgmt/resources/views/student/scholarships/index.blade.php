@extends('layouts.student')
@section('title', 'Scholarships')
@section('page-title', 'Scholarships')

@section('content')
<div class="container-fluid py-3" style="max-width:960px">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-semibold mb-0">Scholarships</h4>
            <div class="text-muted small">Apply for eligible schemes and track review, approval, and disbursement status.</div>
            <div class="small text-muted mt-1">
                Current CGPA: {{ number_format((float) $cgpa, 2) }}
                &bull; Family income: {{ $familyIncome !== null ? 'Rs. '.number_format((float) $familyIncome, 0) : 'Not recorded' }}
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1">Please review your scholarship application.</div>
            <ul class="mb-0 small">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @unless($canApplyForScholarships)
        <div class="alert alert-warning">
            Scholarship application submission is locked because this student profile is not active. Existing scholarship history remains available for reference.
        </div>
    @endunless

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header fw-semibold"><i class="bi bi-clock-history me-2"></i>My Applications</div>
        @if($myApplications->isEmpty())
        <div class="card-body text-center py-4">
            <i class="bi bi-file-earmark-check fs-2 d-block mb-2 text-muted"></i>
            <div class="fw-semibold text-dark mb-1">No scholarship applications submitted yet</div>
            <div class="text-muted small mx-auto" style="max-width:620px">
                Apply from an eligible active scheme below. Submitted applications will appear here with
                pending, shortlisted, approved, rejected, or disbursed status after the scholarship office reviews them.
            </div>
        </div>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th scope="col">Scholarship</th><th scope="col">CGPA</th><th scope="col">Status</th><th scope="col">Applied</th></tr>
                </thead>
                <tbody>
                    @foreach($myApplications as $app)
                    @php $badge = match($app->status) { 'approved', 'disbursed' => 'success', 'rejected' => 'danger', 'shortlisted' => 'info', default => 'warning' }; @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $app->scheme->name ?? '-' }}</div>
                            @if($app->review_note)
                                <div class="small text-muted">{{ $app->review_note }}</div>
                            @endif
                        </td>
                        <td>{{ $app->cgpa_at_application ?? '-' }}</td>
                        <td>
                            <span class="badge bg-{{ $badge }}">{{ ucfirst($app->status) }}</span>
                            @if($app->disbursed_amount)
                                <div class="small text-muted mt-1">Rs. {{ number_format((float) $app->disbursed_amount, 2) }}</div>
                            @endif
                        </td>
                        <td class="text-muted small">{{ $app->created_at->format('d M Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header fw-semibold"><i class="bi bi-award me-2"></i>Available Scholarships</div>
        @if($schemes->isEmpty())
        <div class="card-body text-center py-5">
            <i class="bi bi-award fs-1 d-block mb-2 text-muted"></i>
            <div class="fw-semibold text-dark mb-1">No active scholarships are open for your program right now</div>
            <div class="text-muted small mx-auto" style="max-width:640px">
                Scholarship schemes appear here only after the office publishes an active scheme for your
                program or for all programs. If you expect a scheme, check that your program, CGPA,
                guardian income, and required proof documents are up to date.
            </div>
        </div>
        @else
        <div class="list-group list-group-flush">
            @foreach($schemes as $scheme)
            @php
                $applied = isset($myApplications[$scheme->id]);
                $eligibility = $scheme->student_eligibility ?? ['eligible' => true, 'reason' => null];
            @endphp
            <div class="list-group-item px-4 py-3">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <h6 class="fw-semibold mb-1">{{ $scheme->name }}</h6>
                        <div class="text-muted small mb-1">
                            <span class="{{ $scheme->type_badge }} me-1">{{ $scheme->type_label }}</span>
                            <span>Max Rs. {{ number_format((float) $scheme->max_amount, 0) }}</span>
                            @if($scheme->available_seats !== null)
                                <span class="ms-2">Seats left: {{ $scheme->seatsRemaining() }}</span>
                            @else
                                <span class="ms-2">Unlimited seats</span>
                            @endif
                        </div>
                        @if($scheme->criteria)
                            <p class="small mb-0"><strong>Eligibility:</strong> {{ $scheme->criteria }}</p>
                        @endif
                        <div class="small text-muted mt-1">
                            @if($scheme->min_cgpa)
                                <span class="me-2">Min CGPA {{ $scheme->min_cgpa }}</span>
                            @endif
                            @if($scheme->max_family_income)
                                <span class="me-2">Income up to Rs. {{ number_format((float) $scheme->max_family_income, 0) }}</span>
                            @endif
                            @if($scheme->requires_document)
                                <span class="me-2">Proof document required</span>
                            @endif
                        </div>
                        @if(!$eligibility['eligible'])
                            <div class="small text-danger mt-1">{{ $eligibility['reason'] }}</div>
                        @endif
                    </div>
                    <div class="flex-shrink-0">
                        @if($applied)
                            @php $badge = match($myApplications[$scheme->id]->status) { 'approved', 'disbursed' => 'success', 'rejected' => 'danger', 'shortlisted' => 'info', default => 'warning' }; @endphp
                            <span class="badge bg-{{ $badge }}">{{ ucfirst($myApplications[$scheme->id]->status) }}</span>
                        @elseif(!$eligibility['eligible'])
                            <span class="badge bg-danger">Not Eligible</span>
                        @elseif(!$canApplyForScholarships)
                            <span class="badge bg-secondary">Locked</span>
                        @else
                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#applyScholarship{{ $scheme->id }}">Apply for scholarship</button>
                        @endif
                    </div>
                </div>

                @if(!$applied && $eligibility['eligible'] && $canApplyForScholarships)
                    <div class="collapse mt-3" id="applyScholarship{{ $scheme->id }}">
                        <form method="POST" action="{{ route('student.scholarships.apply', $scheme) }}" enctype="multipart/form-data">
                            @csrf
                            <label class="form-label small fw-semibold">Why should you be considered? <span class="text-danger">*</span></label>
                            <textarea aria-label="Mention academic performance, financial need, achievements, or relevant circumstances." name="reason" class="form-control form-control-sm mb-2" rows="4" minlength="50" maxlength="2000" required placeholder="Mention academic performance, financial need, achievements, or relevant circumstances.">{{ old('reason') }}</textarea>
                            @if($scheme->requires_document)
                                <label class="form-label small fw-semibold">Proof document <span class="text-danger">*</span></label>
                                <input aria-label="Proof Document" type="file" name="proof_document" class="form-control form-control-sm mb-2" accept=".pdf,.jpg,.jpeg,.png" required>
                            @endif
                            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                                <div class="small text-muted">Minimum 50 characters. Staff will review this with your academic record.</div>
                                <button class="btn btn-sm btn-primary">Submit Application</button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>
@endsection
