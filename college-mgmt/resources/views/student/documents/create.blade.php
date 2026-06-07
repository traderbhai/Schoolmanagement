@extends('layouts.student')
@section('title', 'Request a Document')
@section('content')
<div class="container py-4" style="max-width:600px">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('student.documents.index') }}">Document Requests</a></li>
            <li class="breadcrumb-item active">New Request</li>
        </ol>
    </nav>
    <h4 class="fw-semibold mb-4">Request a Document</h4>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('student.documents.store') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Document Type</label>
                    <select name="document_type" class="form-select @error('document_type') is-invalid @enderror">
                        <option value="">— Select document —</option>
                        @foreach($types as $type)
                        <option value="{{ $type }}" @selected(old('document_type') == $type)>
                            {{ \App\Models\DocumentRequest::typeLabel($type) }}
                        </option>
                        @endforeach
                    </select>
                    @error('document_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">Purpose <span class="text-muted">(optional)</span></label>
                    <input type="text" name="purpose" value="{{ old('purpose') }}" class="form-control" placeholder="e.g. Bank loan, Scholarship, Visa application">
                </div>
                <div class="mb-3">
                    <label class="form-label">Additional Information <span class="text-muted">(optional)</span></label>
                    <textarea name="additional_info" rows="3" class="form-control" placeholder="Any specific details required in the document...">{{ old('additional_info') }}</textarea>
                </div>
                <div class="alert alert-info py-2 small">
                    <i class="bi bi-info-circle me-1"></i>
                    Processing time: 2–5 working days. Character certificates may require additional approval.
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                    <a href="{{ route('student.documents.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
