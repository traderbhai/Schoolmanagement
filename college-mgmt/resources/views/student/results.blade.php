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
        <div class="d-flex flex-wrap gap-3 align-items-end justify-content-between">
            <form method="GET" action="{{ route('student.results') }}" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label small fw-semibold mb-1" style="color:var(--clr-text-muted)">Semester</label>
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
            <div>
                <a href="{{ route('student.transcript.download') }}"
                   class="btn btn-sm btn-outline-primary"
                   title="Download official cumulative transcript (all semesters)">
                    <i class="bi bi-file-earmark-text me-1"></i>Download Official Transcript
                </a>
            </div>
        </div>
    </div>
</div>

@if($report)

@php
    $sgpa         = $report['sgpa'];
    $result       = $report['result'];
    $earned       = $report['earned_credits'];
    $total        = $report['total_credits'];
    $resultPassed = $result === 'Pass';

    $sgpaVariant  = $sgpa >= 8.0 ? 'kpi-green' : ($sgpa >= 6.0 ? 'kpi-blue' : ($sgpa >= 4.0 ? 'kpi-amber' : 'kpi-red'));
@endphp

{{-- KPI Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="kpi-card {{ $sgpaVariant }}">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="kpi-label">Semester GPA (SGPA)</div>
                    <div class="kpi-value">{{ number_format($sgpa, 2) }}<span style="font-size:1rem;opacity:.7"> /10</span></div>
                    <div class="kpi-trend">
                        <i class="bi bi-mortarboard me-1"></i>
                        {{ $sgpa >= 8.0 ? 'Excellent' : ($sgpa >= 6.0 ? 'Good standing' : ($sgpa >= 4.0 ? 'Average' : 'Below average')) }}
                    </div>
                </div>
                <div class="kpi-icon"><i class="bi bi-mortarboard-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="kpi-card kpi-purple">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="kpi-label">Credits Earned</div>
                    <div class="kpi-value">{{ $earned }}<span style="font-size:1rem;opacity:.7">/{{ $total }}</span></div>
                    <div class="kpi-trend"><i class="bi bi-star-half me-1"></i>This semester</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-star-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="kpi-card {{ $resultPassed ? 'kpi-green' : 'kpi-red' }}">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="kpi-label">Semester Result</div>
                    <div class="kpi-value">{{ $result }}</div>
                    <div class="kpi-trend">
                        <i class="bi bi-{{ $resultPassed ? 'patch-check' : 'patch-exclamation' }}-fill me-1"></i>
                        {{ $resultPassed ? 'All subjects cleared' : 'Has failed subjects' }}
                    </div>
                </div>
                <div class="kpi-icon"><i class="bi bi-{{ $resultPassed ? 'patch-check' : 'patch-exclamation' }}-fill"></i></div>
            </div>
        </div>
    </div>
</div>

{{-- CGPA Full-Width Card --}}
@if($cgpa !== null)
@php
    $cgpaPct   = min(round($cgpa * 10, 1), 100);
    $cgpaColor = $cgpa >= 8.0 ? 'success' : ($cgpa >= 6.0 ? 'primary' : ($cgpa >= 4.0 ? 'warning' : 'danger'));
@endphp
<div class="card mb-4 border-0" style="box-shadow:var(--shadow-sm)">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h6 class="mb-0 fw-bold"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Cumulative GPA (CGPA)</h6>
                <small style="color:var(--clr-text-muted)">Cumulative across all completed semesters</small>
            </div>
            <div class="text-end">
                <span class="fw-bold text-{{ $cgpaColor }}" style="font-size:2.2rem;line-height:1">{{ number_format($cgpa, 2) }}</span>
                <span style="color:var(--clr-text-muted);font-size:.85rem"> / 10.0</span>
            </div>
        </div>
        <div class="progress" style="height:16px;border-radius:8px">
            <div class="progress-bar bg-{{ $cgpaColor }}" role="progressbar"
                 style="width:{{ $cgpaPct }}%;border-radius:8px;transition:width .6s ease"
                 aria-valuenow="{{ $cgpa }}" aria-valuemin="0" aria-valuemax="10">
                <span style="font-size:.72rem;font-weight:700">{{ number_format($cgpa,2) }}</span>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Grade Card Download + Subject Results Table --}}
