@extends('layouts.admin')

@section('title', 'Student Document Requests')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="mb-0 fw-bold"><i class="bi bi-file-earmark-check me-2 text-primary"></i>Student Document Requests</h2>
        <small class="text-muted">Approve, reject, and fulfill official student document requests.</small>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-1 fw-bold text-warning">{{ $stats['pending'] }}</div>
            <div class="small text-muted">Pending Review</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-1 fw-bold text-info">{{ $stats['approved'] }}</div>
            <div class="small text-muted">In Processing</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-1 fw-bold text-success">{{ $stats['ready_today'] }}</div>
            <div class="small text-muted">Ready Today</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-1 fw-bold text-danger">{{ $stats['rejected_today'] }}</div>
            <div class="small text-muted">Rejected Today</div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.document-requests.index') }}" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Status</label>
                <select aria-label="Status" name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    @foreach(['pending' => 'Pending', 'approved' => 'Processing', 'ready' => 'Ready', 'rejected' => 'Rejected'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Document Type</label>
                <select aria-label="Document Type" name="document_type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    @foreach($types as $type)
                        <option value="{{ $type }}" @selected(request('document_type') === $type)>{{ \App\Models\DocumentRequest::typeLabel($type) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Program</label>
                <select aria-label="Program" name="program_id" class="form-select form-select-sm">
                    <option value="">All Programs</option>
                    @foreach($programs as $program)
                        <option value="{{ $program->id }}" @selected(request('program_id') == $program->id)>{{ $program->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Student</label>
                <input aria-label="Name or email" type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Name or email">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('admin.document-requests.index') }}" class="btn btn-outline-secondary btn-sm flex-fill">Clear</a>
            </div>
        </form>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
        <button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
        {{ session('error') }}
        <button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        <div class="fw-semibold mb-1">Please fix the highlighted request action.</div>
        <ul class="mb-0 small">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Requests ({{ $requests->total() }})</span>
    </div>

    @if($requests->isEmpty())
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-folder2-open fs-1"></i>
            <p class="mt-2 mb-0">No student document requests match the current filters.</p>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Student</th>
                        <th scope="col">Document</th>
                        <th scope="col">Purpose</th>
                        <th scope="col">Status</th>
                        <th scope="col">Requested</th>
                        <th scope="col" style="min-width:360px">Staff Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($requests as $request)
                    @php
                        $statusClasses = [
                            'pending' => 'bg-warning text-dark',
                            'approved' => 'bg-info',
                            'ready' => 'bg-success',
                            'rejected' => 'bg-danger',
                        ];
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $request->student->user->name ?? 'Student' }}</div>
                            <div class="small text-muted">
                                {{ $request->student->enrollment_number ?? 'No enrollment number' }}
                                @if($request->student?->program)
                                    &bull; {{ $request->student->program->name }}
                                @endif
                            </div>
                            <div class="small text-muted">{{ $request->student->user->email ?? '' }}</div>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ \App\Models\DocumentRequest::typeLabel($request->document_type) }}</div>
                            @if($request->additional_info)
                                <div class="small text-muted">{{ $request->additional_info }}</div>
                            @endif
                        </td>
                        <td class="small text-muted">{{ $request->purpose ?: '-' }}</td>
                        <td>
                            <span class="badge {{ $statusClasses[$request->status] ?? 'bg-secondary' }}">
                                {{ ucfirst($request->status === 'approved' ? 'processing' : $request->status) }}
                            </span>
                            @if($request->reviewer)
                                <div class="small text-muted mt-1">By {{ $request->reviewer->name }}</div>
                            @endif
                            @if($request->fulfilled_at)
                                <div class="small text-muted">{{ $request->fulfilled_at->format('d M Y H:i') }}</div>
                            @endif
                        </td>
                        <td class="small text-muted">{{ $request->created_at->format('d M Y H:i') }}</td>
                        <td>
                            @if(in_array($request->status, ['pending', 'approved'], true))
                                <div class="d-flex flex-column gap-2">
                                    @if($request->status === 'pending')
                                        <form method="POST" action="{{ route('admin.document-requests.approve', $request) }}" class="d-flex gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <input aria-label="Processing note" type="text" name="notes" class="form-control form-control-sm" placeholder="Optional processing note">
                                            <button type="submit" class="btn btn-sm btn-outline-success">Approve document request</button>
                                        </form>
                                    @endif

                                    @if($request->status === 'approved')
                                        <form method="POST" action="{{ route('admin.document-requests.fulfill', $request) }}" enctype="multipart/form-data" class="d-flex gap-2">
                                            @csrf
                                            <input aria-label="Document File" type="file" name="document_file" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png" required>
                                            <input aria-label="Student note" type="text" name="notes" class="form-control form-control-sm" placeholder="Student note">
                                            <button type="submit" class="btn btn-sm btn-primary">Mark Ready</button>
                                        </form>
                                    @endif

                                    <form method="POST" action="{{ route('admin.document-requests.reject', $request) }}" class="d-flex gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <input aria-label="Rejection reason" type="text" name="notes" class="form-control form-control-sm" placeholder="Rejection reason" required>
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Reject this student document request? Confirm the rejection reason explains the missing/invalid requirement and the student can act on it before the request is closed.')">Reject document request</button>
                                    </form>
                                </div>
                            @else
                                <div class="small text-muted mb-2">{{ $request->notes ?: 'No staff notes recorded.' }}</div>
                                @if($request->output_path)
                                    <a href="{{ route('admin.document-requests.download', $request) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-download me-1"></i>Download
                                    </a>
                                @endif
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($requests->hasPages())
        <div class="card-footer bg-transparent">{{ $requests->links() }}</div>
    @endif
</div>
@endsection
