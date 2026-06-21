@extends('layouts.student')
@section('title', $subject->name . ' — Course Hub')

@section('content')
<div class="container py-4">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('student.courses.index') }}">Courses</a></li>
            <li class="breadcrumb-item active">{{ $subject->name }}</li>
        </ol>
    </nav>

    <h4 class="fw-semibold mb-1">{{ $subject->name }}</h4>
    <p class="text-muted mb-4">{{ $subject->code ?? '' }}</p>

    <div class="row g-4">
        {{-- Announcements --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-warning-subtle fw-semibold">
                    <i class="bi bi-megaphone me-2"></i>Announcements
                </div>
                <div class="card-body p-0">
                    @forelse($announcements as $ann)
                    <div class="px-3 py-2 border-bottom">
                        @if($ann->is_pinned)<span class="badge bg-danger me-1">Pinned</span>@endif
                        <div class="fw-semibold small">{{ $ann->title }}</div>
                        <div class="text-muted" style="font-size:.8rem">{{ $ann->poster->name }} · {{ $ann->created_at->diffForHumans() }}</div>
                    </div>
                    @empty
                    <div class="p-3 text-muted">
                        <div class="fw-semibold text-dark mb-1">No course announcements are posted yet</div>
                        <div class="small">Your faculty or program office will post class notices here after they publish them for this course.</div>
                    </div>
                    @endforelse
                </div>
                <div class="card-footer text-end">
                    <a href="{{ route('student.announcements.index', $subject) }}" class="btn btn-sm btn-link p-0">All announcements →</a>
                </div>
            </div>
        </div>

        {{-- Study Materials --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-info-subtle fw-semibold">
                    <i class="bi bi-folder2-open me-2"></i>Study Materials
                </div>
                <div class="card-body p-0">
                    @php
                        $typeLabels = ['pre_read'=>'Pre-Read','post_read'=>'Post-Read','notes'=>'Notes','slides'=>'Slides','reference'=>'Reference','video'=>'Video','other'=>'Other'];
                    @endphp
                    @forelse($materials as $type => $items)
                    <div class="px-3 py-2 border-bottom">
                        <div class="text-uppercase text-muted" style="font-size:.75rem">{{ $typeLabels[$type] ?? $type }}</div>
                        @foreach($items->take(2) as $mat)
                        <div class="d-flex align-items-center gap-1">
                            <i class="bi bi-file-earmark text-muted"></i>
                            @if($mat->file_path)
                                <span class="small">{{ $mat->title }}</span>
                            @elseif($mat->external_url)
                                <a href="{{ $mat->external_url }}" target="_blank" class="small">{{ $mat->title }}</a>
                            @else
                                <span class="small">{{ $mat->title }}</span>
                            @endif
                        </div>
                        @endforeach
                    </div>
                    @empty
                    <div class="p-3 text-muted">
                        <div class="fw-semibold text-dark mb-1">No study materials are available yet</div>
                        <div class="small">Published notes, slides, readings, and reference links will appear here after your faculty uploads them.</div>
                    </div>
                    @endforelse
                </div>
                <div class="card-footer text-end">
                    <a href="{{ route('student.materials.index', $subject) }}" class="btn btn-sm btn-link p-0">All materials →</a>
                </div>
            </div>
        </div>

        {{-- Assignments --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-success-subtle fw-semibold">
                    <i class="bi bi-pencil-square me-2"></i>Assignments
                </div>
                <div class="card-body p-0">
                    @forelse($assignments as $assignment)
                    @php $sub = $assignment->submissions->first(); @endphp
                    <div class="px-3 py-2 border-bottom">
                        <div class="d-flex justify-content-between align-items-start">
                            <a href="{{ route('student.assignments.show', $assignment) }}" class="fw-semibold small text-decoration-none">{{ $assignment->title }}</a>
                            @if($sub && $sub->status === 'submitted')
                                <span class="badge bg-success">Submitted</span>
                            @elseif($sub && $sub->status === 'graded')
                                <span class="badge bg-info">Graded</span>
                            @elseif($assignment->due_at->isPast())
                                <span class="badge bg-danger">Missed</span>
                            @else
                                <span class="badge bg-warning text-dark">Pending</span>
                            @endif
                        </div>
                        <div class="text-muted" style="font-size:.8rem">Due: {{ $assignment->due_at->format('d M Y') }}</div>
                    </div>
                    @empty
                    <div class="p-3 text-muted">
                        <div class="fw-semibold text-dark mb-1">No assignments are open for this course yet</div>
                        <div class="small">When your faculty publishes an assignment, it will appear here with its due date and submission status.</div>
                    </div>
                    @endforelse
                </div>
                <div class="card-footer text-end">
                    <a href="{{ route('student.assignments.index') }}" class="btn btn-sm btn-link p-0">All assignments →</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
