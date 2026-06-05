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
                        <p class="text-muted small mt-1">Submitted: {{ $applicant->applied_at->format('d M Y') }}</p>
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
                            <a href="{{ route('applicant.documents') }}" class="btn btn-sm btn-outline-primary">Upload</a>
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