@if(count($report['subjects']) > 0)
<div class="card mb-4" style="box-shadow:var(--shadow-sm)">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-table me-2 text-primary"></i>Subject-wise Results
            <span class="badge bg-secondary ms-1">{{ count($report['subjects']) }} subjects</span>
        </span>
        @if($semesterId)
        <a href="{{ route('student.reports.grade-card', $semesterId) }}" target="_blank"
           class="btn btn-sm btn-primary" aria-label="Download grade card PDF">
            <i class="bi bi-file-earmark-pdf me-1"></i>Download Grade Card
        </a>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Subject</th>
                    <th class="text-center">Credits</th>
                    <th class="text-center">Marks</th>
                    <th class="text-center">%</th>
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
                        in_array($letter, ['O','A+'])                         => 'table-success',
                        in_array($letter, ['C','D'])                          => 'table-warning',
                        $letter === 'F' || ($row['status'] ?? '') === 'fail'  => 'table-danger',
                        default                                                => ''
                    };
                    $statusBadge = match($row['status'] ?? 'pending') {
                        'pass'    => 'badge-active',
                        'fail'    => 'badge-danger',
                        'pending' => 'badge-info',
                        default   => 'badge-info'
                    };
                    $statusLabel = match($row['status'] ?? 'pending') {
                        'pass'    => 'Pass',
                        'fail'    => 'Fail',
                        'pending' => 'Pending',
                        default   => 'N/A'
                    };
                    $gradeBadge = match(true) {
                        in_array($letter ?? '', ['O','A+'])          => 'bg-success',
                        in_array($letter ?? '', ['A','B+','B'])       => 'bg-primary',
                        in_array($letter ?? '', ['C','D'])            => 'bg-warning text-dark',
                        $letter === 'F'                               => 'bg-danger',
                        default                                       => 'bg-secondary'
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
                    <td class="text-center fw-semibold">
                        @if(isset($row['obtained']) && $row['max'] !== null)
                            {{ $row['obtained'] }}<span class="text-muted fw-normal">/{{ $row['max'] }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($row['pct'] !== null)
                            <span class="fw-semibold">{{ $row['pct'] }}%</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($letter)
                        <span class="badge fs-6 {{ $gradeBadge }}" style="min-width:36px">{{ $letter }}</span>
                        @else
                        <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-center fw-bold">
                        @if(isset($row['grade']['points'])) {{ $row['grade']['points'] }} @else <span class="text-muted">—</span> @endif
                    </td>
                    <td class="text-center">
                        <span class="{{ $statusBadge }}">{{ $statusLabel }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Grade Scale Reference (Collapsible) --}}
<div class="card mb-2" style="box-shadow:var(--shadow-sm)">
    <div class="card-header p-0">
        <button class="btn btn-link text-decoration-none fw-semibold w-100 text-start px-3 py-2"
                style="color:var(--clr-text)"
                type="button" data-bs-toggle="collapse" data-bs-target="#gradeScale" aria-expanded="false">
            <i class="bi bi-info-circle me-2 text-info"></i>Grade Scale Reference
            <i class="bi bi-chevron-down float-end mt-1" style="font-size:.8rem"></i>
        </button>
    </div>
    <div id="gradeScale" class="collapse">
        <div class="card-body pt-2 pb-3">
            <div class="row g-2">
                @foreach([
                    ['O',  '90% &amp; above', '10.0', 'Outstanding',  'success'],
                    ['A+', '80% &amp; above', '9.0',  'Excellent',    'success'],
                    ['A',  '70% &amp; above', '8.0',  'Very Good',    'primary'],
                    ['B+', '60% &amp; above', '7.0',  'Good',         'primary'],
                    ['B',  '50% &amp; above', '6.0',  'Average',      'warning'],
                    ['C',  '40% &amp; above', '5.0',  'Pass',         'warning'],
                    ['D',  '35% &amp; above', '4.0',  'Marginal Pass','secondary'],
                    ['F',  'Below 35%',       '0.0',  'Fail',         'danger'],
                ] as [$g,$range,$pts,$desc,$clr])
                <div class="col-6 col-md-3">
                    <div class="d-flex align-items-center gap-2 p-2 rounded" style="background:var(--clr-card-bg,#f8fafc);border:1px solid var(--clr-border,#e2e8f0)">
                        <span class="badge bg-{{ $clr }} fs-6" style="min-width:36px">{{ $g }}</span>
                        <div style="font-size:.75rem;line-height:1.3">
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
<div class="card" style="box-shadow:var(--shadow-sm)">
    <div class="card-body">
        <div class="empty-state py-5">
            <div class="empty-icon"><i class="bi bi-journal-x" style="font-size:3rem;color:var(--clr-text-muted)"></i></div>
            <h5 class="mt-3" style="color:var(--clr-text-muted)">No Results Available</h5>
            <p class="mb-0" style="color:var(--clr-text-muted);font-size:.88rem">
                @if($semesterId)
                    No exam results found for the selected semester. Results will appear once exams are conducted and grades are entered.
                @else
                    Please select a semester to view your results.
                @endif
            </p>
        </div>
    </div>
</div>
@endif

@endsection
