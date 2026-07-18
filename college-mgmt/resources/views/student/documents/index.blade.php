@extends('layouts.student')
@section('title', 'Document Requests')
@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-semibold mb-0">Document Requests</h4>
            <div class="text-muted small">Request and track official student documents.</div>
        </div>
        <a href="{{ route('student.documents.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Request Document
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="text-uppercase text-muted fw-semibold mb-1" style="font-size:.72rem;letter-spacing:.04em">Document Priority</div>
                <h5 class="fw-bold mb-1">{{ $documentPriority['title'] }}</h5>
                <p class="text-muted mb-0">{{ $documentPriority['body'] }}</p>
            </div>
            <a href="{{ $documentPriority['route'] }}" class="btn btn-sm {{ $documentPriority['level'] === 'danger' ? 'btn-danger' : ($documentPriority['level'] === 'success' ? 'btn-success' : 'btn-primary') }}">
                <i class="bi bi-arrow-right-circle me-1"></i>{{ $documentPriority['action'] }}
            </a>
        </div>
    </div>

    @if($requests->isEmpty())
    <div class="alert alert-info d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <div class="fw-semibold mb-1">No document requests yet.</div>
            <div class="small mb-0">
                Use this page to request bonafide, fee, character, migration, NOC, or ID documents. Once submitted, the request status and download action will appear here.
            </div>
        </div>
        <a href="{{ route('student.documents.create') }}" class="btn btn-sm btn-primary align-self-start align-self-md-center">
            Request Document
        </a>
    </div>
    @else
    <div class="card border-0 shadow-sm">
        <div class="d-md-none">
            <div class="vstack gap-2 p-3">
                @foreach($requests as $r)
                @php
                    $statusClasses = [
                        'pending' => 'bg-warning text-dark',
                        'approved' => 'bg-info',
                        'ready' => 'bg-success',
                        'rejected' => 'bg-danger',
                    ];
                @endphp
                <div class="border rounded-2 p-3 bg-white">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <div class="fw-semibold">{{ \App\Models\DocumentRequest::typeLabel($r->document_type) }}</div>
                        <span class="badge {{ $statusClasses[$r->status] ?? 'bg-secondary' }}">{{ ucfirst($r->status === 'approved' ? 'processing' : $r->status) }}</span>
                    </div>
                    <div class="small text-muted mb-1">Requested {{ $r->created_at->format('d M Y') }}</div>
                    <div class="small mb-2">{{ $r->purpose ?: 'Purpose not recorded' }}</div>
                    @if($r->notes)
                        <div class="small text-muted mb-2">{{ $r->notes }}</div>
                    @endif
                    @if($r->status === 'ready' && $r->output_path)
                        <a href="{{ route('student.documents.download', $r) }}" class="btn btn-sm btn-outline-success w-100">Download</a>
                    @else
                        <div class="small text-muted">Download will appear when the document is ready.</div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        <div class="table-responsive d-none d-md-block">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Document Type</th>
                        <th scope="col">Purpose</th>
                        <th scope="col">Requested</th>
                        <th scope="col">Status</th>
                        <th scope="col">Notes</th>
                        <th scope="col" class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($requests as $r)
                    @php
                        $statusClasses = [
                            'pending' => 'bg-warning text-dark',
                            'approved' => 'bg-info',
                            'ready' => 'bg-success',
                            'rejected' => 'bg-danger',
                        ];
                    @endphp
                    <tr>
                        <td class="fw-semibold">{{ \App\Models\DocumentRequest::typeLabel($r->document_type) }}</td>
                        <td class="text-muted small">{{ $r->purpose ?: '-' }}</td>
                        <td class="text-muted small">{{ $r->created_at->format('d M Y') }}</td>
                        <td><span class="badge {{ $statusClasses[$r->status] ?? 'bg-secondary' }}">{{ ucfirst($r->status === 'approved' ? 'processing' : $r->status) }}</span></td>
                        <td class="text-muted small">{{ $r->notes ?: '-' }}</td>
                        <td class="text-end">
                            @if($r->status === 'ready' && $r->output_path)
                                <a href="{{ route('student.documents.download', $r) }}" class="btn btn-sm btn-outline-success py-0 px-2">Download</a>
                            @else
                                <span class="text-muted small">-</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($requests->hasPages())
            <div class="card-footer bg-transparent">{{ $requests->links() }}</div>
        @endif
    </div>
    @endif
</div>
@endsection
