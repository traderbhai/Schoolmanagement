@extends('layouts.teacher')

@section('title', 'Course Feedback')

@section('content')
<div class="container-fluid px-4">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">
            <i class="bi bi-star-half me-2 text-warning"></i>Course Feedback
            @if($currentTerm)
                <span class="text-muted fs-5 fw-normal ms-2">— {{ $currentTerm->name }}</span>
            @endif
        </h4>
    </div>

    @if(!empty($profileMissing))
        <div class="alert alert-warning">
            <strong>Teacher profile not linked.</strong>
            Feedback summaries cannot be loaded until administration links your login to a teacher profile.
        </div>
    @endif

    <div class="alert alert-info mb-4">
        <i class="bi bi-shield-lock me-2"></i>
        <strong>Ratings are aggregated and anonymous.</strong>
        Individual student responses are not shown to protect respondent privacy.
    </div>

    @if($feedbackBySubject->isEmpty())
        <div class="card shadow-sm">
            <div class="card-body text-center py-5 text-muted">
                <i class="bi bi-chat-square-dots fs-1 d-block mb-2 opacity-25"></i>
                <p class="mb-0">No subjects found for the current term.</p>
            </div>
        </div>
    @else
        <div class="row g-4">
            @foreach($feedbackBySubject as $subject)
                @php $stats = $subject->feedback_stats ?? null; @endphp
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header bg-white d-flex align-items-center justify-content-between">
                            <div>
                                <div class="fw-bold">{{ $subject->name }}</div>
                                <div class="text-muted small">{{ $subject->code }}</div>
                            </div>
                            @if($stats)
                                <span class="badge bg-primary">{{ $stats->response_count ?? 0 }} responses</span>
                            @endif
                        </div>

                        <div class="card-body">
                            @if(!$stats)
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-hourglass-split fs-2 d-block mb-2 opacity-25"></i>
                                    <small>No feedback collected yet for this subject.</small>
                                </div>
                            @else
                                @php
                                    $dimensions = [
                                        'teaching' => ['label' => 'Teaching Quality',  'score' => $stats->avg_teaching  ?? null],
                                        'content'  => ['label' => 'Content Quality',   'score' => $stats->avg_content   ?? null],
                                        'overall'  => ['label' => 'Overall Rating',    'score' => $stats->avg_overall   ?? null],
                                    ];
                                @endphp

                                @foreach($dimensions as $key => $dim)
                                    @php
                                        $score = $dim['score'];
                                        if ($score === null) continue;
                                        $scoreFloat = (float) $score;
                                        $starClass  = $scoreFloat >= 4 ? 'text-success' : ($scoreFloat >= 3 ? 'text-warning' : 'text-danger');
                                        $filledStars  = min(5, max(0, round($scoreFloat)));
                                        $emptyStars   = 5 - $filledStars;
                                    @endphp
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="small fw-semibold">{{ $dim['label'] }}</span>
                                            <span class="fw-bold {{ $starClass }}">
                                                {{ number_format($scoreFloat, 1) }}<span class="text-muted fw-normal small"> / 5.0</span>
                                            </span>
                                        </div>
                                        <div class="{{ $starClass }}" style="font-size:1.1rem;letter-spacing:1px;">
                                            @for($i = 0; $i < $filledStars; $i++)
                                                &#9733;
                                            @endfor
                                            @for($i = 0; $i < $emptyStars; $i++)
                                                <span class="text-muted opacity-25">&#9733;</span>
                                            @endfor
                                        </div>
                                        <div class="progress mt-1" style="height:5px;">
                                            @php $barWidth = round($scoreFloat / 5 * 100); @endphp
                                            <div class="progress-bar
                                                {{ $scoreFloat >= 4 ? 'bg-success' : ($scoreFloat >= 3 ? 'bg-warning' : 'bg-danger') }}"
                                                 style="width:{{ $barWidth }}%"></div>
                                        </div>
                                    </div>
                                @endforeach

                                {{-- Anonymous comments --}}
                                @if(!empty($stats->comments) && count($stats->comments) > 0)
                                    <hr class="my-2">
                                    <div class="small fw-semibold text-muted mb-2">
                                        <i class="bi bi-chat-left-quote me-1"></i>Student Comments
                                    </div>
                                    @foreach(array_slice($stats->comments, 0, 5) as $comment)
                                        @if(trim($comment))
                                            <div class="bg-light rounded px-3 py-2 mb-2 small text-muted fst-italic">
                                                "{{ $comment }}"
                                            </div>
                                        @endif
                                    @endforeach
                                @endif

                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</div>
@endsection
