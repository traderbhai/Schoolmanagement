@extends('layouts.teacher')
@section('title','My Exams')
@section('page-title','Exam Results Entry')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('teacher.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Exams</li>
@endsection

@section('content')

<div class="alert alert-info border-0 shadow-sm">
    <div class="fw-semibold mb-1"><i class="bi bi-pencil-square me-1"></i>Marks entry sequence</div>
    <div class="small mb-0">
        Exams appear here only for subjects linked to your published teaching timetable.
        Open an exam, review the roster, enter marks or mark absentees, and save before Exam Cell publishes the official result.
    </div>
</div>

<div class="card" style="box-shadow:var(--shadow-sm)">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold">
            <i class="bi bi-pencil-square me-2 text-primary"></i>Exams - Subjects You Teach
        </span>
        <span class="badge bg-secondary">{{ $exams->count() }} exam(s)</span>
    </div>
    <div class="card-body p-0">
        @if($exams->isEmpty())
            <div class="empty-state py-5">
                <div class="empty-icon"><i class="bi bi-journal-x" style="font-size:3rem;color:var(--clr-text-muted)"></i></div>
                <h6 class="mt-3 text-muted">No published exam-entry work is available yet</h6>
                <p class="text-muted small mb-0">
                    Exams appear after CoE/Admin creates exams for subjects you teach through a published timetable.
                    If an expected exam is missing, verify teacher assignment, timetable publication, and exam setup with the academic office or Exam Cell.
                </p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
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
                        @php
                            $hasResults = $exam->results->isNotEmpty();
                            $typeBadge = match($exam->type) {
                                'internal' => 'bg-info text-dark',
                                'external' => 'bg-warning text-dark',
                                'practical' => 'bg-success',
                                default => 'bg-secondary',
                            };
                        @endphp
                        <tr>
                            <td class="text-muted">{{ $loop->iteration }}</td>
                            <td class="fw-semibold">{{ $exam->name }}</td>
                            <td><div style="font-size:.88rem">{{ $exam->subject?->name ?? 'Subject not linked' }}</div></td>
                            <td><span class="badge {{ $typeBadge }}">{{ ucfirst($exam->type) }}</span></td>
                            <td class="small">{{ $exam->exam_date ? $exam->exam_date->format('d M Y') : 'Exam date not set' }}</td>
                            <td>
                                <span class="fw-semibold">{{ $exam->total_marks }}</span>
                                <span class="text-muted small"> / Pass: {{ $exam->passing_marks }}</span>
                            </td>
                            <td class="small">{{ $exam->semester?->name ?? 'Term not linked' }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <a href="{{ route('teacher.exams.results', $exam) }}"
                                       class="btn btn-sm {{ $hasResults ? 'btn-outline-primary' : 'btn-primary' }}">
                                        <i class="bi bi-pencil me-1"></i>{{ $hasResults ? 'Review Results' : 'Enter Results' }}
                                    </a>
                                    @if($hasResults)
                                        <span class="badge badge-active">
                                            <i class="bi bi-check-circle me-1"></i>{{ $exam->results->count() }} entered
                                        </span>
                                    @else
                                        <span class="badge badge-pending">Marks pending</span>
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
