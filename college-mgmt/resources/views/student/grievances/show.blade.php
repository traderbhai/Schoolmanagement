@extends('layouts.student')
@section('title', 'Grievance - ' . \Illuminate\Support\Str::limit($grievance->title, 40))
@section('content')
<div class="container py-4" style="max-width:800px">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('student.grievances.index') }}">Grievances</a></li>
            <li class="breadcrumb-item active">Detail</li>
        </ol>
    </nav>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
                <h5 class="fw-semibold mb-0">{{ $grievance->title }}</h5>
                {!! $grievance->status_badge !!}
            </div>
            <div class="row g-2 mb-3">
                <div class="col-auto">{!! $grievance->priority_badge !!}</div>
                <div class="col-auto"><span class="badge bg-secondary">{{ ucfirst($grievance->category) }}</span></div>
                <div class="col-auto text-muted small">Submitted {{ $grievance->created_at->format('d M Y H:i') }}</div>
            </div>
            <p class="mb-0">{!! nl2br(e($grievance->description)) !!}</p>

            @if(in_array($grievance->status, ['resolved', 'closed']) && $grievance->resolution_notes)
            <div class="alert alert-success mt-3 mb-0">
                <strong><i class="bi bi-check-circle me-1"></i>Resolution:</strong> {!! nl2br(e($grievance->resolution_notes)) !!}
                @if($grievance->resolved_at)<div class="text-muted small mt-1">{{ $grievance->resolved_at->format('d M Y') }}</div>@endif
            </div>
            @endif
        </div>
        @if($grievance->status === 'resolved')
        <div class="card-footer text-end">
            <form method="POST" action="{{ route('student.grievances.close', $grievance) }}" class="d-inline" onsubmit="return confirm('Close this grievance?')">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-check2-circle me-1"></i>Close Grievance
                </button>
            </form>
        </div>
        @endif
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header fw-semibold"><i class="bi bi-chat-dots me-2"></i>Updates & Comments</div>
        <div class="card-body p-0">
            @forelse($grievance->comments as $comment)
            @php $isMe = $comment->user_id === auth()->id(); @endphp
            <div class="px-3 py-2 border-bottom {{ $isMe ? '' : 'bg-light' }}">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fw-semibold small">
                        {{ $isMe ? 'You' : $comment->user->name }}
                        @if(!$isMe)<span class="badge bg-primary-subtle text-primary ms-1 fw-normal">Staff</span>@endif
                    </span>
                    <span class="text-muted" style="font-size:.75rem">{{ $comment->created_at->format('d M Y, h:i A') }}</span>
                </div>
                <p class="mb-0 small">{!! nl2br(e($comment->comment)) !!}</p>
            </div>
            @empty
            <p class="text-muted p-3 mb-0 small">No comments yet.</p>
            @endforelse
        </div>
        @if($grievance->isOpen())
        <div class="card-footer">
            <form method="POST" action="{{ route('student.grievances.comment', $grievance) }}">
                @csrf
                <div class="d-flex gap-2">
                    <textarea name="comment" rows="2" class="form-control form-control-sm @error('comment') is-invalid @enderror" placeholder="Add a follow-up or additional information">{{ old('comment') }}</textarea>
                    <button type="submit" class="btn btn-sm btn-primary align-self-end">Send</button>
                </div>
                @error('comment')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </form>
        </div>
        @endif
    </div>
</div>
@endsection
