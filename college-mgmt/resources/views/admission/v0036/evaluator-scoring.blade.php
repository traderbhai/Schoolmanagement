@extends('layouts.admin')

@section('title', 'Evaluator Scoring')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h3 class="fw-bold mb-1">Evaluator Scoring Workspace</h3><div class="text-muted small">Draft, finalize, and lock rubric-based assessment scores.</div></div>
    <a href="{{ route('admission.assessment-control-room.index') }}" class="btn btn-outline-primary btn-sm">Control Room</a>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

@foreach($assignments as $assignment)
@php $rubric = $assignment->panel?->rubric; @endphp
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-transparent d-flex flex-wrap justify-content-between gap-2">
        <div>
            <strong>{{ $assignment->applicant?->user?->name }}</strong>
            <div class="small text-muted">{{ $assignment->panel?->name }} · {{ ucwords(str_replace('_', ' ', $assignment->lifecycle_status ?? 'invited')) }} · {{ ucfirst($assignment->score_status) }}</div>
        </div>
        <form method="POST" action="{{ route('admission.evaluator-scoring.lifecycle', $assignment) }}" class="d-flex gap-2">
            @csrf
            <select aria-label="Lifecycle Status" class="form-select form-select-sm" name="lifecycle_status">
                @foreach(['confirmed','checked_in','waiting','in_progress','completed','no_show','rescheduled','cancelled'] as $state)
                    <option value="{{ $state }}">{{ ucwords(str_replace('_', ' ', $state)) }}</option>
                @endforeach
            </select>
            <button class="btn btn-sm btn-outline-secondary">Update evaluation status</button>
        </form>
    </div>
    <div class="card-body">
        @if(!$rubric)
            <div class="alert alert-warning mb-0">No rubric attached to this panel.</div>
        @else
        <form method="POST" action="{{ route('admission.evaluator-scoring.save', $assignment) }}">
            @csrf
            <div class="row g-3">
                @foreach($rubric->criteria as $criterion)
                    <div class="col-lg-6">
                        <div class="border rounded p-2 h-100">
                            <label class="form-label small fw-semibold">{{ $criterion->name }} / {{ $criterion->max_score }}</label>
                            <input aria-label="{{ $criterion->name }} score" class="form-control form-control-sm mb-2" type="number" name="criteria[{{ $criterion->id }}][score]" min="0" max="{{ $criterion->max_score }}" step="0.5" value="0">
                            <textarea aria-label="{{ $criterion->name }} comment" class="form-control form-control-sm" name="criteria[{{ $criterion->id }}][comment]" rows="2" placeholder="{{ $criterion->requires_comment ? 'Comment required' : 'Comment' }}"></textarea>
                        </div>
                    </div>
                @endforeach
                <div class="col-md-4">
                    <select aria-label="Recommendation" class="form-select form-select-sm" name="recommendation">
                        @foreach(($rubric->recommendation_options ?: ['recommended','waitlist','not_recommended']) as $option)
                            <option value="{{ $option }}">{{ ucwords(str_replace('_', ' ', $option)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-8 d-flex gap-2">
                    <button class="btn btn-outline-primary btn-sm" name="finalize" value="0">Save Draft</button>
                    <button class="btn btn-success btn-sm" name="finalize" value="1">Submit Final</button>
                </div>
            </div>
        </form>
        @endif
    </div>
</div>
@endforeach

<div class="mt-3">{{ $assignments->links() }}</div>
@endsection
