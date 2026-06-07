@extends('layouts.student')
@section('title', Str::limit($discussion->title, 50))
@section('page-title', 'Discussion')

@section('content')
<div class="container py-4" style="max-width:860px">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('student.discussions.index', $subject) }}">{{ $subject->name }} Q&A</a></li>
            <li class="breadcrumb-item active">Thread</li>
        </ol>
    </nav>

    {{-- Original Question --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <h5 class="fw-semibold mb-0">
                    @if($discussion->is_resolved)
                        <span class="badge bg-success me-1 fs-6"><i class="bi bi-check-circle me-1"></i>Resolved</span>
                    @endif
                    {{ $discussion->title }}
                </h5>
                @if(!$discussion->is_resolved && $discussion->posted_by === auth()->id())
                <form method="POST" action="{{ route('student.discussions.resolve', [$subject, $discussion]) }}" class="ms-2">
                    @csrf
                    <button class="btn btn-sm btn-outline-success">Mark Resolved</button>
                </form>
                @endif
            </div>
            <div class="text-muted small mb-3">
                <i class="bi bi-person me-1"></i>{{ $discussion->author->name }}
                · {{ $discussion->created_at->format('d M Y, h:i A') }}
                · {{ $discussion->views }} views
            </div>
            <div class="border-start border-3 border-primary ps-3">
                {!! nl2br(e($discussion->body)) !!}
            </div>
        </div>
    </div>

    {{-- Replies --}}
    @if($discussion->replies->isNotEmpty())
    <h6 class="fw-semibold mb-2">{{ $discussion->replies->count() }} {{ Str::plural('Reply', $discussion->replies->count()) }}</h6>
    @foreach($discussion->replies as $reply)
    <div class="card border-0 shadow-sm mb-2 {{ $reply->is_accepted_answer ? 'border-start border-3 border-success' : '' }}">
        <div class="card-body py-3">
            @if($reply->is_accepted_answer)
            <div class="text-success small fw-semibold mb-1"><i class="bi bi-check-circle-fill me-1"></i>Accepted Answer</div>
            @endif
            <div class="d-flex justify-content-between mb-2">
                <span class="fw-semibold small">{{ $reply->author->name }}
                    @if(!($reply->author->student ?? null))<span class="badge bg-primary-subtle text-primary ms-1 fw-normal">Faculty</span>@endif
                </span>
                <span class="text-muted" style="font-size:.72rem">{{ $reply->created_at->format('d M Y, h:i A') }}</span>
            </div>
            <p class="mb-0 small">{!! nl2br(e($reply->body)) !!}</p>
        </div>
    </div>
    @endforeach
    @endif

    {{-- Reply form --}}
    @if(!$discussion->is_resolved)
    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header fw-semibold"><i class="bi bi-reply me-2"></i>Post a Reply</div>
        <div class="card-body">
            <form method="POST" action="{{ route('student.discussions.reply', [$subject, $discussion]) }}">
                @csrf
                <div class="mb-3">
                    <textarea name="body" rows="4" class="form-control @error('body') is-invalid @enderror"
                              placeholder="Share your answer or thoughts...">{{ old('body') }}</textarea>
                    @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-primary">Post Reply</button>
            </form>
        </div>
    </div>
    @endif
</div>
@endsection
