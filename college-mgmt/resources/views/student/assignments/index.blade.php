@extends('layouts.student')
@section('title', 'Assignments')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
            <h4 class="fw-semibold mb-1">Assignments</h4>
            <div class="small text-muted">Visible filter summary: {{ $filterSummary ?? 'All published assignments in your enrolled subjects' }}</div>
        </div>
        @if(request('filter') === 'pending_next_7')
            <span class="badge bg-warning text-dark">Filtered Source List ({{ $upcoming->count() }})</span>
        @endif
    </div>

    @if($upcoming->isNotEmpty())
    <h6 class="text-success mb-3"><i class="bi bi-clock me-1"></i>Upcoming / Open</h6>
    <div class="row g-3 mb-4">
        @foreach($upcoming as $assignment)
        @php $sub = $assignment->submissions->first(); @endphp
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div class="fw-semibold">{{ $assignment->title }}</div>
                        @if($sub && in_array($sub->status, ['submitted','graded']))
                            <span class="badge bg-success">{{ ucfirst($sub->status) }}</span>
                        @else
                            <span class="badge bg-warning text-dark">Pending</span>
                        @endif
                    </div>
                    <div class="text-muted small">{{ $assignment->subject->name }}</div>
                    <div class="mt-2 d-flex justify-content-between align-items-center">
                        <span class="text-danger small"><i class="bi bi-calendar-x me-1"></i>Due: {{ $assignment->due_at->format('d M Y, h:i A') }}</span>
                        <a href="{{ route('student.assignments.show', $assignment) }}" class="btn btn-sm btn-primary">View</a>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    @if($past->isNotEmpty())
    <h6 class="text-secondary mb-3"><i class="bi bi-archive me-1"></i>Past Assignments</h6>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th scope="col">Assignment</th>
                    <th scope="col">Subject</th>
                    <th scope="col">Due Date</th>
                    <th scope="col">Status</th>
                    <th scope="col">Marks</th>
                    <th aria-label="Actions" scope="col"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($past as $assignment)
                @php $sub = $assignment->submissions->first(); @endphp
                <tr>
                    <td class="fw-semibold">{{ $assignment->title }}</td>
                    <td>{{ $assignment->subject->name }}</td>
                    <td>{{ $assignment->due_at->format('d M Y') }}</td>
                    <td>
                        @if(!$sub || $sub->status === 'not_submitted')
                            <span class="badge bg-danger">Not Submitted</span>
                        @elseif($sub->status === 'submitted')
                            <span class="badge bg-info">Submitted</span>
                        @elseif($sub->status === 'graded')
                            <span class="badge bg-success">Graded</span>
                        @endif
                    </td>
                    <td>{{ $sub?->marks_obtained ?? '—' }} / {{ $assignment->max_marks }}</td>
                    <td><a href="{{ route('student.assignments.show', $assignment) }}" class="btn btn-sm btn-outline-secondary">View</a></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if($upcoming->isEmpty() && $past->isEmpty())
    <div class="alert alert-info">
        <div class="fw-semibold mb-1">No assignments are published for this view yet</div>
        <div class="small">
            Assignments appear here only after faculty publish them for your enrolled subjects.
            Check your course hub for materials and announcements, or clear dashboard filters if you opened a filtered assignment queue.
        </div>
    </div>
    @endif
</div>
@endsection
