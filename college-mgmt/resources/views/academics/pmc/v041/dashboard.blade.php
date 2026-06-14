@extends('layouts.admin')
@section('title', 'PMC Timetable OS')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">PMC Timetable OS</h1>
            <div class="small text-muted">Student-course allocation, sections/groups, faculty load, locked slots, constraint generation, approval/freeze, substitution, and reports. Scope: {{ $scopeLabel }}</div>
        </div>
        @include('academics.pmc.v041.partials.nav')
    </div>

    <div class="row g-2 mb-3">
        @foreach([
            ['Allocation Batches', $kpis['allocation_batches'], route('academics.pmc.course-allocation.index')],
            ['Student Allocations', $kpis['student_allocations'], route('academics.pmc.student-course-baskets.index')],
            ['Course Groups', $kpis['course_groups'], route('academics.pmc.course-groups.index')],
            ['Faculty Assignments', $kpis['faculty_assignments'], route('academics.pmc.section-faculty-allocation.index')],
            ['Locked Slots', $kpis['locked_slots'], route('academics.pmc.locked-slots.index')],
            ['Hard Conflicts', $kpis['hard_conflicts'], route('academics.pmc.timetable-planner.index', ['severity' => 'hard'])],
            ['Soft Warnings', $kpis['soft_warnings'], route('academics.pmc.timetable-quality.index', ['severity' => 'soft'])],
            ['Quality Score', $kpis['quality_score'] . '%', route('academics.pmc.timetable-quality.index')],
        ] as [$label, $value, $url])
            <div class="col-6 col-md-3 col-xl">
                <a href="{{ $url }}" class="card h-100 shadow-sm text-decoration-none">
                    <div class="card-body py-2">
                        <div class="small text-muted">{{ $label }}</div>
                        <div class="h4 mb-0">{{ $value }}</div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-xl-4">
            <div class="card shadow-sm h-100">
                <div class="card-header py-2 fw-semibold">Input Readiness</div>
                <div class="list-group list-group-flush">
                    @foreach($readiness as $item)
                        <div class="list-group-item py-2 d-flex justify-content-between gap-2">
                            <span>{{ $item['label'] }}</span>
                            <span class="badge text-bg-{{ $item['ready'] ? 'success' : 'warning' }}">{{ $item['ready'] ? 'ready' : 'blocked' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card shadow-sm h-100">
                <div class="card-header py-2 d-flex justify-content-between">
                    <span class="fw-semibold">Latest Generation</span>
                    <a class="small" href="{{ route('academics.pmc.timetable-generator.index') }}">Open</a>
                </div>
                <div class="card-body">
                    @if($latestRun)
                        <div class="fw-semibold">{{ $latestRun->title }}</div>
                        <div class="small text-muted">{{ $latestRun->strategy }} | {{ $latestRun->status }}</div>
                        <div class="row g-2 mt-2">
                            <div class="col"><div class="border rounded p-2"><div class="small text-muted">Scheduled</div><div class="fw-semibold">{{ $latestRun->scheduled_count }}</div></div></div>
                            <div class="col"><div class="border rounded p-2"><div class="small text-muted">Unscheduled</div><div class="fw-semibold">{{ $latestRun->unscheduled_count }}</div></div></div>
                            <div class="col"><div class="border rounded p-2"><div class="small text-muted">Score</div><div class="fw-semibold">{{ $latestRun->quality_score }}%</div></div></div>
                        </div>
                    @else
                        <div class="text-muted">No generation run yet.</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card shadow-sm h-100">
                <div class="card-header py-2 fw-semibold">Notification Log</div>
                <div class="list-group list-group-flush">
                    @forelse($notifications as $notification)
                        <a class="list-group-item list-group-item-action py-2" href="{{ route('academics.pmc.timetable-reports.index', ['notification_type' => $notification->notification_type]) }}">
                            <div class="fw-semibold">{{ $notification->title }}</div>
                            <div class="small text-muted">{{ $notification->notification_type }} | {{ $notification->recipient_type }} | {{ $notification->status }}</div>
                        </a>
                    @empty
                        <div class="list-group-item text-muted">No notifications logged.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mt-3">
        <div class="card-header py-2 d-flex justify-content-between">
            <span class="fw-semibold">Constraint Board</span>
            <a href="{{ route('academics.pmc.timetable-planner.index') }}" class="small">Planner</a>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Constraint</th><th>Severity</th><th>Affected</th><th>Recommended Fix</th></tr></thead>
                <tbody>
                    @forelse($constraints as $constraint)
                        <tr>
                            <td><div class="fw-semibold">{{ $constraint->title }}</div><div class="small text-muted">{{ $constraint->description }}</div></td>
                            <td><span class="badge text-bg-{{ $constraint->severity === 'hard' ? 'danger' : 'warning' }}">{{ $constraint->severity }}</span></td>
                            <td>{{ $constraint->affected_type }} #{{ $constraint->affected_key }}</td>
                            <td>{{ $constraint->recommended_fix }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted">No constraints found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
