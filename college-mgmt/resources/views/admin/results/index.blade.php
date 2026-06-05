@extends('layouts.admin')
@section('title', 'Grade Report')
@section('page-title', 'Grade & Result Report')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Grade Reports</li>
@endsection

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h5 class="mb-0 fw-bold">Grade &amp; Result Report</h5>
        <div class="text-muted" style="font-size:.82rem">View and download student grade cards</div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header fw-semibold">Generate Report</div>
    <div class="card-body">
        <form class="row g-3 align-items-end" method="GET">
            <div class="col-md-4">
                <label class="form-label">Student</label>
                <select name="student_id" class="form-select form-select-sm">
                    <option value="">Select student…</option>
                    @foreach($students as $s)
                        <option value="{{ $s->id }}" @selected(request('student_id')==$s->id)>{{ $s->user->name }} ({{ $s->enrollment_number }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Semester</label>
                <select name="semester_id" class="form-select form-select-sm">
                    <option value="">Select semester…</option>
                    @foreach($semesters as $s)
                        <option value="{{ $s->id }}" @selected(request('semester_id')==$s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Generate</button>
            </div>
        </form>
    </div>
</div>

@if($report && $student)
{{-- Summary KPI cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body py-3">
                <div class="text-muted" style="font-size:.78rem">Student</div>
                <div class="fw-bold mt-1">{{ $student->user->name }}</div>
                <div class="text-muted" style="font-size:.78rem">{{ $student->enrollment_number }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card {{ $report['sgpa'] >= 7 ? 'kpi-green' : ($report['sgpa'] >= 5 ? 'kpi-amber' : 'kpi-red') }}">
            <div class="kpi-label">SGPA</div>
            <div class="kpi-value mt-1">{{ $report['sgpa'] }}</div>
            <div class="kpi-label mt-1">{{ $semester->name }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card kpi-blue">
            <div class="kpi-label">CGPA (Overall)</div>
            <div class="kpi-value mt-1">{{ $cgpa }}</div>
            <div class="kpi-label mt-1">Cumulative</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card {{ $report['result'] === 'Pass' ? 'kpi-green' : 'kpi-red' }}">
            <div class="kpi-label">Result</div>
            <div class="kpi-value mt-1">{{ $report['result'] }}</div>
            <div class="kpi-label mt-1">Credits: {{ $report['earned_credits'] }}/{{ $report['total_credits'] }}</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Subject-wise Results — {{ $semester->name }}</span>
        <a href="{{ route('admin.reports.grade-card', [$student->id, $semester->id]) }}" target="_blank" class="btn btn-sm btn-outline-primary" aria-label="Download grade card PDF">
            <i class="bi bi-file-earmark-pdf me-1"></i>Download Grade Card
        </a>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
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
                    <div class="text-muted" style="font-size:.78rem">{{ $row['subject']->code }}</div>
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
                        <span style="font-size:.8rem">{{ $row['pct'] }}%</span>
                    </div>
                    @else <span class="text-muted" style="font-size:.8rem">Pending</span> @endif
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
                    @elseif($row['status'] === 'pass')<span class="badge badge-active">Pass</span>
                    @else<span class="badge badge-danger">Fail</span>@endif
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
                    <td><span class="badge {{ $report['result'] === 'Pass' ? 'badge-active' : 'badge-danger' }}">{{ $report['result'] }}</span></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@elseif(request('student_id'))
<div class="card">
    <div class="card-body">
        <div class="empty-state py-4">
            <div class="empty-icon"><i class="bi bi-search"></i></div>
            <div class="mt-2 fw-semibold">No results found</div>
            <div class="text-muted" style="font-size:.85rem">No attendance records found for the selected criteria.</div>
        </div>
    </div>
</div>
@endif
@endsection
