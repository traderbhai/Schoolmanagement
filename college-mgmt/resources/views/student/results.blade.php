@extends('layouts.student')
@section('title', 'My Exam Results')
@section('page-title', 'My Exam Results')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('student.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">My Results</li>
@endsection

@section('content')

{{-- Semester Filter --}}
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('student.results') }}" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-1 text-muted">Semester</label>
                <select name="semester_id" class="form-select form-select-sm" style="min-width:220px" onchange="this.form.submit()">
                    @forelse($semesters as $sem)
                        <option value="{{ $sem->id }}" @selected($sem->id == $semesterId)>
                            {{ $sem->name }}
                            @if($sem->academicYear) &mdash; {{ $sem->academicYear->name }} @endif
                        </option>
                    @empty
                        <option disabled>No semesters found</option>
                    @endforelse
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
            </div>
        </form>
    </div>
</div>

@if($report)

@php
    $sgpa   = $report['sgpa'];
    $result = $report['result'];
    $earned = $report['earned_credits'];
    $total  = $report['total_credits'];

    $sgpaColor = $sgpa >= 8.0 ? '#10b981,#047857'
               : ($sgpa >= 6.0 ? '#3b82f6,#1d4ed8'
               : ($sgpa >= 4.0 ? '#f59e0b,#b45309'
               : '#ef4444,#b91c1c'));
    $resultPassed = $result === 'Pass';
@endphp

{{-- Top Stat Cards --}}
<div class="row g-3 mb-4">
    {{-- SGPA --}}
    <div class="col-md-4">
        <div class="stat-card" style="background:linear-gradient(135deg,{{ $sgpaColor }})">
            <div class="d-flex align-items-center gap-3">
                <div style="font-size:2.4rem;line-height:1">
                    <i class="bi bi-mortarboard"></i>
                </div>
                <div>
                    <div class="fs-2 fw-bold lh-1">{{ number_format($sgpa, 2) }}</div>
                    <div class="small opacity-75 mt-1">Semester GPA (SGPA)</div>
                    <div class="small opacity-60">out of 10.0</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Credits --}}
    <div class="col-md-4">
        <div class="stat-card" style="background:linear-gradient(135deg,#8b5cf6,#6d28d9)">
            <div class="d-flex align-items-center gap-3">
                <div style="font-size:2.4rem;line-height:1">
                    <i class="bi bi-star-half"></i>
                </div>
                <div>
                    <div class="fs-2 fw-bold lh-1">{{ $earned }}<span class="fs-6 opacity-75">/{{ $total }}</span></div>
                    <div class="small opacity-75 mt-1">Credits Earned</div>
                    <div class="small opacity-60">this semester</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Result --}}
    <div class="col-md-4">
        <div class="stat-card" style="background:linear-gradient(135deg,{{ $resultPassed ? '#10b981,#047857' : '#ef4444,#b91c1c' }})">
            <div class="d-flex align-items-center gap-3">
                <div style="font-size:2.4rem;line-height:1">
                    <i class="bi bi-{{ $resultPassed ? 'patch-check' : 'patch-exclamation' }}-fill"></i>
                </div>
                <div>
                    <div class="fs-2 fw-bold lh-1">{{ $result }}</div>
                    <div class="small opacity-75 mt-1">Semester Result</div>
                    <div class="small opacity-60">{{ $resultPassed ? 'All subjects cleared' : 'Has failed subjects' }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- CGPA Card --}}
@if($cgpa !== null)
@php
    $cgpaPct = min(round($cgpa * 10, 1), 100);
    $cgpaColor = $cgpa >= 8.0 ? 'success' : ($cgpa >= 6.0 ? 'primary' : ($cgpa >= 4.0 ? 'warning' : 'danger'));
@endphp
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
                <h6 class="mb-0 fw-bold"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Cumulative GPA (CGPA)</h6>
                <small class="text-muted">Across all completed semesters</small>
            </div>
            <div class="text-end">
                <span class="fs-2 fw-bold text-{{ $cgpaColor }}">{{ number_format($cgpa, 2) }}</span>
                <span class="text-muted small"> / 10.0</span>
            </div>
        </div>
        <div class="progress" style="height:14px; border-radius:8px">
            <div class="progress-bar bg-{{ $cgpaColor }}" role="progressbar"
                 style="width:{{ $cgpaPct }}%; border-radius:8px"
                 aria-valuenow="{{ $cgpa }}" aria-valuemin="0" aria-valuemax="10">
                <span style="font-size:.72rem; font-weight:600">{{ number_format($cgpa,2) }}</span>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Subject-wise Results Table --}}
