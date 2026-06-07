@extends('layouts.student')
@section('title', 'Grievance — ' . $grievance->subject)
@section('page-title', 'Grievance Detail')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('student.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('student.grievances.index') }}">Grievances</a></li>
    <li class="breadcrumb-item active">#{{ $grievance->id }}</li>
@endsection

@section('content')
<div class="row g-4">
    <div class="col-md-8">
        <div class="card" style="box-shadow:var(--shadow-sm)">
            <div class="card-header bg-transparent border-0 pt-3 pb-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0">{{ $grievance->subject }}</h6>
                {!! $grievance->status_badge !!}
            </div>
            <div class="card-body">
                <div class="d-flex gap-2 mb-3">
                    {!! $grievance->priority_badge !!}
                    <span class="badge bg-secondary-subtle text-secondary" style="font-size:.7rem">
                        {{ ucfirst($grievance->category) }}
                    </span>
                </div>

                <h6 class="small fw-semibold text-muted text-uppercase mb-1" style="letter-spacing:.05em">Your Description</h6>
                <div class="p-3 rounded mb-4" style="background:var(--clr-card-bg,#f8fafc);border:1px solid var(--clr-border,#e2e8f0);white-space:pre-wrap;font-size:.88rem">
                    {{ $grievance->description }}
                </div>

                @if($grievance->resolution)
                <div class="alert alert-success mb-0">
                    <div class="fw-semibold mb-1"><i class="bi bi-check-circle me-2"></i>Resolution</div>
                    <div style="white-space:pre-wrap;font-size:.88rem">{{ $grievance->resolution }}</div>
                    @if($grievance->resolved_at)
                    <div class="text-success mt-2" style="font-size:.75rem">
                        Resolved on {{ $grievance->resolved_at->format('d M Y, H:i') }}
                        @if($grievance->assignedTo) by {{ $grievance->assignedTo->name }} @endif
                    </div>
                    @endif
                </div>
                @elseif(in_array($grievance->status, ['open', 'in_progress']))
                <div class="alert alert-info mb-0">
                    <i class="bi bi-hourglass-split me-2"></i>
                    Your grievance is currently <strong>{{ str_replace('_', ' ', $grievance->status) }}</strong>.
                    You will be notified once a resolution is provided.
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card" style="box-shadow:var(--shadow-sm)">
            <div class="card-header bg-transparent border-0 pt-3 pb-0">
                <h6 class="fw-semibold mb-0 small text-uppercase text-muted" style="letter-spacing:.05em">Details</h6>
            </div>
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-5 text-muted fw-normal">Reference</dt>
                    <dd class="col-7 fw-semibold">#{{ str_pad($grievance->id, 4, '0', STR_PAD_LEFT) }}</dd>

                    <dt class="col-5 text-muted fw-normal">Submitted</dt>
                    <dd class="col-7">{{ $grievance->created_at->format('d M Y, H:i') }}</dd>

                    <dt class="col-5 text-muted fw-normal">Last Updated</dt>
                    <dd class="col-7">{{ $grievance->updated_at->diffForHumans() }}</dd>

                    @if($grievance->assignedTo)
                    <dt class="col-5 text-muted fw-normal">Assigned To</dt>
                    <dd class="col-7">{{ $grievance->assignedTo->name }}</dd>
                    @endif
                </dl>
            </div>
        </div>

        <div class="mt-3">
            <a href="{{ route('student.grievances.index') }}" class="btn btn-outline-secondary btn-sm w-100">
                <i class="bi bi-arrow-left me-1"></i>Back to My Grievances
            </a>
        </div>
    </div>
</div>
@endsection
