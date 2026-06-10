@extends('layouts.teacher')

@section('title', 'Submissions — ' . $assignment->title)

@section('content')
<div class="container-fluid px-4">

    {{-- Header --}}
    <div class="d-flex align-items-center mb-3">
        <a href="{{ route('teacher.assignments.index') }}" class="btn btn-sm btn-outline-secondary me-3">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h4 class="mb-0">{{ $assignment->title }}</h4>
            <div class="text-muted small">
                <span class="me-3"><i class="bi bi-book me-1"></i>{{ $assignment->subject->name ?? '—' }}</span>
                <span class="me-3">
                    <i class="bi bi-calendar-event me-1"></i>
                    Due: {{ $assignment->due_at ? \Carbon\Carbon::parse($assignment->due_at)->format('d M Y, H:i') : '—' }}
                </span>
                <span><i class="bi bi-trophy me-1"></i>Max Marks: {{ $assignment->max_marks }}</span>
            </div>
        </div>
    </div>

    {{-- Progress --}}
    @php
        $totalStudents   = $submissions->count() + $notSubmitted->count();
        $submittedCount  = $submissions->count();
        $pct             = $totalStudents > 0 ? round($submittedCount / $totalStudents * 100) : 0;
    @endphp
    <div class="card shadow-sm mb-3">
        <div class="card-body py-3">
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-primary fs-6 px-3 py-2">
                    {{ $submittedCount }} of {{ $totalStudents }} submitted
                </span>
                <div class="flex-grow-1">
                    <div class="progress" style="height:10px;">
                        <div class="progress-bar bg-success" style="width:{{ $pct }}%"></div>
                    </div>
                </div>
                <span class="text-muted small">{{ $pct }}%</span>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Submissions table --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold bg-white">
            <i class="bi bi-list-check me-1 text-primary"></i>Submitted
        </div>
        <div class="card-body p-0">
            @if($submissions->isEmpty())
                <div class="text-center py-4 text-muted">No submissions yet.</div>
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Student</th>
                                <th>Submitted At</th>
                                <th class="text-center">Late</th>
                                <th class="text-center">Status</th>
                                <th style="width:120px;">Marks</th>
                                <th style="width:220px;">Feedback</th>
                                <th class="text-center">Save</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($submissions as $submission)
                                @php
                                    $isLate   = $assignment->due_at && $submission->submitted_at
                                                && \Carbon\Carbon::parse($submission->submitted_at)->gt(\Carbon\Carbon::parse($assignment->due_at));
                                    $graded   = $submission->status === 'graded';
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $submission->student->user->name ?? '—' }}</div>
                                        <div class="text-muted small">{{ $submission->student->enrollment_number ?? '' }}</div>
                                    </td>
                                    <td class="small text-nowrap">
                                        {{ $submission->submitted_at
                                            ? \Carbon\Carbon::parse($submission->submitted_at)->format('d M Y, H:i')
                                            : '—' }}
                                    </td>
                                    <td class="text-center">
                                        @if($isLate)
                                            <span class="badge bg-warning text-dark">Late</span>
                                        @else
                                            <span class="text-muted small">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($graded)
                                            <span class="badge bg-success">Graded</span>
                                        @else
                                            <span class="badge bg-info text-dark">Submitted</span>
                                        @endif
                                    </td>
                                    <td>
                                        <form method="POST" action="{{ route('teacher.assignments.grade', $submission) }}">
                                            @csrf
                                            <input type="number" name="marks" value="{{ $submission->marks }}"
                                                   class="form-control form-control-sm"
                                                   min="0" max="{{ $assignment->max_marks }}"
                                                   placeholder="/ {{ $assignment->max_marks }}">
                                    </td>
                                    <td>
                                            <input type="text" name="feedback" value="{{ $submission->feedback }}"
                                                   class="form-control form-control-sm"
                                                   placeholder="Optional feedback">
                                    </td>
                                    <td class="text-center">
                                            <button type="submit" class="btn btn-sm btn-success">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Not yet submitted --}}
    @if($notSubmitted->isNotEmpty())
        <div class="card shadow-sm">
            <div class="card-header fw-semibold bg-white">
                <i class="bi bi-person-x me-1 text-danger"></i>Not Yet Submitted
                <span class="badge bg-danger ms-2">{{ $notSubmitted->count() }}</span>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    @foreach($notSubmitted as $student)
                        <li class="list-group-item d-flex align-items-center justify-content-between py-2">
                            <div>
                                <span class="fw-semibold">{{ $student->user->name ?? '—' }}</span>
                                <span class="text-muted small ms-2">{{ $student->enrollment_number ?? '' }}</span>
                            </div>
                            <span class="badge bg-secondary">Not Submitted</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

</div>
@endsection
