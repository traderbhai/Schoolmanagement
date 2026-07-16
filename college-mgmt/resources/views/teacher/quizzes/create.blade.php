@extends('layouts.teacher')

@section('title', 'Create Quiz')

@section('content')
<div class="container-fluid px-4">
    <div class="d-flex align-items-center mb-3">
        <a href="{{ route('teacher.quizzes.index') }}" class="btn btn-sm btn-outline-secondary me-3">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h4 class="mb-0"><i class="bi bi-patch-question me-2 text-primary"></i>Create Quiz</h4>
    </div>

    @if($currentTerm)
        <div class="alert alert-info py-2 mb-3">
            <i class="bi bi-info-circle me-1"></i>
            Current term: <strong>{{ $currentTerm->name }}</strong>
        </div>
    @endif

    @if($actionBlockedReason)
        <div class="alert alert-warning">
            <i class="bi bi-lock me-1"></i>{{ $actionBlockedReason }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="alert alert-light border d-flex align-items-start gap-2 py-2 mb-3">
        <i class="bi bi-info-circle text-primary mt-1"></i>
        <div class="small">
            <strong>Create quiz sequence:</strong>
            choose one published teaching subject, set an active window, enter MCQ questions with one correct option each, then publish when students should attempt it.
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('teacher.quizzes.store') }}">
                @csrf
                <fieldset @disabled($actionBlockedReason)>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
                            <select name="subject_id" class="form-select" required>
                                <option value="">Select published teaching subject</option>
                                @foreach($subjects as $subject)
                                    <option value="{{ $subject->id }}" @selected(old('subject_id') == $subject->id)>
                                        {{ $subject->name }} ({{ $subject->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Duration</label>
                            <div class="input-group">
                                <input type="number" name="duration_minutes" value="{{ old('duration_minutes', 20) }}" class="form-control" min="1" max="300">
                                <span class="input-group-text">min</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Pass Marks</label>
                            <input type="number" name="pass_marks" value="{{ old('pass_marks') }}" class="form-control" step="0.5" min="0" placeholder="Optional">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" value="{{ old('title') }}" class="form-control" required placeholder="e.g. Unit 1 MCQ Quiz">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" rows="3" class="form-control" placeholder="Short instructions visible before students start.">{{ old('description') }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Starts At <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="starts_at" value="{{ old('starts_at', now()->subMinutes(5)->format('Y-m-d\TH:i')) }}" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Ends At <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="ends_at" value="{{ old('ends_at', now()->addDays(7)->format('Y-m-d\TH:i')) }}" class="form-control" required>
                        </div>
                    </div>

                    <hr>

                    <h6 class="fw-semibold mb-3">Questions</h6>
                    @for($i = 0; $i < 3; $i++)
                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="fw-semibold">Question {{ $i + 1 }} @if($i === 0)<span class="text-danger">*</span>@else<span class="text-muted small">(optional)</span>@endif</div>
                                <div style="width:120px">
                                    <input type="number" name="questions[{{ $i }}][marks]" value="{{ old("questions.$i.marks", $i === 0 ? 1 : '') }}" class="form-control form-control-sm" step="0.5" min="0.5" placeholder="Marks" @if($i === 0) required @endif>
                                </div>
                            </div>
                            <textarea name="questions[{{ $i }}][question_text]" rows="2" class="form-control mb-2" placeholder="Question text" @if($i === 0) required @endif>{{ old("questions.$i.question_text") }}</textarea>
                            <div class="row g-2">
                                @for($j = 0; $j < 4; $j++)
                                    <div class="col-md-6">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">
                                                <input class="form-check-input mt-0" type="radio" name="questions[{{ $i }}][correct_option]" value="{{ $j }}" @checked((string) old("questions.$i.correct_option", $j === 0 ? '0' : '') === (string) $j) @if($i === 0) required @endif>
                                            </span>
                                            <input type="text" name="questions[{{ $i }}][options][{{ $j }}]" value="{{ old("questions.$i.options.$j") }}" class="form-control" placeholder="Option {{ $j + 1 }}" @if($i === 0 && $j < 2) required @endif>
                                        </div>
                                    </div>
                                @endfor
                            </div>
                            <div class="form-text">Select the radio button beside the correct option.</div>
                        </div>
                    @endfor

                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_published" value="1" id="is_published" @checked(old('is_published', '1'))>
                                <label class="form-check-label fw-semibold" for="is_published">Publish immediately</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="show_result_immediately" value="1" id="show_result_immediately" @checked(old('show_result_immediately', '1'))>
                                <label class="form-check-label fw-semibold" for="show_result_immediately">Show result immediately</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="shuffle_questions" value="1" id="shuffle_questions" @checked(old('shuffle_questions'))>
                                <label class="form-check-label fw-semibold" for="shuffle_questions">Shuffle questions</label>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <hr class="mt-4">

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary" @disabled($actionBlockedReason)>
                        <i class="bi bi-patch-check me-1"></i>Create Quiz
                    </button>
                    <a href="{{ route('teacher.quizzes.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
