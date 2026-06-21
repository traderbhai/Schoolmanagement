@extends('layouts.admin')

@section('title', 'Scorecard - ' . ($applicant->user->name ?? 'Applicant'))

@section('content')

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h3 class="fw-bold mb-1"><i class="bi bi-bar-chart-steps me-2"></i>Applicant Scorecard</h3>
                <div class="text-muted">
                    <strong>{{ $applicant->user->name ?? 'Applicant name missing' }}</strong>
                    - {{ $applicant->application_number ?? 'Application number missing' }}
                    &nbsp;<span class="{{ $applicant->statusBadge }}">{{ $applicant->statusLabel }}</span>
                </div>
                <div class="text-muted small mt-1">
                    <i class="bi bi-mortarboard me-1"></i>{{ $applicant->program->name ?? 'Program not assigned' }}
                    @if($applicant->batch)
                        &nbsp;|&nbsp;{{ $applicant->batch->name }}
                    @endif
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admission.applicants.show', $applicant) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Back to Applicant
                </a>
            </div>
        </div>
    </div>
</div>

@if($scores->isEmpty())
    <div class="alert alert-info">
        <div class="fw-semibold mb-1"><i class="bi bi-info-circle me-2"></i>No assessment scores are recorded for this applicant yet</div>
        <div class="small">
            Scores appear here after the applicant is assigned to a selection session, marked Present, and scored from the session score sheet.
            If the applicant has completed an assessment, review the session attendance and evaluator score queues before selection committee decisions.
        </div>
        <div class="d-flex gap-2 flex-wrap mt-3">
            <a href="{{ route('admission.sessions.index') }}" class="btn btn-sm btn-outline-primary">Open selection sessions</a>
            <a href="{{ route('admission.assessment-operations.index') }}" class="btn btn-sm btn-outline-secondary">Open assessment operations</a>
        </div>
    </div>
@else
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-primary">{{ $scores->count() }}</div>
                <div class="small text-muted">Steps Scored</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-success">{{ number_format($totalWeighted, 2) }}</div>
                <div class="small text-muted">Weighted Score (Preview)</div>
            </div>
        </div>
    </div>
    @if($meritListEntry)
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-warning">Rank {{ $meritListEntry->rank }}</div>
                <div class="small text-muted">Merit Rank</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold"><span class="{{ $meritListEntry->decisionBadge }}">{{ $meritListEntry->decisionLabel }}</span></div>
                <div class="small text-muted">Decision</div>
            </div>
        </div>
    </div>
    @endif
</div>

@foreach($stepsData as $item)
@php
    $score = $item['score'];
    $step  = $item['step'];
@endphp
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
        <div>
            <strong>{{ $step->name ?? $step->typeLabel }}</strong>
            <span class="badge bg-secondary ms-2">{{ $step->typeLabel }}</span>
            @if($score->is_final)
                <span class="badge bg-success ms-1">Final</span>
            @endif
        </div>
        <div class="text-end">
            <span class="badge bg-primary fs-6">Grade: {{ $score->grade }}</span>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-sm-3 text-center">
                <div class="fs-4 fw-bold text-primary">{{ $score->total_score }}</div>
                <div class="small text-muted">Total Score</div>
            </div>
            <div class="col-sm-3 text-center">
                <div class="fs-4 fw-bold text-secondary">{{ $score->max_possible_score }}</div>
                <div class="small text-muted">Max Possible</div>
            </div>
            <div class="col-sm-3 text-center">
                <div class="fs-4 fw-bold text-info">{{ $score->percentage }}%</div>
                <div class="small text-muted">Percentage</div>
            </div>
            <div class="col-sm-3 text-center">
                <div class="fs-4 fw-bold text-success">{{ number_format($item['weighted_contribution'], 2) }}</div>
                <div class="small text-muted">Weighted Contribution ({{ $step->weightage }}%)</div>
            </div>
        </div>

        @if($score->parameter_scores)
        <h6 class="text-muted small mb-2">Parameter Breakdown</h6>
        <div class="row g-2">
            @foreach($step->scoringParameters as $param)
            <div class="col-md-3 col-sm-4">
                <div class="border rounded p-2 text-center">
                    <div class="small text-muted">{{ $param->name }}</div>
                    <div class="fw-bold">{{ $score->parameter_scores[$param->id] ?? 'Score not entered' }} / {{ $param->max_score }}</div>
                </div>
            </div>
            @endforeach
        </div>
        @endif

        @if($score->remarks)
        <div class="mt-3 p-2 bg-light rounded small text-muted">
            <i class="bi bi-chat-quote me-1"></i>{{ $score->remarks }}
        </div>
        @endif

        <div class="mt-2 text-muted small">
            Scored by {{ $score->scoredBy->name ?? 'Scorer not recorded' }} on {{ $score->created_at->format('d M Y H:i') }}
        </div>
    </div>
</div>
@endforeach
@endif

@endsection
