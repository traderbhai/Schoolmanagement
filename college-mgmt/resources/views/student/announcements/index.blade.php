@extends('layouts.student')
@section('title', $subject->name . ' — Announcements')

@section('content')
<div class="container py-4">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('student.courses.index') }}">Courses</a></li>
            <li class="breadcrumb-item"><a href="{{ route('student.courses.show', $subject) }}">{{ $subject->name }}</a></li>
            <li class="breadcrumb-item active">Announcements</li>
        </ol>
    </nav>
    <h4 class="fw-semibold mb-4">Announcements — {{ $subject->name }}</h4>

    @if($announcements->isEmpty())
    <div class="alert alert-info">
        <div class="fw-semibold mb-1">No course announcements are published yet</div>
        <div class="small">
            Faculty or the program office will post notices here when there is a class update, deadline, room change, or academic instruction for this course.
        </div>
    </div>
    @else
    <div class="d-flex flex-column gap-3">
        @foreach($announcements as $ann)
        <div class="card border-0 shadow-sm {{ $ann->is_pinned ? 'border-start border-warning border-3' : '' }}">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-1">
                    <h6 class="fw-semibold mb-0">
                        @if($ann->is_pinned)<span class="badge bg-warning text-dark me-2">Pinned</span>@endif
                        {{ $ann->title }}
                    </h6>
                    <span class="text-muted small">{{ $ann->created_at->format('d M Y') }}</span>
                </div>
                <div class="text-muted small mb-2">Posted by {{ $ann->poster->name }}</div>
                <p class="mb-0">{{ $ann->body }}</p>
            </div>
        </div>
        @endforeach
    </div>
    {{ $announcements->links() }}
    @endif
</div>
@endsection
