@extends('layouts.admin')
@section('title', 'Program Chair — Exams')

@section('content')
<h4 class="mb-4"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Exams</h4>

<h5 class="mb-2 text-success">Upcoming Exams</h5>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th scope="col">Date</th><th scope="col">Exam</th><th scope="col">Program</th><th scope="col">Subject</th><th scope="col">Term</th><th scope="col">Marks</th></tr>
                </thead>
                <tbody>
                @forelse($upcoming as $exam)
                    <tr>
                        <td>{{ $exam->exam_date->format('d M Y') }}</td>
                        <td>{{ $exam->name }}</td>
                        <td>{{ $exam->program?->name ?? '—' }}</td>
                        <td>{{ $exam->subject?->name ?? '—' }}</td>
                        <td>{{ $exam->term?->name ?? '—' }}</td>
                        <td>{{ $exam->total_marks }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">No upcoming exams.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<h5 class="mb-2 text-secondary">Past Exams</h5>
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th scope="col">Date</th><th scope="col">Exam</th><th scope="col">Program</th><th scope="col">Subject</th><th scope="col">Marks</th></tr>
                </thead>
                <tbody>
                @forelse($past as $exam)
                    <tr>
                        <td>{{ $exam->exam_date->format('d M Y') }}</td>
                        <td>{{ $exam->name }}</td>
                        <td>{{ $exam->program?->name ?? '—' }}</td>
                        <td>{{ $exam->subject?->name ?? '—' }}</td>
                        <td>{{ $exam->total_marks }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted">No past exams.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
