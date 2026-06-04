@extends('layouts.teacher')
@section('title','My Exams')
@section('page-title','Exam Results Entry')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('teacher.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Exams</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-pencil-square me-2 text-primary"></i>Exams — Subjects You Teach</span>
        <span class="text-muted small">{{ $exams->count() }} exam(s) found</span>
    </div>
    <div class="card-body p-0">
        @if($exams->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-journal-x display-4 d-block mb-3"></i>
                <h6>No exams found for your subjects.</h6>
                <p class="small">Exams will appear here once the admin creates them for your subjects.</p>
            </div>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Exam Name</th>
                        <th>Subject</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Max Marks</th>
                        <th>Semester</th>
                        <th>Results</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($exams as $exam)
                <tr>
                    <td class="text-muted">{{ $loop->iteration }}</td>
                    <td class="fw-semibold">{{ $exam->name }}</td>
                    <td>{{ $exam->subject->name ?? '—' }}</td>
                    <td>
                        <span class="badge bg-{{ match($exam->type) {
                            'internal' => 'info',
                            'external' => 'warning',
                            'practical' => 'success',
                            default => 'secondary'
                        } }} text-{{ in_array($exam->type,['internal','external','practical']) && $exam->type !== 'internal' ? 'dark' : 'white' }}">
                            {{ ucfirst($exam->type) }}
                        </span>
                    </td>
                    <td>{{ $exam->exam_date ? $exam->exam_date->format('d M Y') : '—' }}</td>
                    <td>
                        <span class="fw-semibold">{{ $exam->total_marks }}</span>
                        <span class="text-muted small">/ Pass: {{ $exam->passing_marks }}</span>
                    </td>
                    <td>{{ $exam->semester->name ?? '—' }}</td>
                    <td>
                        @php $hasResults = $exam->results->isNotEmpty(); @endphp
                        <div class="d-flex align-items-center gap-2">
                            <a href="{{ route('teacher.exams.results', $exam) }}" class="btn btn-sm btn-{{ $hasResults ? 'outline-primary' : 'primary' }}">
                                <i class="bi bi-pencil me-1"></i>{{ $hasResults ? 'Edit Results' : 'Enter Results' }}
                            </a>
                            @if($hasResults)
                                <span class="badge bg-success">
                                    <i class="bi bi-check-circle me-1"></i>{{ $exam->results->count() }} entered
                                </span>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
@endsection
