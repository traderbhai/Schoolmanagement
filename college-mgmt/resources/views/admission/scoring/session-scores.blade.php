@extends('layouts.admin')

@section('title', 'Score Sheet — ' . $session->session_name)

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

{{-- Header --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <span class="{{ $session->statusBadge }} me-2">{{ $session->statusLabel }}</span>
                <h3 class="fw-bold mt-2 mb-1"><i class="bi bi-clipboard2-data me-2"></i>Score Sheet</h3>
                <div class="text-muted">
                    <strong>{{ $session->session_name }}</strong> &mdash; {{ $session->step->typeLabel ?? $session->step->type }}
                </div>
                <div class="text-muted small mt-1">
                    <i class="bi bi-calendar3 me-1"></i>{{ $session->scheduled_date->format('d M Y') }}
                    &nbsp;<i class="bi bi-geo-alt me-1"></i>{{ $session->venue ?? 'N/A' }}
                    &nbsp;<i class="bi bi-mortarboard me-1"></i>{{ $session->program->name ?? 'N/A' }}
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admission.sessions.show', $session) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Back to Session
                </a>
            </div>
        </div>
    </div>
</div>

@php $parameters = $session->step->scoringParameters; @endphp

@if($presentApplicants->isEmpty())
    <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>No present applicants to score. Mark attendance first.</div>
@else
<form method="POST" action="{{ route('admission.sessions.scores.save', $session) }}">
    @csrf

    @foreach($presentApplicants as $sa)
    @php
        $applicant = $sa->applicant;
        $existing  = $existingScores[$applicant->id] ?? null;
        $paramScores = $existing ? ($existing->parameter_scores ?? []) : [];
    @endphp
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header d-flex justify-content-between align-items-center bg-white border-bottom">
            <div>
                <strong>{{ $applicant->user->name ?? 'Applicant #'.$applicant->id }}</strong>
                <span class="text-muted ms-2 small">{{ $applicant->application_number }}</span>
                @if($sa->panel_number)
                    <span class="badge bg-secondary ms-2">Panel {{ $sa->panel_number }}</span>
                @endif
            </div>
            @if($existing)
                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Scored{{ $existing->is_final ? ' (Final)' : '' }}</span>
            @else
                <span class="badge bg-secondary">Not Scored</span>
            @endif
        </div>
        <div class="card-body">
            <div class="row g-3">
                @foreach($parameters as $param)
                <div class="col-md-4 col-sm-6">
                    <label class="form-label small fw-semibold">{{ $param->name }} <span class="text-muted">(max: {{ $param->max_score }})</span></label>
                    <input type="number"
                        name="scores[{{ $applicant->id }}][param_{{ $param->id }}]"
                        class="form-control param-score"
                        data-applicant="{{ $applicant->id }}"
                        data-max="{{ $param->max_score }}"
                        min="0" max="{{ $param->max_score }}" step="0.5"
                        value="{{ $paramScores[$param->id] ?? '' }}"
                        placeholder="0–{{ $param->max_score }}">
                </div>
                @endforeach

                <div class="col-md-4 col-sm-6">
                    <label class="form-label small fw-semibold">Total Score</label>
                    <div class="input-group">
                        <input type="text" id="total-{{ $applicant->id }}" class="form-control bg-light" readonly
                            value="{{ $existing ? $existing->total_score . ' / ' . $existing->max_possible_score . ' (' . $existing->percentage . '%)' : '' }}">
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-semibold">Remarks</label>
                    <textarea name="scores[{{ $applicant->id }}][remarks]" class="form-control" rows="2"
                        placeholder="Optional remarks...">{{ $existing->remarks ?? '' }}</textarea>
                </div>
            </div>
        </div>
    </div>
    @endforeach

    <div class="d-flex gap-2 justify-content-end mt-3">
        <button type="submit" name="mark_final" value="0" class="btn btn-primary">
            <i class="bi bi-save me-1"></i>Save Scores
        </button>
        @if(app(\App\Services\DepartmentHierarchyService::class)->canApproveAdmission(auth()->user()))
        <button type="submit" name="mark_final" value="1" class="btn btn-success">
            <i class="bi bi-lock me-1"></i>Save & Mark Final
        </button>
        @endif
    </div>
</form>
@endif

{{-- Absent Applicants --}}
@if($absentApplicants->isNotEmpty())
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white border-bottom">
        <h6 class="mb-0 text-muted"><i class="bi bi-x-circle me-2"></i>Absent / Excused Applicants (not scored)</h6>
    </div>
    <div class="card-body p-0">
        <ul class="list-group list-group-flush">
            @foreach($absentApplicants as $sa)
            <li class="list-group-item text-muted opacity-75 d-flex align-items-center gap-2">
                <i class="bi bi-person-x"></i>
                {{ $sa->applicant->user->name ?? 'Applicant #'.$sa->applicant->id }}
                <span class="badge bg-secondary ms-1">{{ ucfirst($sa->attendance_status) }}</span>
            </li>
            @endforeach
        </ul>
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const inputs = document.querySelectorAll('.param-score');

    function recalcTotal(applicantId) {
        let total = 0, maxTotal = 0;
        document.querySelectorAll(`.param-score[data-applicant="${applicantId}"]`).forEach(input => {
            const val = parseFloat(input.value) || 0;
            const max = parseFloat(input.dataset.max) || 0;
            total += Math.min(val, max);
            maxTotal += max;
        });
        const pct = maxTotal > 0 ? ((total / maxTotal) * 100).toFixed(1) : 0;
        const totalEl = document.getElementById(`total-${applicantId}`);
        if (totalEl) totalEl.value = `${total.toFixed(1)} / ${maxTotal} (${pct}%)`;
    }

    inputs.forEach(input => {
        input.addEventListener('input', () => recalcTotal(input.dataset.applicant));
        recalcTotal(input.dataset.applicant);
    });
});
</script>
@endpush
