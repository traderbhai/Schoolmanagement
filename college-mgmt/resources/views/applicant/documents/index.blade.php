@extends('layouts.applicant')
@section('title', 'Documents')
@section('page-title', 'Upload Documents')

@section('content')
<div class="container-fluid p-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">Required Documents</h4>
            <p class="text-muted small mb-0">Upload all required documents for {{ $applicant->program->name }}</p>
        </div>
        <div class="text-end">
            @php
                $totalRequired = $requiredDocs->count();
                $uploadedCount = $uploaded->count();
            @endphp
            <span class="badge bg-primary fs-6 me-2">{{ $uploadedCount }}/{{ $totalRequired }} Uploaded</span>
            @if($verifiedCount > 0)
                <span class="badge bg-success fs-6">{{ $verifiedCount }} Verified</span>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($locked)
        <div class="alert alert-warning">
            <i class="bi bi-lock me-2"></i>Document uploads are locked because your application status is <strong>{{ ucfirst($applicant->status) }}</strong>.
        </div>
    @endif

    {{-- Summary bar --}}
    <div class="alert alert-light border mb-4 py-2">
        <div class="row g-2 text-center">
            <div class="col-4">
                <div class="fw-bold text-primary">{{ $totalRequired }}</div>
                <div class="small text-muted">Total Required</div>
            </div>
            <div class="col-4">
                <div class="fw-bold text-info">{{ $uploadedCount }}</div>
                <div class="small text-muted">Uploaded</div>
            </div>
            <div class="col-4">
                <div class="fw-bold text-success">{{ $verifiedCount }}</div>
                <div class="small text-muted">Verified</div>
            </div>
        </div>
    </div>

    @if($requiredDocs->isEmpty())
        <div class="alert alert-info border">
            <div class="fw-semibold mb-1">No document requirements are published yet</div>
            <div class="small mb-3">
                Admission staff have not published the required document checklist for {{ $applicant->program->name }} yet. When the checklist is configured for your program, each required document will appear here with upload format, size limit, verification status, and any rejection reason.
            </div>
            <div class="small mb-3">
                Until then, keep your application and registration fee details complete, and use the checklist or status tracker to see whether any other admission step is pending.
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('applicant.checklist') }}" class="btn btn-sm btn-outline-primary">Checklist</a>
                <a href="{{ route('applicant.status') }}" class="btn btn-sm btn-outline-secondary">Track status</a>
                <a href="{{ route('applicant.dashboard') }}" class="btn btn-sm btn-outline-secondary">Dashboard</a>
            </div>
        </div>
    @endif

    <div class="row g-3">
        @foreach($requiredDocs as $doc)
        @php $uploadedDoc = $uploaded->get($doc->id); @endphp
        <div class="col-md-6">
            <div class="card h-100 {{ $uploadedDoc ? ($uploadedDoc->status === 'verified' ? 'border-success' : ($uploadedDoc->status === 'rejected' ? 'border-danger' : 'border-warning')) : 'border-secondary' }}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h6 class="fw-bold mb-0">
                                {{ $doc->name }}
                                @if($doc->is_mandatory)
                                    <span class="text-danger small">*</span>
                                @endif
                            </h6>
                            @if($doc->description)
                                <p class="text-muted small mb-1">{{ $doc->description }}</p>
                            @endif
                            <span class="text-muted small">
                                <i class="bi bi-file-earmark me-1"></i>{{ $doc->accepted_formats ?? 'pdf,jpg,png' }}
                                @if($doc->max_size_kb)
                                    &bull; Max {{ $doc->max_size_kb >= 1024 ? number_format($doc->max_size_kb/1024,1).' MB' : $doc->max_size_kb.' KB' }}
                                @endif
                            </span>
                        </div>
                        <div>
                            @if($uploadedDoc)
                                {!! $uploadedDoc->status_badge !!}
                            @else
                                <span class="badge bg-secondary">Not Uploaded</span>
                            @endif
                        </div>
                    </div>

                    @if($uploadedDoc && $uploadedDoc->status === 'rejected' && $uploadedDoc->rejection_reason)
                        <div class="alert alert-danger py-2 small mb-2">
                            <i class="bi bi-exclamation-triangle me-1"></i><strong>Rejected:</strong> {{ $uploadedDoc->rejection_reason }}
                            <br><span class="text-muted">Please re-upload the correct document.</span>
                        </div>
                    @endif

                    @if($uploadedDoc)
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi {{ $uploadedDoc->file_icon }} text-secondary"></i>
                            <span class="small text-muted">
                                {{ $uploadedDoc->original_name }}
                                ({{ $uploadedDoc->formatted_file_size }})
                                @if($uploadedDoc->uploaded_at)
                                    &bull; {{ $uploadedDoc->uploaded_at->diffForHumans() }}
                                @endif
                                @if($uploadedDoc->version > 1)
                                    &bull; v{{ $uploadedDoc->version }}
                                @endif
                            </span>
                        </div>
                    @endif

                    @if(! $locked && $uploadedDoc?->status !== 'verified')
                    <form method="POST" action="{{ route('applicant.documents.store', $doc) }}" enctype="multipart/form-data" class="mt-2 doc-upload-form">
                        @csrf
                        <div class="mb-2">
                            <div class="upload-area border rounded p-3 text-center position-relative"
                                 style="cursor:pointer; background:#f8f9fa;">
                                <i class="bi bi-cloud-upload fs-4 text-primary"></i>
                                <p class="mb-1 small text-muted">
                                    @if($uploadedDoc && $uploadedDoc->status === 'rejected')
                                        Click to re-upload
                                    @else
                                        Click to browse or drag &amp; drop
                                    @endif
                                </p>
                                <p class="mb-0 text-muted" style="font-size:0.75rem;">
                                    {{ $doc->accepted_formats_for_input }} &bull;
                                    Max {{ $doc->max_size_kb >= 1024 ? number_format($doc->max_size_kb/1024,1).' MB' : $doc->max_size_kb.' KB' }}
                                </p>
                                <input type="file" name="document" class="position-absolute top-0 start-0 w-100 h-100 opacity-0"
                                       accept="{{ $doc->accepted_formats_for_input }}"
                                       style="cursor:pointer;"
                                       onchange="showFileName(this)">
                            </div>
                            <div class="selected-file-name small text-muted mt-1 d-none">
                                <i class="bi bi-file-earmark me-1"></i><span></span>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-upload me-1"></i>
                            {{ $uploadedDoc ? 'Re-upload' : 'Upload' }}
                        </button>
                    </form>

                    @if($uploadedDoc && $uploadedDoc->status === 'pending')
                    <form method="POST" action="{{ route('applicant.documents.destroy', $uploadedDoc) }}" class="mt-2">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-link btn-sm text-danger p-0"
                                onclick="return confirm('Remove this document?')">
                            <i class="bi bi-trash me-1"></i>Remove
                        </button>
                    </form>
                    @endif
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>

</div>

@push('scripts')
<script>
function showFileName(input) {
    const container = input.closest('.doc-upload-form');
    const display   = container.querySelector('.selected-file-name');
    const span      = display.querySelector('span');
    if (input.files && input.files[0]) {
        span.textContent = input.files[0].name;
        display.classList.remove('d-none');
    } else {
        display.classList.add('d-none');
    }
}
</script>
@endpush
@endsection
