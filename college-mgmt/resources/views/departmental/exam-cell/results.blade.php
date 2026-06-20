@extends('layouts.admin')
@section('title', 'Exam Cell — Results')

@section('content')
<h1 class="h4 mb-4"><i class="bi bi-clipboard-data me-2 text-primary"></i>Result Entry Status</h1>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Exam</th><th>Program</th><th>Date</th><th>Enrolled</th><th>Entered</th><th>Completion</th><th>Actions</th></tr>
                </thead>
                <tbody>
                @forelse($exams as $exam)
                    <tr>
                        <td>{{ $exam->name }}</td>
                        <td>{{ $exam->program?->name ?? '—' }}</td>
                        <td>{{ $exam->exam_date->format('d M Y') }}</td>
                        <td>{{ $exam->enrolled }}</td>
                        <td>{{ $exam->entered }}</td>
                        <td>
                            <div class="progress" style="height:14px;min-width:80px">
                                <div class="progress-bar bg-{{ $exam->completion_pct == 100 ? 'success' : 'warning' }}"
                                     style="width:{{ $exam->completion_pct }}%">{{ $exam->completion_pct }}%</div>
                            </div>
                        </td>
                        <td><a href="{{ route('exam-cell.grade-sheet', $exam) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">No exams found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
