@extends('layouts.student')
@section('title', 'Quiz: ' . $quiz->title)

@section('content')
<div class="container py-4" style="max-width:800px">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-semibold mb-0">{{ $quiz->title }}</h4>
        @if($quiz->duration_minutes)
        <div id="quiz-timer" class="badge bg-danger fs-6 px-3 py-2" data-seconds="{{ $quiz->duration_minutes * 60 }}">
            {{ $quiz->duration_minutes }}:00
        </div>
        @endif
    </div>

    <form method="POST" action="{{ route('student.quizzes.submit', $quiz) }}" id="quiz-form">
        @csrf
        @foreach($questions as $i => $question)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <p class="fw-semibold mb-3">{{ $i+1 }}. {{ $question->question_text }}
                    <span class="badge bg-secondary ms-2">{{ $question->marks }} mark{{ $question->marks != 1 ? 's' : '' }}</span>
                </p>

                @if($question->type === 'mcq')
                <div class="list-group">
                    @foreach($question->options as $option)
                    <label class="list-group-item list-group-item-action cursor-pointer">
                        <input aria-label="Select answer {{ $option->option_text }} for question {{ $i + 1 }}" type="radio" class="form-check-input me-2" name="answers[{{ $question->id }}]" value="{{ $option->id }}">
                        {{ $option->option_text }}
                    </label>
                    @endforeach
                </div>
                @elseif($question->type === 'true_false')
                <div class="list-group">
                    <label class="list-group-item list-group-item-action cursor-pointer">
                        <input aria-label="Select true for question {{ $i + 1 }}" type="radio" name="answers[{{ $question->id }}]" value="true" class="form-check-input me-2"> True
                    </label>
                    <label class="list-group-item list-group-item-action cursor-pointer">
                        <input aria-label="Select false for question {{ $i + 1 }}" type="radio" name="answers[{{ $question->id }}]" value="false" class="form-check-input me-2"> False
                    </label>
                </div>
                @endif
            </div>
        </div>
        @endforeach

        <div class="d-flex justify-content-between align-items-center mt-4">
            <span class="text-muted">{{ $questions->count() }} questions</span>
            <button type="submit" class="btn btn-success btn-lg" onclick="return confirm('Submit this quiz attempt now? Confirm every answer is final because the attempt is locked and cannot be changed after submission.')">
                <i class="bi bi-send me-2"></i>Submit Quiz
            </button>
        </div>
    </form>
</div>

@if($quiz->duration_minutes)
<script>
(function(){
    const el = document.getElementById('quiz-timer');
    let secs = parseInt(el.dataset.seconds);
    const interval = setInterval(function(){
        secs--;
        if(secs <= 0){
            clearInterval(interval);
            document.getElementById('quiz-form').submit();
            return;
        }
        const m = Math.floor(secs/60), s = secs%60;
        el.textContent = m + ':' + (s<10?'0':'') + s;
        if(secs < 60) el.classList.replace('bg-danger','bg-danger');
    }, 1000);
})();
</script>
@endif
@endsection
