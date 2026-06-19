@extends('layouts.student')
@section('title', 'My Results')

@section('content')
<div class="container-fluid py-3 px-3 px-md-4">

{{-- Semester Filter + Download --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap gap-3 align-items-end justify-content-between">
            <form method="GET" action="{{ route('student.results') }}" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label small fw-semibold mb-1">Semester / Term</label>
                    <select name="semester_id" class="form-select form-select-sm" style="min-width:220px" onchange="this.form.submit()">
                        @forelse($semesters as $sem)
                            <option value="{{ $sem->id }}" @selected($sem->id == $semesterId)>
                                {{ $sem->name }}@if($sem->academicYear) &mdash; {{ $sem->academicYear->name }}@endif
                            </option>
                        @empty
                            <option disabled>No semesters found</option>
                        @endforelse
                    </select>
                </div>
            </form>
            @if($issuedTranscript)
                <a href="{{ route('student.transcript.download') }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-file-earmark-text me-1"></i>Official Transcript PDF
                </a>
            @else
                <span class="badge bg-light text-muted border">Official transcript not issued</span>
            @endif
        </div>
    </div>
</div>

@if($report)
@php
    $sgpa    = $report['sgpa'];
    $result  = $report['result'];
    $earned  = $report['earned_credits'];
    $total   = $report['total_credits'];
    $passed  = $result === 'Pass';
    $sgpaColor = $sgpa >= 8.0 ? 'success' : ($sgpa >= 6.0 ? 'primary' : ($sgpa >= 4.0 ? 'warning' : 'danger'));
@endphp

{{-- KPI Row --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-{{ $sgpaColor }}">{{ number_format($sgpa,2) }}</div>
                <div class="text-muted small">SGPA / 10</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-info">{{ $cgpa ? number_format($cgpa,2) : '—' }}</div>
                <div class="text-muted small">Overall CGPA</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold">{{ $earned }}<span class="text-muted fs-5">/{{ $total }}</span></div>
                <div class="text-muted small">Credits Earned</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold {{ $passed ? 'text-success' : 'text-danger' }}">{{ $result }}</div>
                <div class="text-muted small">Semester Result</div>
            </div>
        </div>
    </div>
</div>

{{-- Subject-wise Results with Component Breakdown --}}
@if(count($report['subjects']) > 0)
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-table me-2"></i>Subject-wise Results</span>
        @if($semesterId && $gradeCardAvailable)
        <a href="{{ route('student.reports.grade-card', $semesterId) }}" target="_blank" class="btn btn-sm btn-primary">
            <i class="bi bi-file-earmark-pdf me-1"></i>Grade Card PDF
        </a>
        @elseif($semesterId)
        <span class="badge bg-light text-muted border">Grade card pending publication</span>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Subject</th>
                    <th class="text-center">Credits</th>
                    <th class="text-center">IA1</th>
                    <th class="text-center">IA2</th>
                    <th class="text-center">End-Sem</th>
                    <th class="text-center">Total</th>
                    <th class="text-center">%</th>
                    <th class="text-center">Grade</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report['subjects'] as $row)
                @php
                    $letter = $row['grade']['letter'] ?? null;
                    $rowBg  = match(true) {
                        $letter === 'F' || ($row['status'] ?? '') === 'fail' => 'table-danger',
                        in_array($letter, ['C','D'])                          => 'table-warning',
                        in_array($letter, ['O','A+'])                         => 'table-success',
                        default                                                => ''
                    };
                    $gradeBg = match(true) {
                        in_array($letter ?? '', ['O','A+']) => 'bg-success',
                        in_array($letter ?? '', ['A','B+','B']) => 'bg-primary',
                        in_array($letter ?? '', ['C','D']) => 'bg-warning text-dark',
                        $letter === 'F' => 'bg-danger',
                        default => 'bg-secondary'
                    };
                    // Gather component marks from assessmentComponents
                    $ia1 = null; $ia2 = null; $endSem = null;
                    foreach ($row['results'] as $result) {
                        // Direct fields first
                        if ($result->ia1_marks !== null) $ia1 = $result->ia1_marks;
                        if ($result->ia2_marks !== null) $ia2 = $result->ia2_marks;
                        if ($result->end_sem_marks !== null) $endSem = $result->end_sem_marks;
                        // AssessmentComponent relation
                        foreach ($result->assessmentComponents ?? [] as $comp) {
                            if ($comp->component_type === 'ia1') $ia1 = $comp->marks_obtained;
                            if ($comp->component_type === 'ia2') $ia2 = $comp->marks_obtained;
                            if ($comp->component_type === 'end_semester') $endSem = $comp->marks_obtained;
                        }
                    }
                @endphp
                <tr class="{{ $rowBg }}">
                    <td>
                        <div class="fw-semibold" style="font-size:.88rem">{{ $row['subject']->name }}</div>
                        @if(!empty($row['has_absent']))<small class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>Absent in exam</small>@endif
                    </td>
                    <td class="text-center">{{ $row['credits'] }}</td>
                    <td class="text-center small">
                        @if($ia1 !== null)<span class="fw-semibold">{{ $ia1 }}</span>@else<span class="text-muted">—</span>@endif
                    </td>
                    <td class="text-center small">
                        @if($ia2 !== null)<span class="fw-semibold">{{ $ia2 }}</span>@else<span class="text-muted">—</span>@endif
                    </td>
                    <td class="text-center small">
                        @if($endSem !== null)<span class="fw-semibold">{{ $endSem }}</span>@else<span class="text-muted">—</span>@endif
                    </td>
                    <td class="text-center fw-semibold">
                        @if(isset($row['obtained'])){{ $row['obtained'] }}<span class="text-muted fw-normal">/{{ $row['max'] }}</span>@else<span class="text-muted">—</span>@endif
                    </td>
                    <td class="text-center">
                        @if($row['pct'] !== null)<span class="fw-semibold">{{ $row['pct'] }}%</span>@else<span class="text-muted">—</span>@endif
                    </td>
                    <td class="text-center">
                        @if($letter)<span class="badge fs-6 {{ $gradeBg }}" style="min-width:34px">{{ $letter }}</span>@else<span class="text-muted">—</span>@endif
                    </td>
                    <td class="text-center">
                        @if(($row['status'] ?? 'pending') === 'pass')
                            <span class="badge bg-success">Pass</span>
                        @elseif(($row['status'] ?? '') === 'fail')
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
    <div class="card-footer text-muted small">
        IA1 = Internal Assessment 1 &nbsp;·&nbsp; IA2 = Internal Assessment 2 &nbsp;·&nbsp; End-Sem = End Semester Examination
    </div>
</div>
@endif

{{-- Grade Scale --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header p-0">
        <button class="btn btn-link text-decoration-none fw-semibold w-100 text-start px-3 py-2 text-dark"
                type="button" data-bs-toggle="collapse" data-bs-target="#gradeScale">
            <i class="bi bi-info-circle me-2 text-info"></i>Grade Scale Reference
        </button>
    </div>
    <div id="gradeScale" class="collapse">
        <div class="card-body">
            <div class="row g-2">
                @foreach([['O','≥90%','10.0','Outstanding','success'],['A+','≥80%','9.0','Excellent','success'],['A','≥70%','8.0','Very Good','primary'],['B+','≥60%','7.0','Good','primary'],['B','≥50%','6.0','Average','warning'],['C','≥40%','5.0','Pass','warning'],['D','≥35%','4.0','Marginal','secondary'],['F','<35%','0.0','Fail','danger']] as [$g,$r,$p,$d,$c])
                <div class="col-6 col-md-3">
                    <div class="d-flex align-items-center gap-2 p-2 rounded border">
                        <span class="badge bg-{{ $c }} fs-6" style="min-width:34px">{{ $g }}</span>
                        <div style="font-size:.75rem;line-height:1.3"><div class="fw-semibold">{{ $r }}</div><div class="text-muted">{{ $p }}pts · {{ $d }}</div></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

@else
<div class="alert alert-info">Select a semester above to view your results.</div>
@endif

{{-- S2-2: Backlog / Arrear Tracker --}}
@if($backlogs->isNotEmpty())
<div class="card border-0 shadow-sm border-start border-danger border-3">
    <div class="card-header fw-semibold text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Backlogs / Arrears ({{ $backlogs->count() }} subject{{ $backlogs->count() > 1 ? 's' : '' }})</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr><th>Subject</th><th>Semester</th><th>Marks</th><th>%</th><th>Grade</th></tr>
            </thead>
            <tbody>
                @foreach($backlogs as $r)
                @php
                    $pct = $r->exam->total_marks > 0 ? round(($r->marks_obtained / $r->exam->total_marks) * 100, 1) : 0;
                @endphp
                <tr class="table-danger">
                    <td class="fw-semibold">{{ $r->exam->subject->name ?? '—' }}</td>
                    <td class="text-muted small">{{ $r->exam->semester->name ?? '—' }}</td>
                    <td>{{ $r->marks_obtained }}/{{ $r->exam->total_marks }}</td>
                    <td>{{ $pct }}%</td>
                    <td><span class="badge bg-danger">F</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer text-muted small">
        <i class="bi bi-info-circle me-1"></i>Contact the Exam Cell to register for supplementary / back paper examinations.
    </div>
</div>
@endif

</div>
@endsection
