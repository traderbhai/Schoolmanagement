@extends('layouts.teacher')

@section('title', 'Quizzes')

@section('content')
<div class="container-fluid px-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0"><i class="bi bi-patch-question me-2 text-primary"></i>Quizzes</h4>
        @if($canManageQuizzes)
            <a href="{{ route('teacher.quizzes.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle me-1"></i>Create Quiz
            </a>
        @else
            <span class="badge bg-secondary">Active teachers only</span>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="alert alert-light border d-flex align-items-start gap-2 py-2 mb-3">
        <i class="bi bi-info-circle text-primary mt-1"></i>
        <div class="small">
            <strong>Quiz workflow:</strong>
            create MCQ quizzes only for your published teaching subjects. Published active quizzes become visible to enrolled students in their quiz list and course hub.
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('teacher.quizzes.index') }}" id="quiz-filter-form">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Subject</label>
                        <select name="subject_id" class="form-select form-select-sm" onchange="document.getElementById('quiz-filter-form').submit()">
                            <option value="">All Subjects</option>
                            @foreach($subjects as $subject)
                                <option value="{{ $subject->id }}" @selected(request('subject_id') == $subject->id)>
                                    {{ $subject->name }} ({{ $subject->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        @if(request()->filled('subject_id'))
                            <a href="{{ route('teacher.quizzes.index') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-x-circle"></i> Clear
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            @if($quizzes->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-patch-question fs-1 d-block mb-2"></i>
                    <div class="fw-semibold text-dark mb-1">No quizzes are published or drafted for this view yet</div>
                    <div class="small mb-2">Create an MCQ quiz for your assigned subject, or clear filters if you expected to see existing quizzes.</div>
                    @if($canManageQuizzes)
                        <a href="{{ route('teacher.quizzes.create') }}" class="btn btn-sm btn-outline-primary">Create quiz</a>
                    @endif
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Quiz</th>
                                <th>Subject</th>
                                <th>Window</th>
                                <th class="text-center">Marks</th>
                                <th class="text-center">Attempts</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($quizzes as $quiz)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $quiz->title }}</div>
                                        @if($quiz->description)
                                            <div class="text-muted small">{{ Str::limit($quiz->description, 70) }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="text-muted small">{{ $quiz->subject->code ?? 'Subject code not linked' }}</span><br>
                                        {{ $quiz->subject->name ?? 'Subject not linked' }}
                                    </td>
                                    <td class="small text-nowrap">
                                        <div>{{ $quiz->starts_at?->format('d M Y, H:i') ?? 'No start' }}</div>
                                        <div class="text-muted">{{ $quiz->ends_at?->format('d M Y, H:i') ?? 'No end' }}</div>
                                    </td>
                                    <td class="text-center">{{ $quiz->total_marks }}</td>
                                    <td class="text-center">{{ $quiz->attempts->where('is_completed', true)->count() }}</td>
                                    <td class="text-center">
                                        @if(!$quiz->is_published)
                                            <span class="badge bg-secondary">Draft</span>
                                        @elseif($quiz->isActive())
                                            <span class="badge bg-success">Active</span>
                                        @elseif($quiz->starts_at && now()->lt($quiz->starts_at))
                                            <span class="badge bg-info text-dark">Scheduled</span>
                                        @else
                                            <span class="badge bg-dark">Closed</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($quizzes->hasPages())
                    <div class="card-footer d-flex justify-content-end">
                        {{ $quizzes->withQueryString()->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
@endsection
