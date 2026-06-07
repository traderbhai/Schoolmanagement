@extends('layouts.student')
@section('title', 'Course Feedback — ' . $subject->name)
@section('page-title', 'Course Feedback')

@section('content')
<div class="container py-4" style="max-width:580px">
    <div class="card border-0 shadow-sm">
        <div class="card-header fw-semibold">
            <i class="bi bi-star me-2"></i>{{ $subject->name }}
            <span class="badge bg-secondary fw-normal ms-1">{{ $subject->code ?? '' }}</span>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-4">Your feedback is anonymous and helps improve teaching quality.</p>

            <form method="POST" action="{{ route('student.feedback.store', $subject) }}">
                @csrf

                @php
                $ratings = [
                    'teaching_rating' => 'Teaching Quality',
                    'content_rating'  => 'Course Content',
                    'overall_rating'  => 'Overall Experience',
                ];
                @endphp

                @foreach($ratings as $field => $label)
                <div class="mb-4">
                    <label class="form-label fw-semibold">{{ $label }} <span class="text-danger">*</span></label>
                    <div class="d-flex gap-2">
                        @for($i=1;$i<=5;$i++)
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="{{ $field }}" id="{{ $field }}_{{ $i }}" value="{{ $i }}"
                                   {{ old($field)==$i?'checked':'' }} required>
                            <label class="form-check-label" for="{{ $field }}_{{ $i }}">{{ $i }}★</label>
                        </div>
                        @endfor
                    </div>
                    @error($field)<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                @endforeach

                <div class="mb-4">
                    <label class="form-label fw-semibold">Comments (optional)</label>
                    <textarea name="comments" rows="4" class="form-control"
                              placeholder="Any suggestions or observations...">{{ old('comments') }}</textarea>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Submit Feedback</button>
                    <a href="{{ route('student.feedback.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
