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
        <div class="kpi-card kpi-blue">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="bi bi-journal-text"></i></div>
                <div>
                    <div class="kpi-value">{{ $total }}</div>
                    <div class="kpi-label">Total Exams</div>
                </div>
            </div>
            <div class="kpi-trend"><i class="bi bi-collection me-1"></i>All time</div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="kpi-card kpi-cyan">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="bi bi-calendar-event-fill"></i></div>
                <div>
                    <div class="kpi-value">{{ $upcoming }}</div>
                    <div class="kpi-label">Upcoming</div>
                </div>
            </div>
            <div class="kpi-trend"><i class="bi bi-clock me-1"></i>Scheduled</div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="kpi-card {{ $pending > 0 ? 'kpi-red' : 'kpi-blue' }}">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="bi bi-pencil-square"></i></div>
                <div>
                    <div class="kpi-value">{{ $pending }}</div>
                    <div class="kpi-label">Pending Entry</div>
                </div>
            </div>
            <div class="kpi-trend {{ $pending > 0 ? 'up' : '' }}">
                @if($pending > 0)<i class="bi bi-exclamation-circle me-1"></i>Needs entry
                @else<i class="bi bi-check me-1"></i>Up to date@endif
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="kpi-card kpi-green">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="bi bi-check2-all"></i></div>
                <div>
                    <div class="kpi-value">{{ $withResults }}</div>
                    <div class="kpi-label">Results Entered</div>
                </div>
            </div>
            <div class="kpi-trend up"><i class="bi bi-arrow-up me-1"></i>Completed</div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="kpi-card {{ $completionPct >= 75 ? 'kpi-green' : ($completionPct >= 50 ? 'kpi-amber' : 'kpi-red') }}">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="bi bi-pie-chart-fill"></i></div>
                <div>
                    <div class="kpi-value">{{ $completionPct }}%</div>
                    <div class="kpi-label">Completion</div>
                </div>
            </div>
            <div class="kpi-trend {{ $completionPct >= 75 ? 'up' : 'down' }}">
                <i class="bi bi-arrow-{{ $completionPct >= 75 ? 'up' : 'down' }} me-1"></i>Result entry rate
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="kpi-card kpi-purple">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="bi bi-globe2"></i></div>
                <div>
                    <div class="kpi-value">{{ $published }}</div>
                    <div class="kpi-label">Published</div>
                </div>
            </div>
            <div class="kpi-trend"><i class="bi bi-megaphone me-1"></i>Visible to students</div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <a href="{{ route('exam-cell.anomalies.index') }}" class="text-decoration-none">
            <div class="kpi-card {{ ($anomalyCount ?? 0) > 0 ? 'kpi-red' : 'kpi-blue' }}">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon"><i class="bi bi-flag-fill"></i></div>
                    <div>
                        <div class="kpi-value">{{ $anomalyCount ?? 0 }}</div>
                        <div class="kpi-label">Open Anomalies</div>
                    </div>
                </div>
                <div class="kpi-trend {{ ($anomalyCount ?? 0) > 0 ? 'up' : '' }}">
                    @if(($anomalyCount ?? 0) > 0)<i class="bi bi-exclamation-triangle me-1"></i>Requires action
                    @else<i class="bi bi-check-all me-1"></i>None flagged@endif
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
            <div class="card-header bg-transparent fw-semibold"><i class="bi bi-lightning-charge-fill me-2 text-warning"></i>Quick Actions</div>
            <div class="card-body d-flex flex-column gap-2">
                <a href="{{ route('exam-cell.exams.create') }}" class="btn btn-sm btn-primary text-start">
                    <i class="bi bi-plus-circle me-2"></i>Schedule New Exam
                </a>
                <a href="{{ route('exam-cell.hall-tickets') }}" class="btn btn-sm btn-outline-cyan text-start">
                    <i class="bi bi-credit-card me-2"></i>Hall Tickets
                </a>
                <a href="{{ route('exam-cell.marks-appeals') }}" class="btn btn-sm btn-outline-warning text-start">
                    <i class="bi bi-chat-left-dots me-2"></i>Marks Appeals
                </a>
                <a href="{{ route('exam-cell.results') }}" class="btn btn-sm btn-outline-success text-start">
                    <i class="bi bi-pencil-square me-2"></i>Enter Results
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
