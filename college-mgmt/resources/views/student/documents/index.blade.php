@extends('layouts.student')
@section('title', 'Document Requests')
@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-semibold mb-0">Document Requests</h4>
        <a href="{{ route('student.documents.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Request Document
        </a>
    </div>

    @if($requests->isEmpty())
    <div class="alert alert-info">No document requests yet.</div>
    @else
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr><th>Document Type</th><th>Purpose</th><th>Requested</th><th>Status</th><th>Notes</th></tr>
            </thead>
            <tbody>
                @foreach($requests as $r)
                <tr>
                    <td class="fw-semibold">{{ \App\Models\DocumentRequest::typeLabel($r->document_type) }}</td>
                    <td class="text-muted small">{{ $r->purpose ?: '—' }}</td>
                    <td class="text-muted small">{{ $r->created_at->format('d M Y') }}</td>
                    <td>
                        @if($r->status === 'pending') <span class="badge bg-warning text-dark">Pending</span>
                        @elseif($r->status === 'approved') <span class="badge bg-info">Processing</span>
                        @elseif($r->status === 'ready')
                            <span class="badge bg-success">Ready</span>
                            @if($r->output_path)
                            <a href="#" class="btn btn-sm btn-outline-success ms-1 py-0 px-2">Download</a>
                            @endif
                        @else <span class="badge bg-danger">Rejected</span>
                        @endif
                    </td>
                    <td class="text-muted small">{{ $r->notes ?: '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $requests->links() }}
    @endif
</div>
@endsection
