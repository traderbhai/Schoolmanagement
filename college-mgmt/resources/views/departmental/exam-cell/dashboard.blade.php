@extends('layouts.admin')
@section('title', 'Exam Cell - Dashboard')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 mb-1"><i class="bi bi-file-earmark-check me-2 text-primary"></i>Exam Cell Dashboard</h1>
        <p class="text-muted mb-0">Track exam scheduling, marks entry, publication readiness, appeals, and anomalies.</p>
    </div>
    <a href="{{ route('exam-cell.exams.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Schedule Exam</a>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="text-uppercase text-muted fw-semibold mb-1" style="font-size:.72rem;letter-spacing:.04em">Exam Cell Priority</div>
            <h5 class="fw-bold mb-1">{{ $priority['title'] }}</h5>
            <p class="text-muted mb-0">{{ $priority['body'] }}</p>
        </div>
        <a href="{{ $priority['route'] }}" class="btn btn-sm {{ $priority['level'] === 'danger' ? 'btn-danger' : ($priority['level'] === 'warning' ? 'btn-warning' : 'btn-primary') }}">
            <i class="bi bi-arrow-right-circle me-1"></i>{{ $priority['action'] }}
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-2">
        <x-ui.kpi-card tone="blue" icon="bi-journal-text" :value="$total" label="Total Exams" trend="All time" trend-icon="bi-collection" />
    </div>
    <div class="col-sm-6 col-lg-2">
        <x-ui.kpi-card tone="cyan" icon="bi-calendar-event-fill" :value="$upcoming" label="Upcoming" trend="Scheduled" trend-icon="bi-clock" />
    </div>
    <div class="col-sm-6 col-lg-2">
        <x-ui.kpi-card
            :tone="$pending > 0 ? 'red' : 'blue'"
            icon="bi-pencil-square"
            :value="$pending"
            label="Pending Entry"
            :trend="$pending > 0 ? 'Needs entry' : 'Up to date'"
            :trend-icon="$pending > 0 ? 'bi-exclamation-circle' : 'bi-check'"
            :trend-tone="$pending > 0 ? 'up' : null"
        />
    </div>
    <div class="col-sm-6 col-lg-2">
        <x-ui.kpi-card
            :tone="($pendingAppeals ?? 0) > 0 ? 'amber' : 'green'"
            icon="bi-envelope-exclamation"
            :value="$pendingAppeals ?? 0"
            label="Open Appeals"
            :trend="($pendingAppeals ?? 0) > 0 ? 'Review queue' : 'None pending'"
            :trend-icon="($pendingAppeals ?? 0) > 0 ? 'bi-exclamation-circle' : 'bi-check'"
            :trend-tone="($pendingAppeals ?? 0) > 0 ? 'up' : null"
        />
    </div>
    <div class="col-sm-6 col-lg-2">
        <x-ui.kpi-card
            :href="route('exam-cell.anomalies.index')"
            :tone="($anomalyCount ?? 0) > 0 ? 'red' : 'blue'"
            icon="bi-flag-fill"
            :value="$anomalyCount ?? 0"
            label="Open Anomalies"
            :trend="($anomalyCount ?? 0) > 0 ? 'Requires action' : 'None flagged'"
            :trend-icon="($anomalyCount ?? 0) > 0 ? 'bi-exclamation-triangle' : 'bi-check-all'"
            :trend-tone="($anomalyCount ?? 0) > 0 ? 'up' : null"
        />
    </div>
    <div class="col-sm-6 col-lg-2">
        <x-ui.kpi-card
            :tone="$completionPct >= 75 ? 'green' : ($completionPct >= 50 ? 'amber' : 'red')"
            icon="bi-pie-chart-fill"
            :value="$completionPct . '%'"
            label="Completion"
            trend="Result entry rate"
            :trend-icon="$completionPct >= 75 ? 'bi-arrow-up' : 'bi-arrow-down'"
            :trend-tone="$completionPct >= 75 ? 'up' : 'down'"
        />
    </div>
</div>

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
                                <td class="small text-muted">{{ $exam->subject?->name ?? '-' }}</td>
                                <td class="small text-muted">{{ $exam->program?->code ?? $exam->program?->name ?? '-' }}</td>
                                <td class="small">{{ $exam->exam_date ? $exam->exam_date->format('d M Y') : '-' }}</td>
                                <td><span class="badge bg-secondary">{{ $exam->result_count }}</span></td>
                                <td class="small">{{ $exam->avg_marks ?? '-' }}</td>
                                <td>
                                    @if($exam->pass_pct !== null)
                                        <span class="badge bg-{{ $exam->pass_pct >= 75 ? 'success' : ($exam->pass_pct >= 50 ? 'warning' : 'danger') }}">{{ $exam->pass_pct }}%</span>
                                    @else
                                        <span class="text-muted small">-</span>
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
                            @if($exam->program) | {{ $exam->program->code ?? $exam->program->name }} @endif
                        </div>
                        @if($exam->subject)<div class="text-muted" style="font-size:.72rem"><i class="bi bi-book me-1"></i>{{ $exam->subject->name }}</div>@endif
                    </li>
                @empty
                    <li class="list-group-item text-center text-muted py-3">No upcoming exams.</li>
                @endforelse
                </ul>
            </div>
        </div>

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
