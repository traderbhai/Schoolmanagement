@extends('layouts.admin')
@section('title', 'AICTE Compliance Report')
@section('page-title', 'AICTE Compliance Report')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-1">AICTE Compliance Report — {{ $reportYear }}</h5>
        <p class="text-muted small mb-0">Academic Performance &amp; Institutional Data</p>
    </div>
    <a href="{{ route('admin.aicte-report.pdf') }}" class="btn btn-primary" target="_blank">
        <i class="bi bi-file-earmark-pdf me-2"></i>Export PDF
    </a>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-600"><i class="bi bi-table me-2 text-primary"></i>Program-wise Compliance Data</span>
        <span class="text-muted small">Total Students: {{ $totalStudents }} | Total Faculty: {{ $totalFaculty }}</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Program</th>
                    <th>Department</th>
                    <th>Students</th>
                    <th>Faculty</th>
                    <th>Subjects</th>
                    <th>Exams ({{ $reportYear }})</th>
                    <th>Pass Rate %</th>
                    <th>Attendance %</th>
                </tr>
            </thead>
            <tbody>
            @forelse($programs as $prog)
            @php
                $passClass = $prog->pass_rate === null ? 'secondary' : ($prog->pass_rate < 60 ? 'danger' : ($prog->pass_rate < 75 ? 'warning' : 'success'));
                $attClass  = $prog->attendance_pct === null ? 'secondary' : ($prog->attendance_pct < 60 ? 'danger' : ($prog->attendance_pct < 75 ? 'warning' : 'success'));
            @endphp
            <tr>
                <td class="fw-semibold">{{ $prog->name }}</td>
                <td>{{ $prog->department->name ?? '—' }}</td>
                <td>{{ $prog->active_students }}</td>
                <td>{{ $prog->faculty_count }}</td>
                <td>{{ $prog->subject_count }}</td>
                <td>{{ $prog->exam_count }}</td>
                <td>
                    @if($prog->pass_rate !== null)
                        <span class="badge bg-{{ $passClass }}">{{ $prog->pass_rate }}%</span>
                    @else
                        <span class="text-muted small">N/A</span>
                    @endif
                </td>
                <td>
                    @if($prog->attendance_pct !== null)
                        <span class="badge bg-{{ $attClass }}">{{ $prog->attendance_pct }}%</span>
                    @else
                        <span class="text-muted small">N/A</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="8" class="text-center text-muted py-3">No active programs found.</td></tr>
            @endforelse
            </tbody>
            <tfoot class="table-light fw-bold">
                <tr>
                    <td colspan="2">INSTITUTION TOTAL</td>
                    <td>{{ $totalStudents }}</td>
                    <td>{{ $totalFaculty }}</td>
                    <td colspan="4"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="alert alert-info d-flex align-items-center gap-2">
    <i class="bi bi-info-circle-fill fs-5"></i>
    <div><strong>Note:</strong> For official AICTE submission, use the <a href="{{ route('admin.aicte-report.pdf') }}" target="_blank">PDF export</a>. Color coding: <span class="badge bg-success">Green</span> &gt;75% | <span class="badge bg-warning text-dark">Yellow</span> 60–75% | <span class="badge bg-danger">Red</span> &lt;60%.</div>
</div>

@endsection
