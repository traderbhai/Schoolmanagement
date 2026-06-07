@extends('layouts.admin')
@section('title', 'Curriculum Change Detail')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center mb-4 gap-3">
        <a href="{{ route('academic.curriculum-changes.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h4 class="fw-bold mb-0">{{ $curriculumChange->title }}</h4>
            <span class="text-muted small">Curriculum Change #{{ $curriculumChange->id }}</span>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-4 pb-0">
                    <h6 class="fw-semibold text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.08em">PROPOSAL DETAILS</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3 text-muted">Program</dt>
                        <dd class="col-sm-9">{{ $curriculumChange->program->name ?? '—' }}</dd>
                        <dt class="col-sm-3 text-muted">Subject</dt>
                        <dd class="col-sm-9">{{ $curriculumChange->subject?->name ?? '—' }}</dd>
                        <dt class="col-sm-3 text-muted">Change Type</dt>
                        <dd class="col-sm-9"><span class="badge bg-light text-dark">{{ ucfirst(str_replace('_',' ',$curriculumChange->change_type)) }}</span></dd>
                        <dt class="col-sm-3 text-muted">Proposed By</dt>
                        <dd class="col-sm-9">{{ $curriculumChange->proposedBy->name ?? '—' }}</dd>
                        <dt class="col-sm-3 text-muted">Submitted</dt>
                        <dd class="col-sm-9">{{ $curriculumChange->submitted_at?->format('d M Y, g:i a') ?? 'Draft' }}</dd>
                        <dt class="col-sm-3 text-muted">Status</dt>
                        <dd class="col-sm-9">
                            @php $colors = ['draft'=>'secondary','submitted'=>'warning','under_review'=>'info','approved'=>'success','rejected'=>'danger']; @endphp
                            <span class="badge bg-{{ $colors[$curriculumChange->status] ?? 'secondary' }} fs-6">
                                {{ ucfirst(str_replace('_',' ',$curriculumChange->status)) }}
                            </span>
                        </dd>
                        <dt class="col-sm-3 text-muted">Description</dt>
                        <dd class="col-sm-9">{{ $curriculumChange->description }}</dd>
                    </dl>
                </div>
            </div>

            @if($curriculumChange->reviewed_by)
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-4 pb-0">
                    <h6 class="fw-semibold text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.08em">REVIEW DECISION</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3 text-muted">Reviewed By</dt>
                        <dd class="col-sm-9">{{ $curriculumChange->reviewedBy->name ?? '—' }}</dd>
                        <dt class="col-sm-3 text-muted">Reviewed At</dt>
                        <dd class="col-sm-9">{{ $curriculumChange->reviewed_at?->format('d M Y, g:i a') }}</dd>
                        <dt class="col-sm-3 text-muted">Remarks</dt>
                        <dd class="col-sm-9">{{ $curriculumChange->review_remarks ?? '—' }}</dd>
                    </dl>
                </div>
            </div>
            @endif
        </div>

        @if(auth()->user()->hasAnyRole(['dean_academics','admin']) && in_array($curriculumChange->status, ['submitted','under_review']))
        <div class="col-lg-4">
            {{-- Approve --}}
            <div class="card border-0 shadow-sm mb-3 border-start border-success border-3">
                <div class="card-body">
                    <h6 class="fw-semibold text-success mb-3"><i class="bi bi-check-circle me-1"></i>Approve</h6>
                    <form method="POST" action="{{ route('academic.curriculum-changes.approve', $curriculumChange) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Remarks (optional)</label>
                            <textarea name="remarks" rows="3" class="form-control form-control-sm" placeholder="Optional approval remarks..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-success btn-sm w-100"
                            onclick="return confirm('Approve this curriculum change?')">
                            <i class="bi bi-check-lg me-1"></i>Approve Change
                        </button>
                    </form>
                </div>
            </div>
            {{-- Reject --}}
            <div class="card border-0 shadow-sm border-start border-danger border-3">
                <div class="card-body">
                    <h6 class="fw-semibold text-danger mb-3"><i class="bi bi-x-circle me-1"></i>Reject</h6>
                    <form method="POST" action="{{ route('academic.curriculum-changes.reject', $curriculumChange) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Reason <span class="text-danger">*</span></label>
                            <textarea name="remarks" rows="3" class="form-control form-control-sm" placeholder="Explain why this is rejected..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger btn-sm w-100"
                            onclick="return confirm('Reject this curriculum change?')">
                            <i class="bi bi-x-lg me-1"></i>Reject Change
                        </button>
                    </form>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
