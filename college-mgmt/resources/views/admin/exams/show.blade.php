@extends('layouts.admin')
@section('title','Exam Details')
@section('page-title','Exam Details')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.exams.index') }}">Exams</a></li>
    <li class="breadcrumb-item active">View Exam</li>
@endsection

@section('content')
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
<div class="row g-3">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h5 class="fw-bold">{{ $exam->name }}</h5>
                <span class="badge {{ match($exam->type){'internal'=>'bg-info text-dark','midterm'=>'bg-warning text-dark','final'=>'bg-danger','practical'=>'bg-success','assignment'=>'bg-secondary',default=>'bg-secondary'} }} mb-2">{{ ucfirst($exam->type) }}</span>
                <table class="table table-sm table-borderless mb-0 small">
                    <tr><td class="text-muted">Subject</td><td class="fw-semibold">{{ $exam->subject->name }}</td></tr>
                    <tr><td class="text-muted">Semester</td><td>{{ $exam->semester->name }}</td></tr>
                    <tr><td class="text-muted">Date</td><td>{{ $exam->exam_date->format('d M Y') }}</td></tr>
                    <tr><td class="text-muted">Time</td><td>{{ $exam->start_time ?? '–' }} @if($exam->end_time)– {{ $exam->end_time }}@endif</td></tr>
                    <tr><td class="text-muted">Total Marks</td><td>{{ $exam->total_marks }}</td></tr>
                    <tr><td class="text-muted">Pass Marks</td><td>{{ $exam->passing_marks }}</td></tr>
                    <tr><td class="text-muted">Classroom</td><td>{{ optional($exam->classroom)->room_number ?? '–' }}</td></tr>
                    <tr><td class="text-muted">Results</td><td>{{ $exam->results->count() }} entered</td></tr>
                    <tr><td class="text-muted">Publication</td><td>{{ $exam->published_at ? 'Published '.$exam->published_at->format('d M Y H:i') : 'Draft' }}</td></tr>
                </table>
                <div class="d-flex gap-2 mt-3">
                    <a href="{{ route('admin.exams.results', $exam) }}" class="btn btn-sm btn-success">Enter Results</a>
                    @if($exam->published_at)
                        <span class="btn btn-sm btn-outline-secondary disabled"><i class="bi bi-lock me-1"></i>Locked</span>
                    @else
                        <a href="{{ route('admin.exams.edit', $exam) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">Results Summary</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th scope="col">Student</th><th scope="col">Enrollment</th><th scope="col">Marks</th><th scope="col">Grade</th><th scope="col">Result</th></tr></thead>
                    <tbody>
                    @forelse($exam->results as $r)
                    <tr>
                        <td>{{ $r->student->user->name }}</td>
                        <td><code>{{ $r->student->enrollment_number }}</code></td>
                        <td>{{ $r->is_absent ? '–' : $r->marks_obtained }}</td>
                        <td>{{ $r->grade ?? '–' }}</td>
                        <td>
                            @if($r->is_absent)<span class="badge bg-secondary">Absent</span>
                            @elseif($r->passed)<span class="badge bg-success">Pass</span>
                            @else<span class="badge bg-danger">Fail</span>@endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">No results entered yet. <a href="{{ route('admin.exams.results', $exam) }}">Enter now.</a></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
