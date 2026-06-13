@extends('layouts.admin')
@section('title', 'Grievance #' . str_pad($grievance->id, 4, '0', STR_PAD_LEFT))

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('admin.grievances.index') }}">Grievances</a></li>
                    <li class="breadcrumb-item active">#{{ str_pad($grievance->id, 4, '0', STR_PAD_LEFT) }}</li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0">{{ $grievance->title }}</h4>
        </div>
        <a href="{{ route('admin.grievances.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif
    @if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="row g-4">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-3 pb-0 d-flex gap-2 align-items-center">
                    {!! $grievance->status_badge !!}
                    {!! $grievance->priority_badge !!}
                    <span class="badge bg-secondary-subtle text-secondary" style="font-size:.7rem">{{ ucfirst($grievance->category) }}</span>
                </div>
                <div class="card-body">
                    <h6 class="small fw-semibold text-muted text-uppercase mb-2" style="letter-spacing:.05em">Student Description</h6>
                    <div class="p-3 rounded" style="background:#f8fafc;border:1px solid #e2e8f0;white-space:pre-wrap;font-size:.88rem">{{ $grievance->description }}</div>
                    @if($grievance->resolution_notes)
                    <h6 class="small fw-semibold text-muted text-uppercase mt-4 mb-2" style="letter-spacing:.05em">Resolution Notes</h6>
                    <div class="p-3 rounded bg-success-subtle text-success small">{{ $grievance->resolution_notes }}</div>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h6 class="fw-semibold mb-0">Update Grievance</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.grievances.update', $grievance) }}">
                        @csrf @method('PATCH')

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Status</label>
                                <select name="status" class="form-select form-select-sm">
                                    @foreach(['open','under_review','escalated','resolved','closed'] as $s)
                                    <option value="{{ $s }}" @selected($grievance->status === $s)>{{ ucwords(str_replace('_',' ',$s)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Assign To</label>
                                <select name="assigned_to" class="form-select form-select-sm">
                                    <option value="">Unassigned</option>
                                    @foreach($staffUsers as $user)
                                    <option value="{{ $user->id }}" @selected($grievance->assigned_to == $user->id)>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Resolution / Response Notes</label>
                                <textarea name="resolution_notes" rows="5" class="form-control form-control-sm" placeholder="Describe actions taken, staff response, and final resolution when applicable.">{{ old('resolution_notes', $grievance->resolution_notes) }}</textarea>
                            </div>
                        </div>

                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h6 class="fw-semibold mb-0 small text-uppercase text-muted" style="letter-spacing:.05em">Student</h6>
                </div>
                <div class="card-body small">
                    <div class="fw-bold">{{ $grievance->student?->user?->name ?? '-' }}</div>
                    <div class="text-muted">{{ $grievance->student?->enrollment_number ?? '' }}</div>
                    <div class="text-muted">{{ $grievance->student?->program?->name ?? '' }}</div>
                    <div class="mt-2">
                        <a href="{{ route('admin.students.show', $grievance->student_id) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-person me-1"></i>View Profile
                        </a>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h6 class="fw-semibold mb-0 small text-uppercase text-muted" style="letter-spacing:.05em">Timeline</h6>
                </div>
                <div class="card-body small">
                    <dl class="row mb-0">
                        <dt class="col-5 text-muted fw-normal">Reference</dt>
                        <dd class="col-7">#{{ str_pad($grievance->id, 4, '0', STR_PAD_LEFT) }}</dd>
                        <dt class="col-5 text-muted fw-normal">Submitted</dt>
                        <dd class="col-7">{{ $grievance->created_at->format('d M Y H:i') }}</dd>
                        <dt class="col-5 text-muted fw-normal">Last Update</dt>
                        <dd class="col-7">{{ $grievance->updated_at->diffForHumans() }}</dd>
                        @if($grievance->resolved_at)
                        <dt class="col-5 text-muted fw-normal">Resolved</dt>
                        <dd class="col-7 text-success">{{ $grievance->resolved_at->format('d M Y H:i') }}</dd>
                        @endif
                        @if($grievance->assignedTo)
                        <dt class="col-5 text-muted fw-normal">Assigned To</dt>
                        <dd class="col-7">{{ $grievance->assignedTo->name }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
