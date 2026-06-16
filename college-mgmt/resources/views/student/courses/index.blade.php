@extends('layouts.student')
@section('title', 'My Courses')

@section('content')
<div class="container py-4">
    <h4 class="mb-4 fw-semibold">My Courses</h4>

    @if($subjects->isEmpty())
        <div class="alert alert-info">You are not enrolled in any courses for this term.</div>
    @else
    <div class="row g-3">
        @foreach($subjects as $subject)
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="fw-semibold mb-0">{{ $subject->name }}</h6>
                        <span class="badge bg-primary-subtle text-primary">{{ $subject->code ?? '' }}</span>
                    </div>
                    <p class="text-muted small mb-3">
                        {{ !empty($subject->faculty_names) ? implode(', ', $subject->faculty_names) : 'Faculty not assigned' }}
                    </p>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="{{ route('student.courses.show', $subject) }}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-book me-1"></i>Course Hub
                        </a>
                        <a href="{{ route('student.materials.index', $subject) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-folder me-1"></i>Materials
                        </a>
                        <a href="{{ route('student.announcements.index', $subject) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-megaphone me-1"></i>Notices
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>
@endsection
