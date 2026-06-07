@extends('layouts.admin')
@section('title', 'Grievance Detail')

@section('content')
<div class="mb-4">
    <a href="{{ route('hod.grievances.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
    <h4 class="mt-2 mb-0"><i class="bi bi-chat-square-text me-2 text-primary"></i>Grievance Detail</h4>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-x-circle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">Student</dt>
            <dd class="col-sm-9">{{ $grievance->student?->user?->name ?? '—' }}</dd>

            <dt class="col-sm-3">Program</dt>
            <dd class="col-sm-9">{{ $grievance->program?->name ?? '—' }}</dd>

            <dt class="col-sm-3">Category</dt>
            <dd class="col-sm-9">{{ ucfirst($grievance->category) }}</dd>

            <dt class="col-sm-3">Title</dt>
            <dd class="col-sm-9">{{ $grievance->title }}</dd>

            <dt class="col-sm-3">Priority</dt>
            <dd class="col-sm-9">
                @php $prioBadge = match($grievance->priority) { 'urgent'=>'danger','high'=>'warning','normal'=>'primary',default=>'secondary' }; @endphp
                <span class="badge bg-{{ $prioBadge }}">{{ ucfirst($grievance->priority) }}</span>
            </dd>

            <dt class="col-sm-3">Status</dt>
            <dd class="col-sm-9">
                @php $statBadge = match($grievance->status) { 'open'=>'warning','under_review'=>'info','escalated'=>'danger','resolved'=>'success',default=>'secondary' }; @endphp
                <span class="badge bg-{{ $statBadge }}">{{ ucwords(str_replace('_',' ',$grievance->status)) }}</span>
            </dd>

            <dt class="col-sm-3">Submitted</dt>
            <dd class="col-sm-9">{{ $grievance->created_at->format('d M Y H:i') }}</dd>

            <dt class="col-sm-3">Description</dt>
            <dd class="col-sm-9">{!! nl2br(e($grievance->description)) !!}</dd>

            @if($grievance->assignedTo)
            <dt class="col-sm-3">Assigned To</dt>
            <dd class="col-sm-9">{{ $grievance->assignedTo->name }}</dd>
            @endif

            @if($grievance->status === 'resolved')
            <dt class="col-sm-3">Resolved By</dt>
            <dd class="col-sm-9">{{ $grievance->resolvedBy?->name ?? '—' }} ({{ $grievance->resolved_at?->format('d M Y H:i') }})</dd>

            <dt class="col-sm-3">Resolution Notes</dt>
            <dd class="col-sm-9">{!! nl2br(e($grievance->resolution_notes)) !!}</dd>
            @endif
        </dl>
    </div>
</div>

@if(in_array($grievance->status, ['open','under_review','escalated']))
<div class="row g-3">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm border-start border-success border-3">
            <div class="card-header bg-transparent fw-semibold text-success">Resolve Grievance</div>
            <div class="card-body">
                <form method="POST" action="{{ route('hod.grievances.resolve', $grievance) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label form-label-sm">Resolution Notes <span class="text-danger">*</span></label>
                        <textarea name="resolution_notes" rows="4" class="form-control form-control-sm" required maxlength="1000"></textarea>
                    </div>
                    <button type="submit" class="btn btn-success btn-sm">Mark Resolved</button>
                </form>
            </div>
        </div>
    </div>
    @if($grievance->status !== 'escalated')
    <div class="col-md-6">
        <div class="card border-0 shadow-sm border-start border-warning border-3">
            <div class="card-header bg-transparent fw-semibold text-warning">Escalate to Dean</div>
            <div class="card-body">
                <p class="small text-muted">Escalate this grievance to Dean Academics for further action.</p>
                <form method="POST" action="{{ route('hod.grievances.escalate', $grievance) }}">
                    @csrf
                    <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Escalate this grievance?')">Escalate</button>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>
@endif
@endsection
