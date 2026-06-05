@extends('layouts.admin')
@section('title', 'Grade Sheet — '.$exam->name)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0">Grade Sheet: {{ $exam->name }}</h4>
        <small class="text-muted">{{ $exam->program?->name }} | {{ $exam->subject?->name }} | {{ $exam->exam_date->format('d M Y') }}</small>
    </div>
    <div class="d-flex gap-2">
        <form method="POST" action="{{ route('exam-cell.publish', $exam) }}">
            @csrf
            <button class="btn btn-sm btn-success" onclick="return confirm('Publish results for this exam?')">
                <i class="bi bi-check-circle me-1"></i>Publish Results
            </button>
        </form>
        <a href="{{ route('exam-cell.results') }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent">
        Total Marks: <strong>{{ $exam->total_marks }}</strong> &nbsp;|&nbsp; Passing: <strong>{{ $exam->passing_marks }}</strong>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>#</th><th>Student</th><th>Roll No.</th><th>Marks</th><th>Grade</th><th>Status</th><th>Remarks</th></tr>
                </thead>
                <tbody>
                @forelse($students as $s)
                    @php $r = $s->result; @endphp
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $s->user?->name ?? '—' }}</td>
                        <td>{{ $s->roll_number }}</td>
                        <td>{{ $r ? $r->marks_obtained : '—' }}</td>
                        <td>{{ $r?->grade ?? '—' }}</td>
                        <td>
                            @if(!$r)
                                <span class="badge bg-secondary">Not Entered</span>
                            @elseif($r->is_absent)
                                <span class="badge bg-warning text-dark">Absent</span>
                            @elseif($r->marks_obtained >= $exam->passing_marks)
                                <span class="badge bg-success">Pass</span>
                            @else
                                <span class="badge bg-danger">Fail</span>
                            @endif
                        </td>
                        <td>{{ $r?->remarks ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">No students enrolled.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
