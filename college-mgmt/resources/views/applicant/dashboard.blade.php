@extends('layouts.applicant')
@section('title', 'Dashboard — Applicant Portal')
@section('page-title', 'Dashboard')

@section('content')
<div class="container-fluid p-4">

    {{-- Welcome Banner --}}
    <div class="card mb-4 border-0" style="background: linear-gradient(135deg, var(--primary-color, #4f46e5) 0%, #7c3aed 100%);">
        <div class="card-body text-white p-4">
            <h4 class="fw-bold mb-1">Welcome, {{ auth()->user()->name }}!</h4>
            <p class="mb-0 opacity-75">Application No: <strong>{{ $applicant->application_number }}</strong> &bull; Program: <strong>{{ $applicant->program->name }}</strong></p>
        </div>
    </div>

    {{-- Status + Progress --}}
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card kpi-card h-100">
                <div class="card-body text-center p-4">
                    <div class="mb-2">
                        <span class="{{ $applicant->status_badge }} fs-6 px-3 py-2">{{ $applicant->status_label }}</span>
                    </div>
                    <p class="text-muted small mb-0">Application Status</p>
                    @if($applicant->applied_at)
                        <p class="text-muted small mt-1">Submitted: {{ $applicant->applied_at ? $applicant->applied_at->format('d M Y') : '—' }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card kpi-card h-100">
                <div class="card-body p-4">
                    <h6 class="text-muted mb-3">Form Completion</h6>
                    <div class="progress mb-2" style="height: 10px;">
                        <div class="progress-bar bg-success" style="width: {{ ($completedSections / 4) * 100 }}%"></div>
                    </div>
                    <p class="small text-muted mb-0">{{ $completedSections }}/4 sections completed</p>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card kpi-card h-100">
                <div class="card-body p-4">
                    <h6 class="text-muted mb-3">Documents</h6>
                    @php
                        $totalDocs = \App\Models\RequiredDocument::where('program_id', $applicant->program_id)->count();
                        $uploadedDocs = $applicant->documents->count();
                    @endphp
                    <div class="progress mb-2" style="height: 10px;">
                        <div class="progress-bar bg-info" style="width: {{ $totalDocs > 0 ? ($uploadedDocs / $totalDocs) * 100 : 0 }}%"></div>
                    </div>
                    <p class="small text-muted mb-0">{{ $uploadedDocs }}/{{ $totalDocs }} documents uploaded</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Registration Fee CTA --}}
    @if(!$applicant->hasRegistrationFeePaid() && in_array($applicant->status, ['draft', 'submitted']))
    <div class="alert alert-warning d-flex align-items-start gap-3 mb-4">
        <i class="bi bi-exclamation-triangle-fill fs-4 mt-1"></i>
        <div>
            <div class="fw-semibold">Registration Fee Required</div>
            <div class="small mt-1">Your application cannot be submitted until the registration fee is paid.</div>
            <a href="{{ route('admission.applicants.registration-fee.show', $applicant) }}" class="btn btn-warning btn-sm mt-2">
                <i class="bi bi-credit-card me-1"></i> Pay Registration Fee
            </a>
        </div>
    </div>
    @endif

    {{-- Submission Checklist --}}
    @php
        $totalRequired  = \App\Models\RequiredDocument::where('program_id', $applicant->program_id)->count();
        $uploadedCount  = $applicant->documents->count();
        $verifiedCount  = $applicant->documents->where('status', 'verified')->count();
    @endphp
    <div class="card mb-4">
        <div class="card-header fw-semibold"><i class="bi bi-check2-all me-2"></i>Submission Checklist</div>
        <div class="card-body p-0">
            <ul class="list-group list-group-flush">
                @php
                    $formComplete = $completedSections >= 4;
                    $feePaid = $applicant->hasRegistrationFeePaid();
                    $docsUploaded = $uploadedCount >= $totalRequired && $totalRequired > 0;
                    $submitted = !in_array($applicant->status, ['draft']);
                @endphp
                <li class="list-group-item d-flex align-items-center gap-3 py-3">
                    <i class="bi bi-{{ $feePaid ? 'check-circle-fill text-success' : 'circle text-warning' }} fs-5"></i>
                    <div class="flex-grow-1">
                        <div class="fw-semibold small">Registration Fee</div>
                        <div class="text-muted smaller">{{ $feePaid ? 'Paid on ' . ($applicant->registration_fee_paid_at?->format('d M Y') ?? '—') : 'Not yet paid' }}</div>
                    </div>
                    @if(!$feePaid)<a href="{{ route('admission.applicants.registration-fee.show', $applicant) }}" class="btn btn-xs btn-outline-warning btn-sm">Pay Now</a>@endif
                </li>
                <li class="list-group-item d-flex align-items-center gap-3 py-3">
                    <i class="bi bi-{{ $formComplete ? 'check-circle-fill text-success' : 'circle text-secondary' }} fs-5"></i>
                    <div class="flex-grow-1">
                        <div class="fw-semibold small">Application Form</div>
                        <div class="text-muted smaller">{{ $completedSections }}/4 sections completed</div>
                    </div>
                    @if(!$formComplete && $applicant->status === 'draft')<a href="{{ route('applicant.application.show') }}" class="btn btn-xs btn-outline-primary btn-sm">Complete</a>@endif
                </li>
                <li class="list-group-item d-flex align-items-center gap-3 py-3">
                    <i class="bi bi-{{ $docsUploaded ? 'check-circle-fill text-success' : ($totalRequired > 0 ? 'circle text-secondary' : 'dash-circle text-muted') }} fs-5"></i>
                    <div class="flex-grow-1">
                        <div class="fw-semibold small">Documents</div>
                        <div class="text-muted smaller">{{ $uploadedCount }}/{{ $totalRequired }} uploaded @if($verifiedCount > 0)({{ $verifiedCount }} verified)@endif</div>
                    </div>
                    @if(!$docsUploaded && $applicant->status === 'draft')<a href="{{ route('applicant.documents.index') }}" class="btn btn-xs btn-outline-primary btn-sm">Upload</a>@endif
                </li>
                <li class="list-group-item d-flex align-items-center gap-3 py-3">
                    <i class="bi bi-{{ $submitted ? 'check-circle-fill text-success' : 'circle text-secondary' }} fs-5"></i>
                    <div class="flex-grow-1">
                        <div class="fw-semibold small">Application Submitted</div>
                        <div class="text-muted smaller">{{ $submitted ? 'Submitted — status: ' . ucfirst(str_replace('_',' ',$applicant->status)) : 'Not yet submitted' }}</div>
                    </div>
                </li>
            </ul>
        </div>
    </div>

    {{-- Application Status Timeline --}}
    <div class="card mb-4">
        <div class="card-header fw-semibold"><i class="bi bi-clock-history me-2"></i>Application Journey</div>
        <div class="card-body">
            @php
                $statuses = [
                    'submitted'    => in_array($applicant->status, ['submitted','under_review','shortlisted','selected','enrolled']),
                    'under_review' => in_array($applicant->status, ['under_review','shortlisted','selected','enrolled']),
                    'selected'     => in_array($applicant->status, ['shortlisted','selected','enrolled']),
                    'offer_issued' => isset($offerLetter),
                    'enrolled'     => isset($enrollment) && $enrollment->status === 'completed',
                ];
            @endphp
            <div class="d-flex flex-wrap gap-0 align-items-center">
                @php
                    $steps = [
                        ['label' => 'Submitted',    'key' => 'submitted',    'icon' => 'send-check'],
                        ['label' => 'Under Review', 'key' => 'under_review', 'icon' => 'search'],
                        ['label' => 'Selected',     'key' => 'selected',     'icon' => 'person-check'],
                        ['label' => 'Offer Issued', 'key' => 'offer_issued', 'icon' => 'envelope-check'],
                        ['label' => 'Enrolled',     'key' => 'enrolled',     'icon' => 'mortarboard-fill'],
                    ];
                @endphp
                @foreach($steps as $i => $step)
                    <div class="text-center" style="min-width:100px">
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-1
                            {{ $statuses[$step['key']] ? 'bg-success text-white' : 'bg-light text-muted border' }}"
                            style="width:44px;height:44px">
                            <i class="bi bi-{{ $step['icon'] }}"></i>
                        </div>
                        <div class="small fw-semibold {{ $statuses[$step['key']] ? 'text-success' : 'text-muted' }}">
                            {{ $step['label'] }}
                        </div>
                        @if($step['key'] === 'offer_issued' && isset($offerLetter) && $offerLetter->status === 'issued')
                            <div class="smaller text-warning" style="font-size:0.72rem">
                                Deadline: {{ \Carbon\Carbon::parse($offerLetter->acceptance_deadline)->format('d M Y') }}
                            </div>
                        @elseif($step['key'] === 'enrolled' && isset($enrollment) && $enrollment->status === 'completed')
                            <div class="smaller text-success" style="font-size:0.72rem">
                                {{ $enrollment->enrollment_number }}
                            </div>
                        @endif
                    </div>
                    @if(!$loop->last)
                        <div class="flex-grow-1" style="height:2px;background:{{ $statuses[$step['key']] ? '#198754' : '#dee2e6' }};min-width:20px;max-width:60px;margin-bottom:24px"></div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>

    {{-- What's Next --}}
    <div class="row g-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>What's Next</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        @php
                            $sectionKeys = ['personal', 'academic', 'family', 'additional'];
                            $sectionLabels = ['Personal Information', 'Academic Background', 'Family Information', 'Additional Information'];
                        @endphp
                        @foreach($sectionKeys as $i => $key)
                            @php $done = !empty($applicant->getFormDataForSection($key)); @endphp
                            <li class="list-group-item d-flex align-items-center gap-3">
                                <i class="bi {{ $done ? 'bi-check-circle-fill text-success' : 'bi-circle text-muted' }} fs-5"></i>
                                <div class="flex-grow-1">
                                    <span class="{{ $done ? 'text-decoration-line-through text-muted' : 'fw-semibold' }}">
                                        Fill {{ $sectionLabels[$i] }}
                                    </span>
                                </div>
                                @if(!$done && $applicant->status === 'draft')
                                    <a href="{{ route('applicant.application.show', ['step' => $i]) }}" class="btn btn-sm btn-outline-primary">Fill Now</a>
                                @endif
                            </li>
                        @endforeach
                        <li class="list-group-item d-flex align-items-center gap-3">
                            <i class="bi {{ $uploadedDocs >= 1 ? 'bi-check-circle-fill text-success' : 'bi-circle text-muted' }} fs-5"></i>
                            <div class="flex-grow-1">
                                <span class="{{ $uploadedDocs >= 1 ? 'text-decoration-line-through text-muted' : 'fw-semibold' }}">Upload Required Documents</span>
                            </div>
                            <a href="{{ route('applicant.documents.index') }}" class="btn btn-sm btn-outline-primary">Upload</a>
                        </li>
                        <li class="list-group-item d-flex align-items-center gap-3">
                            <i class="bi {{ $applicant->status !== 'draft' ? 'bi-check-circle-fill text-success' : 'bi-circle text-muted' }} fs-5"></i>
                            <div class="flex-grow-1">
                                <span class="{{ $applicant->status !== 'draft' ? 'text-decoration-line-through text-muted' : 'fw-semibold' }}">Submit Application</span>
                            </div>
                            @if($applicant->status === 'draft')
                                <a href="{{ route('applicant.application.show') }}" class="btn btn-sm btn-success">Submit</a>
                            @endif
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Program Info</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-muted">Program</dt>
                        <dd class="col-7">{{ $applicant->program->name }}</dd>
                        <dt class="col-5 text-muted">Duration</dt>
                        <dd class="col-7">{{ $applicant->program->duration_years }} Years</dd>
                        <dt class="col-5 text-muted">Type</dt>
                        <dd class="col-7">{{ strtoupper($applicant->program->system_type) }}</dd>
                        @if($applicant->batch)
                        <dt class="col-5 text-muted">Batch</dt>
                        <dd class="col-7">{{ $applicant->batch->name }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
