@extends('layouts.teacher')

@section('title', 'Assignments')

@section('content')
<div class="container-fluid px-4">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0"><i class="bi bi-journal-check me-2 text-primary"></i>Assignments</h4>
        @if($canManageAssignments)
            <a href="{{ route('teacher.assignments.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle me-1"></i> Create Assignment
            </a>
        @else
            <span class="badge bg-secondary">Active teachers only</span>
        @endif
    </div>

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Subject filter --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('teacher.assignments.index') }}" id="filter-form">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Filter by Subject</label>
                        <select name="subject_id" class="form-select form-select-sm"
                                onchange="document.getElementById('filter-form').submit()">
                            <option value="">All Subjects</option>
                            @foreach($subjects as $subject)
                                <option value="{{ $subject->id }}" {{ request('subject_id') == $subject->id ? 'selected' : '' }}>
                                    {{ $subject->name }} ({{ $subject->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        @if(request()->filled('subject_id'))
                            <a href="{{ route('teacher.assignments.index') }}" class="btn btn-outline-secondary btn-sm">
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
            @if($assignments->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-journal-x fs-1 d-block mb-2"></i>
                    <div class="fw-semibold text-dark mb-1">No assignments are published or drafted for this view yet</div>
                    <div class="small mb-2">Create the first assignment for your assigned subject, or clear the subject filter if you expected to see existing work.</div>
                    @if($canManageAssignments)
                        <a href="{{ route('teacher.assignments.create') }}" class="btn btn-sm btn-outline-primary">Create assignment</a>
                    @endif
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Title</th>
                                <th>Subject</th>
                                <th>Due Date</th>
                                <th class="text-center">Max Marks</th>
                                <th class="text-center">Submissions</th>
                                <th class="text-center">Published</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($assignments as $assignment)
                                @php $isPast = $assignment->due_at && \Carbon\Carbon::parse($assignment->due_at)->isPast(); @endphp
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $assignment->title }}</div>
                                        @if($assignment->description)
                                            <div class="text-muted small">{{ Str::limit($assignment->description, 60) }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="text-muted small">{{ $assignment->subject->code ?? '' }}</span><br>
                                        {{ $assignment->subject->name ?? '—' }}
                                    </td>
                                    <td>
                                        @if($assignment->due_at)
                                            <span class="{{ $isPast ? 'text-danger fw-semibold' : '' }}">
                                                {{ \Carbon\Carbon::parse($assignment->due_at)->format('d M Y, H:i') }}
                                            </span>
                                            @if($isPast)
                                                <span class="badge bg-danger ms-1 small">Past Due</span>
                                            @endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $assignment->max_marks }}</td>
                                    <td class="text-center">
                                        <a href="{{ route('teacher.assignments.submissions', $assignment) }}"
                                           class="badge bg-info text-dark text-decoration-none">
                                            {{ $assignment->submissions_count ?? 0 }} submitted
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        @if($assignment->is_published)
                                            <span class="badge bg-success">Published</span>
                                        @else
                                            <span class="badge bg-secondary">Draft</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('teacher.assignments.submissions', $assignment) }}"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-people me-1"></i>View Submissions
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($assignments->hasPages())
                    <div class="card-footer d-flex justify-content-end">
                        {{ $assignments->withQueryString()->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>

</div>
@endsection
