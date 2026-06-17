@extends('layouts.student')
@section('title', 'Course Feedback')
@section('page-title', 'Course Feedback')

@section('content')
<div class="container-fluid py-3" style="max-width:800px">
    <p class="text-muted mb-4 small">Share anonymous feedback for your enrolled courses. Responses are visible only to HOD and Dean.</p>

    @if(session('success'))
        <div class="alert alert-success py-2">{{ session('success') }}</div>
    @endif
    @if(session('info'))
        <div class="alert alert-info py-2">{{ session('info') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-warning py-2">{{ session('error') }}</div>
    @endif
    @unless($canSubmitFeedback)
        <div class="alert alert-secondary py-2 small">Feedback submission is locked because your student profile is not active. Submitted feedback remains visible in history.</div>
    @endunless

    @if($subjects->isEmpty())
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-star fs-1 d-block mb-2"></i>
            No enrolled subjects found for feedback.
        </div>
    </div>
    @else
    <div class="list-group shadow-sm">
        @foreach($subjects as $subject)
        @php $submitted = (bool) ($subject->feedback_submitted ?? false); @endphp
        <div class="list-group-item d-flex justify-content-between align-items-center px-4 py-3">
            <div>
                <div class="fw-semibold">{{ $subject->name }}</div>
                <div class="text-muted small">{{ $subject->code ?? '' }}</div>
            </div>
            @if($submitted)
                <span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>Submitted</span>
            @elseif(!$canSubmitFeedback)
                <span class="badge bg-secondary">Active students only</span>
            @else
                <a href="{{ route('student.feedback.create', $subject) }}" class="btn btn-sm btn-outline-primary">Give Feedback</a>
            @endif
        </div>
        @endforeach
    </div>
    @endif
</div>
@endsection
