@extends('layouts.student')
@section('title', $quiz->title)

@section('content')
<div class="container py-4" style="max-width:700px">
    <h4 class="fw-semibold mb-1">{{ $quiz->title }}</h4>
    <p class="text-muted mb-3">{{ $quiz->subject->name }}</p>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            @if($quiz->description)<p>{{ $quiz->description }}</p>@endif
            <ul class="list-unstyled mb-0">
                <li><i class="bi bi-question-circle me-2"></i>{{ $quiz->questions->count() }} Questions</li>
                @if($quiz->duration_minutes)<li><i class="bi bi-clock me-2"></i>{{ $quiz->duration_minutes }} minutes</li>@endif
                <li><i class="bi bi-award me-2"></i>Total: {{ $quiz->total_marks }} marks</li>
                @if($quiz->pass_marks)<li><i class="bi bi-check-circle me-2"></i>Pass: {{ $quiz->pass_marks }}</li>@endif
            </ul>
        </div>
    </div>

    @if(!$quiz->isActive())
    <div class="alert alert-warning">This quiz is not currently active.</div>
    @else
    <form method="POST" action="{{ route('student.quizzes.start', $quiz) }}">
        @csrf
        <button type="submit" class="btn btn-primary btn-lg w-100">
            <i class="bi bi-play-fill me-2"></i>Start Quiz
        </button>
    </form>
    @endif
</div>
@endsection
