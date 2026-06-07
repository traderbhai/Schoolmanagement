@extends('layouts.student')
@section('title', 'Quizzes')

@section('content')
<div class="container py-4">
    <h4 class="fw-semibold mb-4">Quizzes</h4>

    @if($quizzes->isEmpty())
    <div class="alert alert-info">No quizzes available yet.</div>
    @else
    <div class="row g-3">
        @foreach($quizzes as $quiz)
        @php $attempt = $attemptMap[$quiz->id] ?? null; @endphp
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="fw-semibold">{{ $quiz->title }}</h6>
                    <p class="text-muted small mb-2">{{ $quiz->subject->name }}</p>
                    <div class="d-flex gap-2 flex-wrap mb-3">
                        @if($quiz->duration_minutes)
                        <span class="badge bg-secondary-subtle text-secondary"><i class="bi bi-clock me-1"></i>{{ $quiz->duration_minutes }} min</span>
                        @endif
                        <span class="badge bg-secondary-subtle text-secondary"><i class="bi bi-award me-1"></i>{{ $quiz->total_marks }} marks</span>
                    </div>

                    @if($attempt && $attempt->is_completed)
                        <div class="mb-2 text-success small"><i class="bi bi-check-circle me-1"></i>Completed · Score: {{ $attempt->score }}/{{ $quiz->total_marks }}</div>
                        @if($quiz->show_result_immediately)
                        <a href="{{ route('student.quizzes.result', $quiz) }}" class="btn btn-sm btn-outline-success">View Result</a>
                        @endif
                    @elseif($quiz->isActive())
                        <a href="{{ route('student.quizzes.show', $quiz) }}" class="btn btn-sm btn-primary">{{ $attempt ? 'Continue' : 'Start Quiz' }}</a>
                    @elseif($quiz->starts_at && now()->lt($quiz->starts_at))
                        <span class="text-muted small">Starts {{ $quiz->starts_at->format('d M, h:i A') }}</span>
                    @else
                        <span class="badge bg-secondary">Closed</span>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>
@endsection
