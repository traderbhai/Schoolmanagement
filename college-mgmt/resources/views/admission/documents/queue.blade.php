@extends('layouts.admin')
@section('title', 'Document Verification Queue')

@section('content')
@php
    $filterSummary = collect([
        request('program_id') ? 'Program filtered' : null,
        request('batch_id') ? 'Batch filtered' : null,
        request('document_name') ? 'Document: ' . request('document_name') : null,
        request('uploaded_from') ? 'From: ' . request('uploaded_from') : null,
        request('uploaded_to') ? 'To: ' . request('uploaded_to') : null,
    ])->filter()->implode(' | ') ?: 'All visible pending documents';
@endphp
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0 fw-bold"><i class="bi bi-folder-check me-2 text-primary"></i>Document Verification Queue</h2>
        <small class="text-muted">Review and verify uploaded applicant documents</small>
        <div class="small text-muted">Filter: {{ $filterSummary }}</div>
    </div>
    <a href="{{ route('admission.documents.queue.export', request()->query()) }}" class="btn btn-sm btn-outline-success">
        <i class="bi bi-download me-1"></i>Export Current View
    </a>
</div>

{{-- Stats --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-1 fw-bold text-warning">{{ $stats['pending'] }}</div>
            <div class="small text-muted">Pending Verification</div>
            @if(($stats['all_pending'] ?? $stats['pending']) !== $stats['pending'])
                <div class="small text-muted">of {{ $stats['all_pending'] }} visible total</div>
            @endif
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-1 fw-bold text-success">{{ $stats['verified_today'] }}</div>
            <div class="small text-muted">Verified Today</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-1 fw-bold text-danger">{{ $stats['rejected_today'] }}</div>
            <div class="small text-muted">Rejected Today</div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admission.documents.queue') }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Program</label>
                <select name="program_id" class="form-select form-select-sm">
                    <option value="">All Programs</option>
                    @foreach($programs as $p)
                        <option value="{{ $p->id }}" @selected(request('program_id') == $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Batch</label>
                <select name="batch_id" class="form-select form-select-sm">
                    <option value="">All Batches</option>
                    @foreach($batches as $b)
                        <option value="{{ $b->id }}" @selected(request('batch_id') == $b->id)>{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Document Name</label>
                <input type="text" name="document_name" class="form-control form-control-sm"
                       placeholder="Search document..." value="{{ request('document_name') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Uploaded From</label>
                <input type="date" name="uploaded_from" class="form-control form-control-sm"
                       value="{{ request('uploaded_from') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Uploaded To</label>
                <input type="date" name="uploaded_to" class="form-control form-control-sm"
                       value="{{ request('uploaded_to') }}">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-fill">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
                <a href="{{ route('admission.documents.queue') }}" class="btn btn-outline-secondary btn-sm flex-fill">
                    Clear
                </a>
            </div>
        </form>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- Bulk Verify Form --}}
<form method="POST" action="{{ route('admission.documents.bulk-verify') }}" id="bulkVerifyForm">
    @csrf

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
            <span class="fw-semibold">Pending Documents ({{ $documents->total() }})</span>
            <span class="small text-muted">Showing {{ $documents->firstItem() ?? 0 }}-{{ $documents->lastItem() ?? 0 }} of {{ $documents->total() }}</span>
            <button type="submit" class="btn btn-success btn-sm" id="bulkVerifyBtn" disabled
                    onclick="return confirm('Verify all selected documents?')">
                <i class="bi bi-check-all me-1"></i>Verify Selected (<span id="selectedCount">0</span>)
            </button>
        </div>

        @if($documents->isEmpty())
            <div class="card-body text-center py-5 text-muted">
                <i class="bi bi-folder2-open fs-1"></i>
                <div class="fw-semibold text-dark mt-2">No pending documents in this queue</div>
                <div class="small">
                    Uploaded applicant documents appear here only when they are pending verification and inside your admission scope. Clear filters or open the applicant list if your team expects documents to review.
                </div>
                <div class="mt-3 d-flex flex-wrap justify-content-center gap-2">
                    <a href="{{ route('admission.documents.queue') }}" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
                    <a href="{{ route('admission.applicants.index') }}" class="btn btn-sm btn-outline-primary">Open Applicants</a>
                </div>
            </div>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th width="40">
                            <input type="checkbox" class="form-check-input" id="selectAll"
                                   onchange="toggleAll(this)">
                        </th>
                        <th>Applicant</th>
                        <th>Document</th>
                        <th>Uploaded</th>
                        <th>Size / Type</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($documents as $doc)
                    <tr>
                        <td>
                            <input type="checkbox" name="document_ids[]" value="{{ $doc->id }}"
                                   class="form-check-input doc-checkbox" onchange="updateBulkBtn()">
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $doc->applicant->user->name ?? 'Applicant name missing' }}</div>
                            <div class="small text-muted">
                                #{{ str_pad($doc->applicant_id, 5, '0', STR_PAD_LEFT) }}
                                &bull; {{ $doc->applicant->program->name ?? 'Program not assigned' }}
                            </div>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $doc->requiredDocument->name ?? $doc->original_name }}</div>
                            <div class="small text-muted">{{ $doc->original_name }}</div>
                            @if($doc->version > 1)
                                <span class="badge bg-info text-dark small">v{{ $doc->version }}</span>
                            @endif
                        </td>
                        <td>
                            @if($doc->uploaded_at)
                                <span title="{{ $doc->uploaded_at->format('d M Y H:i') }}">
                                    {{ $doc->uploaded_at->diffForHumans() }}
                                </span>
                            @else
                                <span class="text-muted">Upload time not captured</span>
                            @endif
                        </td>
                        <td>
                            <i class="bi {{ $doc->file_icon }} text-secondary me-1"></i>
                            {{ $doc->formatted_file_size }}
                            <div class="small text-muted">{{ strtoupper(pathinfo($doc->original_name, PATHINFO_EXTENSION)) }}</div>
                        </td>
                        <td>
                            <div class="d-flex gap-1 flex-wrap">
                                <a href="{{ route('admission.documents.preview', $doc) }}" target="_blank"
                                   class="btn btn-sm btn-outline-secondary" title="Preview">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('admission.documents.download', $doc) }}"
                                   class="btn btn-sm btn-outline-secondary" title="Download">
                                    <i class="bi bi-download"></i>
                                </a>
                                <form method="POST" action="{{ route('admission.documents.verify', $doc) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success" title="Verify"
                                            onclick="return confirm('Verify this document?')">
                                        <i class="bi bi-check-circle"></i> Verify
                                    </button>
                                </form>
                                <button type="button" class="btn btn-sm btn-danger" title="Reject"
                                        data-bs-toggle="modal" data-bs-target="#rejectModal"
                                        data-doc-id="{{ $doc->id }}"
                                        data-doc-name="{{ $doc->requiredDocument->name ?? $doc->original_name }}"
                                        data-applicant="{{ $doc->applicant->user->name ?? 'Applicant name missing' }}">
                                    <i class="bi bi-x-circle"></i> Reject
                                </button>
                                <a href="{{ route('admission.applicants.show', $doc->applicant) }}"
                                   class="btn btn-sm btn-outline-primary" title="View Applicant">
                                    <i class="bi bi-person"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</form>

{{-- Pagination --}}
@if($documents->hasPages())
<div class="mt-3 d-flex justify-content-center">
    {{ $documents->links() }}
</div>
@endif

{{-- Reject Modal --}}
@include('admission.documents._reject-modal')

@push('scripts')
<script>
function toggleAll(master) {
    document.querySelectorAll('.doc-checkbox').forEach(cb => cb.checked = master.checked);
    updateBulkBtn();
}

function updateBulkBtn() {
    const checked = document.querySelectorAll('.doc-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = checked;
    document.getElementById('bulkVerifyBtn').disabled = checked === 0;
    document.getElementById('selectAll').indeterminate = false;
}

const rejectModal = document.getElementById('rejectModal');
if (rejectModal) {
    rejectModal.addEventListener('show.bs.modal', function(event) {
        const btn = event.relatedTarget;
        document.getElementById('rejectDocId').value   = btn.dataset.docId;
        document.getElementById('rejectDocName').textContent    = btn.dataset.docName;
        document.getElementById('rejectApplicant').textContent  = btn.dataset.applicant;
        document.getElementById('rejectForm').action = '/admission/documents/' + btn.dataset.docId + '/reject';
    });
}
</script>
@endpush
@endsection
