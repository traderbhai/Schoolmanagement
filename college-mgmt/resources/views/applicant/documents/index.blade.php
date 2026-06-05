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
        <span class="badge bg-primary fs-6">{{ $uploaded->count() }}/{{ $requiredDocs->count() }} Uploaded</span>
    </div>

    <div class="row g-3">
        @foreach($requiredDocs as $doc)
        @php $uploadedDoc = $uploaded->get($doc->id); @endphp
        <div class="col-md-6">
            <div class="card h-100 {{ $uploadedDoc ? ($uploadedDoc->status === 'verified' ? 'border-success' : ($uploadedDoc->status === 'rejected' ? 'border-danger' : 'border-info')) : '' }}">
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
                                    &bull; Max {{ $doc->max_size_kb }}KB
                                @endif
                            </span>
                        </div>
                        <div>
                            @if($uploadedDoc)
                                @if($uploadedDoc->status === 'verified')
                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Verified</span>
                                @elseif($uploadedDoc->status === 'rejected')
                                    <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Rejected</span>
                                @else
                                    <span class="badge bg-info text-dark"><i class="bi bi-clock me-1"></i>Pending</span>
                                @endif
                            @else
                                <span class="badge bg-secondary">Not Uploaded</span>
                            @endif
                        </div>
                    </div>

                    @if($uploadedDoc && $uploadedDoc->status === 'rejected' && $uploadedDoc->rejection_reason)
                        <div class="alert alert-danger py-2 small mb-2">
                            <i class="bi bi-exclamation-triangle me-1"></i>{{ $uploadedDoc->rejection_reason }}
                        </div>
                    @endif

                    @if($uploadedDoc)
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-file-earmark-check text-success"></i>
                            <span class="small text-muted">{{ $uploadedDoc->original_name }} ({{ $uploadedDoc->file_size_kb }}KB)</span>
                        </div>
                    @endif

                    @if($uploadedDoc?->status !== 'verified')
                    <form method="POST" action="{{ route('applicant.documents.upload', $doc) }}" enctype="multipart/form-data" class="mt-2">
                        @csrf
                        <div class="input-group input-group-sm">
                            <input type="file" name="document" class="form-control form-control-sm"
                                   accept="{{ implode(',', array_map(fn($f) => '.'.$f, array_filter(array_map('trim', explode(',', $doc->accepted_formats ?? 'pdf,jpg,png'))))) }}">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-upload me-1"></i>Upload
                            </button>
                        </div>
                    </form>

                    @if($uploadedDoc && $uploadedDoc->status !== 'verified')
                    <form method="POST" action="{{ route('applicant.documents.destroy', $uploadedDoc) }}" class="mt-1">
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
@endsection
