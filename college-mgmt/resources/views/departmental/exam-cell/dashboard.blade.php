@extends('layouts.admin')
@section('title', 'Exam Cell — Dashboard')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-file-earmark-check me-2 text-primary"></i>Exam Cell Dashboard</h4>
    <a href="{{ route('admin.exams.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>New Exam</a>
</div>

{{-- KPI Cards --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-2">
        <div class="card text-center border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold text-primary">{{ $total }}</div>
                <div class="text-muted small">Total Exams</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card text-center border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold text-info">{{ $upcoming }}</div>
                <div class="text-muted small">Upcoming</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card text-center border-0 shadow-sm h-100 {{ $pending > 0 ? 'border border-danger' : '' }}">
            <div class="card-body">
                <div class="fs-2 fw-bold {{ $pending > 0 ? 'text-danger' : 'text-secondary' }}">{{ $pending }}</div>
                <div class="text-muted small">Pending Entry</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card text-center border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold text-success">{{ $withResults }}</div>
                <div class="text-muted small">Results Entered</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card text-center border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold text-warning">{{ $completionPct }}%</div>
                <div class="text-muted small">Completion</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card text-center border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold text-secondary">{{ $published }}</div>
                <div class="text-muted small">Published</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <a href="{{ route('exam-cell.anomalies.index') }}" class="text-decoration-none">
            <div class="card text-center border-0 shadow-sm h-100 {{ ($anomalyCount ?? 0) > 0 ? 'border border-danger' : '' }}">
                <div class="card-body">
                    <div class="fs-2 fw-bold {{ ($anomalyCount ?? 0) > 0 ? 'text-danger' : 'text-secondary' }}">{{ $anomalyCount ?? 0 }}</div>
                    <div class="text-muted small">Open Anomalies</div>
                </div>
            </div>
        </a>
    </div>
</div>

{{-- Progress Bar --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="small fw-semibold">Result Entry Completion</span>
            <span class="small text-muted">{{ $withResults }} / {{ $withResults + $pending }} past exams</span>
        </div>
        <div class="progress" style="height:10px">
            <div class="progress-bar bg-{{ $completionPct >= 75 ? 'success' : ($completionPct >= 50 ? 'warning' : 'danger') }}"
                 style="width:{{ $completionPct }}%"></div>
        </div>
    </div>
</div>

<div class="row g-3">
    {{-- Recent Exams --}}
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-journal-text me-2 text-primary"></i>Recent Exams
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Exam</th><th>Subject</th><th>Program</th><th>Date</th><th>Results</th><th>Avg</th><th>Pass %</th></tr>
                        </thead>
                        <tbody>
                        @forelse($recentExams as $exam)
                            <tr>
                                <td class="small fw-semibold">{{ $exam->name }}</td>
                                <td class="small text-muted">{{ $exam->subject?->name ?? '—' }}</td>
                                <td class="small text-muted">{{ $exam->program?->code ?? '—' }}</td>
                                <td class="small">{{ $exam->exam_date ? $exam->exam_date->format('d M Y') : '—' }}</td>
                                <td><span class="badge bg-secondary">{{ $exam->result_count }}</span></td>
                                <td class="small">{{ $exam->avg_marks ?? '—' }}</td>
                                <td>
                                    @if($exam->pass_pct !== null)
                                        <span class="badge bg-{{ $exam->pass_pct >= 75 ? 'success' : ($exam->pass_pct >= 50 ? 'warning' : 'danger') }}">{{ $exam->pass_pct }}%</span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-3">No exams found.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Upcoming Exams --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-calendar-event me-2 text-success"></i>Upcoming Exams
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                @forelse($upcomingExams as $exam)
                    <li class="list-group-item py-2 px-3">
                        <div class="fw-semibold small">{{ $exam->name }}</div>
                        <div class="text-muted" style="font-size:.75rem">
                            <i class="bi bi-calendar3 me-1"></i>{{ $exam->exam_date->format('d M Y') }}
                            @if($exam->program) · {{ $exam->program->code }} @endif
                        </div>
                        @if($exam->subject)<div class="text-muted" style="font-size:.72rem"><i class="bi bi-book me-1"></i>{{ $exam->subject->name }}</div>@endif
                    </li>
                @empty
                    <li class="list-group-item text-center text-muted py-3">No upcoming exams.</li>
                @endforelse
                </ul>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-transparent fw-semibold"><i class="bi bi-lightning me-2 text-warning"></i>Quick Actions</div>
            <div class="card-body d-flex flex-wrap gap-2">
                <a href="{{ route('exam-cell.exams') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-list-ul me-1"></i>All Exams</a>
                <a href="{{ route('exam-cell.results') }}" class="btn btn-sm btn-outline-success"><i class="bi bi-pencil me-1"></i>Enter Results</a>
            </div>
        </div>
    </div>
</div>
@endsection
