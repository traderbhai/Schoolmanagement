@extends('layouts.admin')
@section('title','Grade Report')
@section('page-title','Grade & Result Report')
@section('content')
<div class="card mb-4">
    <div class="card-body">
        <form class="row g-3 align-items-end" method="GET">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Student *</label>
                <select name="student_id" class="form-select">
                    <option value="">Select student</option>
                    @foreach($students as $s)<option value="{{ $s->id }}" @selected(request('student_id')==$s->id)>{{ $s->user->name }} ({{ $s->enrollment_number }})</option>@endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Semester *</label>
                <select name="semester_id" class="form-select">
                    <option value="">Select semester</option>
                    @foreach($semesters as $s)<option value="{{ $s->id }}" @selected(request('semester_id')==$s->id)>{{ $s->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-primary">Generate</button></div>
        </form>
    </div>
</div>

@if($report && $student)
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <div class="text-muted small">Student</div>
                <div class="fw-bold">{{ $student->user->name }}</div>
                <div class="text-muted small">{{ $student->enrollment_number }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center" style="background:{{ $report['sgpa'] >= 7 ? '#d1fae5' : ($report['sgpa'] >= 5 ? '#fef3c7' : '#fee2e2') }}">
            <div class="card-body">
                <div class="text-muted small">SGPA</div>
                <div class="fw-bold fs-3">{{ $report['sgpa'] }}</div>
                <div class="text-muted small">{{ $semester->name }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <div class="text-muted small">CGPA (Overall)</div>
                <div class="fw-bold fs-3">{{ $cgpa }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center" style="background:{{ $report['result'] === 'Pass' ? '#d1fae5' : '#fee2e2' }}">
            <div class="card-body">
                <div class="text-muted small">Result</div>
                <div class="fw-bold fs-3 {{ $report['result'] === 'Pass' ? 'text-success' : 'text-danger' }}">{{ $report['result'] }}</div>
                <div class="text-muted small">Credits: {{ $report['earned_credits'] }}/{{ $report['total_credits'] }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Subject-wise Results — {{ $semester->name }}</span>
        <a href="{{ route('admin.reports.grade-card', [$student->id, $semester->id]) }}" target="_blank" class="btn btn-sm btn-outline-primary" aria-label="Download grade card PDF">
            <i class="bi bi-file-earmark-pdf me-1"></i>Download Grade Card
        </a>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>Credits</th>
                    <th>Marks Obtained</th>
                    <th>Percentage</th>
                    <th>Grade</th>
                    <th>Grade Points</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            @foreach($report['subjects'] as $row)
            <tr>
                <td>
                    <div class="fw-semibold">{{ $row['subject']->name }}</div>
                    <div class="text-muted small">{{ $row['subject']->code }}</div>
                </td>
                <td>{{ $row['credits'] }}</td>
                <td>
                    @if($row['pct'] === null)
                        <span class="text-muted">–</span>
                    @else
                        {{ $row['obtained'] }}/{{ $row['total'] }}
                    @endif
                </td>
                <td>
                    @if($row['pct'] !== null)
                    <div class="d-flex align-items-center gap-2">
                        <div class="progress flex-grow-1" style="height:6px;min-width:60px">
                            <div class="progress-bar bg-{{ $row['pct'] >= 60 ? 'success' : ($row['pct'] >= 35 ? 'warning' : 'danger') }}" style="width:{{ $row['pct'] }}%"></div>
                        </div>
                        <span class="small">{{ $row['pct'] }}%</span>
                    </div>
                    @else <span class="text-muted small">Pending</span> @endif
                </td>
                <td>
                    @if($row['grade'])
                    <span class="badge fs-6 bg-{{ $row['grade']['letter'] === 'F' ? 'danger' : ($row['grade']['points'] >= 8 ? 'success' : ($row['grade']['points'] >= 6 ? 'primary' : 'warning text-dark')) }}">
                        {{ $row['grade']['letter'] }}
                    </span>
                    @else <span class="text-muted">–</span> @endif
                </td>
                <td>{{ $row['grade'] ? $row['grade']['points'] : '–' }}</td>
                <td>
                    @if($row['status'] === 'pending')<span class="badge bg-secondary">Pending</span>
                    @elseif($row['status'] === 'pass')<span class="badge bg-success">Pass</span>
                    @else<span class="badge bg-danger">Fail</span>@endif
                </td>
            </tr>
            @endforeach
            </tbody>
            <tfoot class="table-light fw-semibold">
                <tr>
                    <td>Total</td>
                    <td>{{ $report['total_credits'] }}</td>
                    <td colspan="2"></td>
                    <td>SGPA: {{ $report['sgpa'] }}</td>
                    <td>{{ $report['earned_points'] }}</td>
                    <td><span class="badge {{ $report['result'] === 'Pass' ? 'bg-success' : 'bg-danger' }}">{{ $report['result'] }}</span></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endif
@endsection
