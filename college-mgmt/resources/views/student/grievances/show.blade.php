@extends('layouts.student')
@section('title', 'Grievance Detail')

@section('content')
<div class="mb-4">
    <a href="{{ route('student.grievances.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
    <h4 class="mt-2 mb-0"><i class="bi bi-chat-square-text me-2 text-primary"></i>Grievance Detail</h4>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-4">Title</dt>
            <dd class="col-sm-8">{{ $grievance->title }}</dd>

            <dt class="col-sm-4">Category</dt>
            <dd class="col-sm-8">{{ ucfirst($grievance->category) }}</dd>

            <dt class="col-sm-4">Priority</dt>
            <dd class="col-sm-8">
                @php $prioBadge = match($grievance->priority) { 'urgent'=>'danger','high'=>'warning','normal'=>'primary',default=>'secondary' }; @endphp
                <span class="badge bg-{{ $prioBadge }}">{{ ucfirst($grievance->priority) }}</span>
            </dd>

            <dt class="col-sm-4">Status</dt>
            <dd class="col-sm-8">
                @php $statBadge = match($grievance->status) { 'open'=>'warning','under_review'=>'info','escalated'=>'danger','resolved'=>'success',default=>'secondary' }; @endphp
                <span class="badge bg-{{ $statBadge }}">{{ ucwords(str_replace('_',' ',$grievance->status)) }}</span>
            </dd>

            <dt class="col-sm-4">Submitted</dt>
            <dd class="col-sm-8">{{ $grievance->created_at->format('d M Y H:i') }}</dd>

            <dt class="col-sm-4">Description</dt>
            <dd class="col-sm-8">{!! nl2br(e($grievance->description)) !!}</dd>

            @if($grievance->status === 'resolved')
            <dt class="col-sm-4">Resolved At</dt>
            <dd class="col-sm-8">{{ $grievance->resolved_at?->format('d M Y H:i') }}</dd>

            <dt class="col-sm-4">Resolution Notes</dt>
            <dd class="col-sm-8">{!! nl2br(e($grievance->resolution_notes)) !!}</dd>
            @endif
        </dl>
    </div>
</div>
@endsection
