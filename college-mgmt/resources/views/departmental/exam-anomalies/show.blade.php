@extends('layouts.admin')
@section('title', 'Anomaly Detail')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center mb-4 gap-3">
        <a href="{{ route('exam-cell.anomalies.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
        <div>
            <h4 class="fw-bold mb-0">Exam Anomaly #{{ $anomalyLog->id }}</h4>
            <span class="text-muted small">{{ ucwords(str_replace('_',' ',$anomalyLog->anomaly_type)) }}</span>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    @php $sev = ['critical'=>'danger','high'=>'warning','medium'=>'info','low'=>'secondary'][$anomalyLog->severity] ?? 'secondary'; @endphp

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3 text-muted">Exam</dt>
                        <dd class="col-sm-9">{{ $anomalyLog->exam->subject->name ?? $anomalyLog->exam->name ?? '—' }}</dd>
                        <dt class="col-sm-3 text-muted">Student</dt>
                        <dd class="col-sm-9">{{ $anomalyLog->student->user->name ?? '—' }} ({{ $anomalyLog->student->enrollment_number ?? '' }})</dd>
                        <dt class="col-sm-3 text-muted">Anomaly Type</dt>
                        <dd class="col-sm-9"><span class="badge bg-light text-dark">{{ ucwords(str_replace('_',' ',$anomalyLog->anomaly_type)) }}</span></dd>
                        <dt class="col-sm-3 text-muted">Severity</dt>
                        <dd class="col-sm-9"><span class="badge bg-{{ $sev }} fs-6">{{ ucfirst($anomalyLog->severity) }}</span></dd>
                        <dt class="col-sm-3 text-muted">Action Taken</dt>
                        <dd class="col-sm-9">{{ $anomalyLog->action_taken ? ucfirst($anomalyLog->action_taken) : 'Pending decision' }}</dd>
                        <dt class="col-sm-3 text-muted">Reported By</dt>
                        <dd class="col-sm-9">{{ $anomalyLog->reportedBy->name ?? '—' }} on {{ $anomalyLog->created_at->format('d M Y, g:i a') }}</dd>
                        @if($anomalyLog->resolved_at)
                        <dt class="col-sm-3 text-muted">Resolved By</dt>
                        <dd class="col-sm-9">{{ $anomalyLog->resolvedBy->name ?? '—' }} on {{ $anomalyLog->resolved_at->format('d M Y') }}</dd>
                        @endif
                        <dt class="col-sm-3 text-muted">Description</dt>
                        <dd class="col-sm-9">{{ $anomalyLog->description }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        @if(!$anomalyLog->resolved_at)
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm border-start border-warning border-3">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3"><i class="bi bi-check-circle me-1 text-success"></i>Take Action</h6>
                    <form method="POST" action="{{ route('exam-cell.anomalies.resolve', $anomalyLog) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Action <span class="text-danger">*</span></label>
                            <select name="action_taken" class="form-select form-select-sm" required>
                                <option value="">Select Action</option>
                                <option value="none">No Action</option>
                                <option value="warning">Issue Warning</option>
                                <option value="debarred">Debar Student</option>
                                <option value="cancelled">Cancel Exam</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Resolution Notes <span class="text-danger">*</span></label>
                            <textarea name="resolution_notes" class="form-control form-control-sm @error('resolution_notes') is-invalid @enderror" rows="3" required>{{ old('resolution_notes') }}</textarea>
                            @error('resolution_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-warning btn-sm w-100">Resolve Anomaly</button>
                    </form>
                </div>
            </div>
        </div>
        @else
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm border-start border-success border-3">
                <div class="card-body">
                    <h6 class="fw-semibold text-success"><i class="bi bi-check-circle me-1"></i>Resolved</h6>
                    <p class="mb-0 small text-muted">Action: <strong>{{ ucfirst($anomalyLog->action_taken) }}</strong></p>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
