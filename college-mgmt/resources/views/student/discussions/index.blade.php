@extends('layouts.student')
@section('title', 'Q&A — ' . $subject->name)
@section('page-title', 'Discussion Board')

@section('content')
<div class="container-fluid py-3" style="max-width:960px">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <span class="fw-semibold">{{ $subject->name }}</span>
            <span class="text-muted small ms-2">{{ $subject->code ?? '' }}</span>
        </div>
        @if($canParticipate)
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newThreadModal">
            <i class="bi bi-plus-lg me-1"></i>Ask a Question
        </button>
        @else
            <span class="badge bg-secondary">Active students only</span>
        @endif
    </div>
    @unless($canParticipate)
        <div class="alert alert-secondary">
            <i class="bi bi-lock me-1"></i>Discussion posting is locked because your student profile is not active. Existing discussions remain visible for history.
        </div>
    @endunless

    @if($discussions->isEmpty())
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-chat-square-text fs-1 d-block mb-2"></i>
            <div class="fw-semibold text-dark mb-1">No discussion threads are open for this subject yet</div>
            <div class="small">
                Ask a question when you need clarification on class topics, assignments, assessments, or faculty instructions. Your classmates and faculty can reply in this subject thread.
            </div>
        </div>
    </div>
    @else
    <div class="card border-0 shadow-sm">
        <div class="list-group list-group-flush">
            @foreach($discussions as $thread)
            <a href="{{ route('student.discussions.show', [$subject, $thread]) }}" class="list-group-item list-group-item-action px-4 py-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="me-3">
                        <div class="fw-semibold mb-1">
                            @if($thread->is_pinned)<i class="bi bi-pin-fill text-warning me-1"></i>@endif
                            @if($thread->is_resolved)<i class="bi bi-check-circle-fill text-success me-1"></i>@endif
                            {{ $thread->title }}
                        </div>
                        <div class="text-muted small">
                            Asked by {{ $thread->author->name }} · {{ $thread->created_at->diffForHumans() }}
                        </div>
                    </div>
                    <div class="text-end flex-shrink-0">
                        <div class="small fw-semibold">{{ $thread->replies_count }} <span class="text-muted fw-normal">replies</span></div>
                        <div class="text-muted" style="font-size:.72rem">{{ $thread->views }} views</div>
                    </div>
                </div>
            </a>
            @endforeach
        </div>
        @if($discussions->hasPages())
        <div class="card-footer">{{ $discussions->links() }}</div>
        @endif
    </div>
    @endif
</div>

{{-- New Thread Modal --}}
@if($canParticipate)
<div class="modal fade" id="newThreadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('student.discussions.store', $subject) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-semibold">Ask a Question</h5>
                    <button aria-label="Close dialog" type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                        <input aria-label="Short, descriptive question title" type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                               value="{{ old('title') }}" placeholder="Short, descriptive question title" required>
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Details <span class="text-danger">*</span></label>
                               <textarea aria-label="Discussion question details" name="body" rows="5" class="form-control @error('body') is-invalid @enderror"
                                  placeholder="Provide details, what you've already tried, etc.">{{ old('body') }}</textarea>
                        @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Post Question</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if($canParticipate && $errors->any())
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    new bootstrap.Modal(document.getElementById('newThreadModal')).show();
});
</script>
@endpush
@endif
@endsection
