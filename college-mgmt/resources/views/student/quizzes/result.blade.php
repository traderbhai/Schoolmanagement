@extends('layouts.student')
@section('title', 'Quiz Result — ' . $quiz->title)

@section('content')
<div class="container py-4" style="max-width:800px">
    <h4 class="fw-semibold mb-1">{{ $quiz->title }}</h4>
    <p class="text-muted mb-4">{{ $quiz->subject->name }}</p>

    <div class="card border-0 shadow-sm mb-4 text-center">
        <div class="card-body py-4">
            <div class="display-4 fw-bold {{ $attempt->score >= ($quiz->pass_marks ?? 0) ? 'text-success' : 'text-danger' }}">
                {{ $attempt->score }} / {{ $quiz->total_marks }}
            </div>
            <div class="text-muted mt-1">
                @if($quiz->pass_marks)
                    @if($attempt->score >= $quiz->pass_marks)
                        <span class="badge bg-success fs-6">Passed</span>
                    @else
                        <span class="badge bg-danger fs-6">Failed</span>
                    @endif
                @endif
            </div>
            <div class="text-muted small mt-2">Submitted: {{ $attempt->submitted_at?->format('d M Y, h:i A') }}</div>
        </div>
    </div>

    @if($quiz->show_result_immediately)
    <h6 class="fw-semibold mb-3">Answer Review</h6>
    @foreach($quiz->questions as $i => $question)
    @php
        $answer = $attempt->answers->firstWhere('quiz_question_id', $question->id);
        $isCorrect = $answer?->is_correct;
    @endphp
    <div class="card border-0 shadow-sm mb-3 {{ $isCorrect ? 'border-start border-success border-3' : 'border-start border-danger border-3' }}">
        <div class="card-body">
            <p class="fw-semibold mb-2">{{ $i+1 }}. {{ $question->question_text }}</p>
            @foreach($question->options as $option)
            <div class="d-flex align-items-center gap-2 mb-1">
                @if($option->is_correct)
                    <i class="bi bi-check-circle-fill text-success"></i>
                @elseif($answer?->quiz_option_id === $option->id)
                    <i class="bi bi-x-circle-fill text-danger"></i>
                @else
                    <i class="bi bi-circle text-muted"></i>
                @endif
                <span class="{{ $option->is_correct ? 'text-success fw-semibold' : ($answer?->quiz_option_id === $option->id ? 'text-danger' : '') }}">
                    {{ $option->option_text }}
                </span>
            </div>
            @endforeach
        </div>
    </div>
    @endforeach
    @endif

    <a href="{{ route('student.quizzes.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Quizzes
    </a>
</div>
@endsection
