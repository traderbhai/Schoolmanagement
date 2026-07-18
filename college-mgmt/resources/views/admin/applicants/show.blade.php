@extends('layouts.admin')
@section('title', 'Applicant — ' . $applicant->application_number)

@section('content')
<div class="container-fluid p-4">

    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('admin.applicants.index') }}" class="btn btn-outline-secondary btn-sm" aria-label="Back to applicants">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h4 class="fw-bold mb-0">{{ $applicant->user?->name ?? 'Unknown' }}</h4>
            <span class="text-muted small">{{ $applicant->application_number }}</span>
        </div>
        <span class="{{ $applicant->status_badge }} ms-2">{{ $applicant->status_label }}</span>
    </div>

    <div class="row g-4">
        {{-- Left Column --}}
        <div class="col-md-8">

            {{-- Application Sections --}}
            @php
                $sectionDefs = [
                    ['key' => 'personal_data',   'label' => 'Personal Information',  'icon' => 'bi-person'],
                    ['key' => 'academic_data',   'label' => 'Academic Background',   'icon' => 'bi-mortarboard'],
                    ['key' => 'family_data',     'label' => 'Family Information',    'icon' => 'bi-people'],
                    ['key' => 'additional_data', 'label' => 'Additional Information','icon' => 'bi-info-circle'],
                ];
            @endphp

            @foreach($sectionDefs as $sec)
            @php $data = $applicant->{$sec['key']} ?? []; @endphp
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi {{ $sec['icon'] }} me-2"></i>{{ $sec['label'] }}</h6>
                </div>
                <div class="card-body">
                    @if(!empty($data))
                    <div class="row g-2">
                        @foreach($data as $key => $value)
                        <div class="col-md-6">
                            <span class="text-muted small d-block">{{ ucwords(str_replace('_', ' ', $key)) }}</span>
                            <span class="fw-semibold">{{ $value ?: '—' }}</span>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <p class="text-muted mb-0">Not filled</p>
                    @endif
                </div>
            </div>
            @endforeach

            {{-- Documents --}}
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0"><i class="bi bi-folder2-open me-2"></i>Documents</h6></div>
                <div class="card-body">
                    @forelse($applicant->documents as $doc)
                    <div class="d-flex align-items-center gap-3 py-2 border-bottom">
                        <i class="bi bi-file-earmark-fill text-muted fs-5"></i>
                        <div class="flex-grow-1">
                            <p class="mb-0 fw-semibold small">{{ $doc->requiredDocument?->name }}</p>
                            <span class="text-muted small">{{ $doc->original_name }} ({{ $doc->file_size_kb }}KB)</span>
                        </div>
                        <span class="badge {{ $doc->status === 'verified' ? 'bg-success' : ($doc->status === 'rejected' ? 'bg-danger' : 'bg-info text-dark') }}">
                            {{ ucfirst($doc->status) }}
                        </span>
                    </div>
                    @empty
                    <p class="text-muted mb-0">No documents uploaded</p>
                    @endforelse
                </div>
            </div>

        </div>

        {{-- Right Column --}}
        <div class="col-md-4">

            {{-- Summary Card --}}
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0">Application Details</h6></div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-5 text-muted">Program</dt>
                        <dd class="col-7">{{ $applicant->program->name }}</dd>
                        <dt class="col-5 text-muted">Batch</dt>
                        <dd class="col-7">{{ $applicant->batch?->name ?? '—' }}</dd>
                        <dt class="col-5 text-muted">Status</dt>
                        <dd class="col-7"><span class="{{ $applicant->status_badge }}">{{ $applicant->status_label }}</span></dd>
                        <dt class="col-5 text-muted">Applied At</dt>
                        <dd class="col-7">{{ $applicant->applied_at?->format('d M Y') ?? '—' }}</dd>
                        <dt class="col-5 text-muted">Reviewed At</dt>
                        <dd class="col-7">{{ $applicant->reviewed_at?->format('d M Y') ?? '—' }}</dd>
                        <dt class="col-5 text-muted">Reviewed By</dt>
                        <dd class="col-7">{{ $applicant->reviewer?->name ?? '—' }}</dd>
                    </dl>
                </div>
            </div>

            {{-- Contact --}}
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0">Contact</h6></div>
                <div class="card-body small">
                    <p class="mb-1"><i class="bi bi-person me-2 text-muted"></i>{{ $applicant->user?->name ?? '—' }}</p>
                    <p class="mb-1"><i class="bi bi-envelope me-2 text-muted"></i>{{ $applicant->user?->email ?? '—' }}</p>
                    @if(!empty($applicant->personal_data['phone']))
                    <p class="mb-0"><i class="bi bi-telephone me-2 text-muted"></i>{{ $applicant->personal_data['phone'] }}</p>
                    @endif
                </div>
            </div>

            {{-- Notes --}}
            <div class="card">
                <div class="card-header"><h6 class="mb-0"><i class="bi bi-sticky me-2"></i>Internal Notes</h6></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.applicants.notes', $applicant) }}">
                        @csrf
                        <textarea aria-label="Internal notes (not visible to applicant)" name="notes" rows="5" class="form-control form-control-sm mb-2"
                                  placeholder="Internal notes (not visible to applicant)">{{ $applicant->notes }}</textarea>
                        <button type="submit" class="btn btn-sm btn-primary">Save Notes</button>
                    </form>
                </div>
            </div>

        </div>
    </div>

</div>
@endsection
