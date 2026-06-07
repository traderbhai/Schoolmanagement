@extends('layouts.admin')
@section('title', 'Transcript — ' . $student->user?->name)
@section('page-title', 'Academic Transcript')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Academic Transcript</h4>
    <div>
        <a href="{{ route('academic.transcripts.index') }}" class="btn btn-sm btn-outline-secondary me-2">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
        <a href="{{ route('academic.transcripts.pdf', $student) }}" class="btn btn-sm btn-danger" target="_blank">
            <i class="bi bi-file-pdf me-1"></i>Download PDF
        </a>
    </div>
</div>

{{-- Student Info --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-sm-3">
                <div class="text-muted small">Student Name</div>
                <div class="fw-semibold">{{ $student->user?->name ?? '—' }}</div>
            </div>
            <div class="col-sm-3">
                <div class="text-muted small">Enrollment No.</div>
                <div class="fw-semibold">{{ $student->enrollment_number }}</div>
            </div>
            <div class="col-sm-3">
                <div class="text-muted small">Program</div>
                <div class="fw-semibold">{{ $student->program?->name ?? '—' }}</div>
            </div>
            <div class="col-sm-3">
                <div class="text-muted small">Batch</div>
                <div class="fw-semibold">{{ $student->batch?->name ?? '—' }}</div>
            </div>
        </div>
        @if($transcript)
        <div class="mt-2 pt-2 border-top">
            <span class="badge bg-success me-2"><i class="bi bi-check-circle me-1"></i>Transcript Issued</span>
            <span class="text-muted small">Last issued: {{ $transcript->issued_at?->format('d M Y') }}</span>
        </div>
        @endif
    </div>
</div>

{{-- Semester Results --}}
@foreach($semesterReports as $report)
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-light fw-semibold d-flex justify-content-between">
        <span><i class="bi bi-calendar3 me-2 text-primary"></i>{{ $report['term']->name }}</span>
        <span>
            SGPA: <strong class="text-primary">{{ $report['sgpa'] }}</strong>
            &nbsp;|&nbsp;Credits: {{ $report['earned_credits'] }}/{{ $report['total_credits'] }}
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Subject</th>
                        <th>Credits</th>
                        <th>Marks</th>
                        <th>%</th>
                        <th>Grade</th>
                        <th>Result</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($report['subjects'] as $s)
                    <tr>
                        <td class="small">{{ $s['subject']?->name ?? '—' }}</td>
                        <td class="small">{{ $s['credits'] }}</td>
                        <td class="small">
                            @if($s['obtained'] !== null)
                                {{ $s['obtained'] }}/{{ $s['total'] }}
                            @else
                                <span class="text-muted">Pending</span>
                            @endif
                        </td>
                        <td class="small">{{ $s['pct'] !== null ? $s['pct'].'%' : '—' }}</td>
                        <td><strong>{{ $s['grade']['letter'] ?? '—' }}</strong></td>
                        <td>
                            @if($s['status'] === 'pass')
                                <span class="badge bg-success">Pass</span>
                            @elseif($s['status'] === 'fail')
                                <span class="badge bg-danger">Fail</span>
                            @else
                                <span class="badge bg-secondary">Pending</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endforeach

@if(empty($semesterReports))
<div class="alert alert-info">No semester data found for this student.</div>
@endif

{{-- CGPA Summary --}}
<div class="card border-0 shadow-sm border-start border-primary border-4">
    <div class="card-body d-flex align-items-center gap-4">
        <div class="text-center">
            <div class="fs-1 fw-bold text-primary">{{ $cgpa }}</div>
            <div class="text-muted small">Cumulative GPA (CGPA)</div>
        </div>
        <div class="vr"></div>
        <div>
            <div class="fs-4 fw-bold">{{ $totalCredits }}</div>
            <div class="text-muted small">Total Credits</div>
        </div>
    </div>
</div>
@endsection