@if(count($report['subjects']) > 0)
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-table me-2 text-primary"></i>Subject-wise Results</span>
        <span class="badge bg-secondary">{{ count($report['subjects']) }} subjects</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Subject</th>
                    <th class="text-center">Credits</th>
                    <th class="text-center">Max Marks</th>
                    <th class="text-center">Marks Obtained</th>
                    <th class="text-center">Grade</th>
                    <th class="text-center">Points</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report['subjects'] as $row)
                @php
                    $letter  = $row['grade']['letter'] ?? null;
                    $rowBg   = match(true) {
                        in_array($letter, ['O','A+'])       => 'table-success',
                        in_array($letter, ['B+','B'])       => 'table-warning',
                        $letter === 'F' || $row['status'] === 'fail' => 'table-danger',
                        default => ''
                    };
                    $statusBadge = match($row['status'] ?? 'pending') {
                        'pass'    => 'bg-success',
                        'fail'    => 'bg-danger',
                        'pending' => 'bg-secondary',
                        default   => 'bg-secondary'
                    };
                    $statusLabel = match($row['status'] ?? 'pending') {
                        'pass'    => 'Pass',
                        'fail'    => 'Fail',
                        'pending' => 'Pending',
                        default   => 'N/A'
                    };
                @endphp
                <tr class="{{ $rowBg }}">
                    <td>
                        <div class="fw-semibold" style="font-size:.88rem">{{ $row['subject']->name }}</div>
                        @if(!empty($row['has_absent']))
                        <small class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>Absent in exam(s)</small>
                        @endif
                    </td>
                    <td class="text-center fw-semibold">{{ $row['credits'] }}</td>
                    <td class="text-center">
                        @if($row['max'] !== null) {{ $row['max'] }} @else <span class="text-muted">—</span> @endif
                    </td>
                    <td class="text-center fw-semibold">
                        @if(isset($row['obtained'])) {{ $row['obtained'] }} @else <span class="text-muted">—</span> @endif
                        @if($row['pct'] !== null)
                        <div class="text-muted" style="font-size:.72rem">{{ $row['pct'] }}%</div>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($letter)
                        <span class="badge fs-6 {{ in_array($letter,['O','A+']) ? 'bg-success' : (in_array($letter,['B+','B','A']) ? 'bg-primary' : ($letter === 'F' ? 'bg-danger' : 'bg-warning text-dark')) }}">
                            {{ $letter }}
                        </span>
                        @else
                        <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td class="text-center fw-bold">
                        @if(isset($row['grade']['points'])) {{ $row['grade']['points'] }} @else <span class="text-muted">—</span> @endif
                    </td>
                    <td class="text-center">
                        <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Grade Scale Reference (Collapsible) --}}
<div class="card">
    <div class="card-header p-0">
        <button class="btn btn-link text-decoration-none text-dark fw-semibold w-100 text-start px-3 py-2"
                type="button" data-bs-toggle="collapse" data-bs-target="#gradeScale" aria-expanded="false">
            <i class="bi bi-info-circle me-2 text-info"></i>Grade Scale Reference
            <i class="bi bi-chevron-down ms-auto float-end mt-1" style="font-size:.8rem"></i>
        </button>
    </div>
    <div id="gradeScale" class="collapse">
        <div class="card-body pb-3 pt-2">
            <div class="row g-2">
                @foreach([
                    ['O',  '90% &amp; above', '10.0', 'Outstanding',    'success'],
                    ['A+', '80% &amp; above', '9.0',  'Excellent',       'success'],
                    ['A',  '70% &amp; above', '8.0',  'Very Good',       'primary'],
                    ['B+', '60% &amp; above', '7.0',  'Good',            'primary'],
                    ['B',  '50% &amp; above', '6.0',  'Average',         'warning'],
                    ['C',  '40% &amp; above', '5.0',  'Pass',            'warning'],
                    ['D',  '35% &amp; above', '4.0',  'Marginal Pass',   'secondary'],
                    ['F',  'Below 35%',        '0.0',  'Fail',            'danger'],
                ] as [$g,$range,$pts,$desc,$clr])
                <div class="col-6 col-md-3">
                    <div class="d-flex align-items-center gap-2 p-2 rounded" style="background:#f8fafc; border:1px solid #e2e8f0">
                        <span class="badge bg-{{ $clr }} fs-6" style="min-width:36px">{{ $g }}</span>
                        <div style="font-size:.78rem; line-height:1.3">
                            <div class="fw-semibold">{!! $range !!}</div>
                            <div class="text-muted">{{ $pts }} pts &middot; {{ $desc }}</div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

@else
{{-- Empty State --}}
<div class="card">
    <div class="card-body text-center py-5">
        <i class="bi bi-journal-x text-muted" style="font-size:3rem"></i>
        <h5 class="mt-3 text-muted">No Results Available</h5>
        <p class="text-muted small mb-0">
            @if($semesterId)
                No exam results found for the selected semester. Results will appear here once exams are conducted and grades are entered.
            @else
                Please select a semester to view your results.
            @endif
        </p>
    </div>
</div>
@endif

@endsection
