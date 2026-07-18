@extends('layouts.admin')
@section('title', 'Grade Sheet — '.$exam->name)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-0">Grade Sheet: {{ $exam->name }}</h1>
        <small class="text-muted">{{ $exam->program?->name }} | {{ $exam->subject?->name }} | {{ $exam->exam_date->format('d M Y') }}</small>
    </div>
    <div class="d-flex gap-2">
        <form method="POST" action="{{ route('exam-cell.publish', $exam) }}">
            @csrf
            <button class="btn btn-sm btn-success" onclick="return confirm('Publish results for this exam? Confirm marks, absentees, pass/fail status, student visibility, and post-publication correction workflow before locking this grade sheet.')" @disabled($exam->published_at)>
                <i class="bi bi-check-circle me-1"></i>Publish Results
            </button>
        </form>
        <a href="{{ route('exam-cell.results') }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if($exam->published_at)
<div class="alert alert-info">
    <strong>Results published.</strong> Marks are locked for this exam. Use an approved correction workflow for post-publication changes.
</div>
@endif

<form method="POST" action="{{ route('exam-cell.save-marks', $exam) }}">
@csrf
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <span>Total Marks: <strong>{{ $exam->total_marks }}</strong> &nbsp;|&nbsp; Passing: <strong>{{ $exam->passing_marks }}</strong></span>
        <button type="submit" class="btn btn-sm btn-primary" @disabled($exam->published_at)><i class="bi bi-save me-1"></i>Save Marks</button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th scope="col">#</th><th scope="col">Student</th><th scope="col">Roll No.</th><th scope="col">Absent</th><th scope="col">Marks (/ {{ $exam->total_marks }})</th><th scope="col">Status</th></tr>
                </thead>
                <tbody>
                @forelse($students as $s)
                    @php $r = $s->result; @endphp
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $s->user?->name ?? '—' }}</td>
                        <td>{{ $s->roll_number ?? $s->enrollment_number ?? '—' }}</td>
                        <td>
                            <input aria-label="Absent" type="checkbox" name="absent[]" value="{{ $s->id }}" class="form-check-input"
                                {{ $r?->is_absent ? 'checked' : '' }}
                                @disabled($exam->published_at)>
                        </td>
                        <td style="width:130px;">
                            <input aria-label="Marks for {{ $s->user?->name ?? 'student' }}" type="number" name="marks[{{ $s->id }}]" class="form-control form-control-sm"
                                min="0" max="{{ $exam->total_marks }}" step="0.5"
                                value="{{ $r && !$r->is_absent ? $r->marks_obtained : '' }}"
                                placeholder="—"
                                @disabled($exam->published_at)>
                        </td>
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
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">No students enrolled.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</form>
@endsection
