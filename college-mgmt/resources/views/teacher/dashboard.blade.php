@extends('layouts.teacher')
@section('title','Teacher Dashboard')
@section('page-title','Teacher Dashboard')
@section('content')

<div class="alert alert-info border-0 shadow-sm py-2 mb-3">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
        <div>
            <div class="fw-semibold">Teacher daily sequence</div>
            <div class="small text-muted">Use this dashboard to move from class readiness to attendance, assignments, materials, marks, and mentoring.</div>
        </div>
        <div class="d-flex flex-wrap gap-1">
            <span class="badge text-bg-light">1. Review timetable</span>
            <span class="badge text-bg-light">2. Mark attendance</span>
            <span class="badge text-bg-light">3. Grade submissions</span>
            <span class="badge text-bg-light">4. Upload materials</span>
            <span class="badge text-bg-light">5. Follow up mentees</span>
        </div>
    </div>
</div>

{{-- Teacher Info Banner --}}
<div class="card border-0 mb-4 overflow-hidden" style="background:linear-gradient(135deg,#0d9488,#0284c7);min-height:110px">
    <div class="card-body d-flex align-items-center justify-content-between py-4">
        <div class="text-white">
            <div class="opacity-75 small fw-semibold text-uppercase mb-1">
                <i class="bi bi-person-workspace me-1"></i>Faculty Portal
            </div>
            <h3 class="fw-bold mb-1" style="font-size:1.5rem">{{ $teacher?->user?->name ?? auth()->user()->name }}</h3>
            <div class="d-flex gap-3 flex-wrap" style="font-size:.85rem">
                <span class="opacity-85"><i class="bi bi-briefcase me-1 opacity-75"></i>{{ $teacher?->designation ?? 'Teacher profile pending' }}</span>
                <span class="opacity-85"><i class="bi bi-card-text me-1 opacity-75"></i>{{ $teacher?->employee_id ?? 'Not linked' }}</span>
                <span class="opacity-85"><i class="bi bi-clock me-1 opacity-75"></i>{{ $weeklyLoad }} periods/week</span>
            </div>
        </div>
        <div class="d-none d-md-flex align-items-center justify-content-center rounded-circle"
             style="width:90px;height:90px;background:rgba(255,255,255,.15);flex-shrink:0">
            <i class="bi bi-person-workspace text-white" style="font-size:2.8rem"></i>
        </div>
    </div>
</div>

{{-- Teaching Priority --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3 p-md-4">
        <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between">
            <div>
                <div class="text-muted small text-uppercase fw-semibold mb-1">Today's Teaching Priority</div>
                @if($pendingGrading > 0)
                    <h5 class="fw-bold mb-1">Grade {{ $pendingGrading }} pending submission{{ $pendingGrading === 1 ? '' : 's' }}</h5>
                    <p class="text-muted mb-0">Students are waiting for feedback. Review submitted assignments before the next class or deadline cycle.</p>
                @elseif(count($todayClasses) > 0)
                    <h5 class="fw-bold mb-1">Mark attendance for today's classes</h5>
                    <p class="text-muted mb-0">{{ count($todayClasses) }} class{{ count($todayClasses) === 1 ? '' : 'es' }} scheduled today. Open attendance when the class is ready.</p>
                @elseif($activeAssignments > 0)
                    <h5 class="fw-bold mb-1">Monitor active assignments</h5>
                    <p class="text-muted mb-0">{{ $activeAssignments }} published assignment{{ $activeAssignments === 1 ? '' : 's' }} still {{ $activeAssignments === 1 ? 'has' : 'have' }} an upcoming due date.</p>
                @else
                    <h5 class="fw-bold mb-1">No urgent teaching action due today</h5>
                    <p class="text-muted mb-0">Use this time to upload materials, create assignments, or review your timetable for upcoming classes.</p>
                @endif
                <div class="d-flex flex-wrap gap-1 mt-2">
                    @if($pendingGrading > 0)
                        <span class="badge text-bg-primary">Owner: You</span>
                        <span class="badge text-bg-light">Source: Submitted assignment work</span>
                    @elseif(count($todayClasses) > 0)
                        <span class="badge text-bg-warning">Owner: You</span>
                        <span class="badge text-bg-light">Source: Published timetable</span>
                    @elseif($activeAssignments > 0)
                        <span class="badge text-bg-primary">Owner: You</span>
                        <span class="badge text-bg-light">Source: Published assignments</span>
                    @else
                        <span class="badge text-bg-light">Owner: You + Program office</span>
                        <span class="badge text-bg-light">Source: Your teaching records</span>
                    @endif
                </div>
            </div>
            <div class="d-grid gap-2" style="min-width: 220px;">
                @if($pendingGrading > 0)
                    <a href="{{ route('teacher.assignments.index') }}" class="btn btn-primary btn-sm"><i class="bi bi-check2-square me-1"></i> Review Submissions</a>
                @elseif(count($todayClasses) > 0)
                    <a href="{{ route('teacher.attendance.mark') }}" class="btn btn-warning btn-sm"><i class="bi bi-person-check me-1"></i> Mark Attendance</a>
                @elseif($activeAssignments > 0)
                    <a href="{{ route('teacher.assignments.index') }}" class="btn btn-primary btn-sm"><i class="bi bi-pencil-square me-1"></i> View Assignments</a>
                @else
                    <a href="{{ route('teacher.materials.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-upload me-1"></i> Upload Material</a>
                @endif
                <a href="{{ route('teacher.timetable.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-calendar-week me-1"></i> View Timetable</a>
            </div>
        </div>
    </div>
</div>

{{-- KPI Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <a href="{{ route('teacher.timetable.index') }}" class="kpi-card kpi-cyan d-block text-decoration-none text-white" aria-label="Open weekly load timetable">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="kpi-label">Weekly Load</div>
                    <div class="kpi-value">{{ $weeklyLoad }}<span style="font-size:1rem;opacity:.7"> periods</span></div>
                    <div class="kpi-trend"><i class="bi bi-calendar-week me-1"></i>Per week</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-grid-3x3-gap-fill"></i></div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="{{ route('teacher.timetable.index') }}" class="kpi-card kpi-blue d-block text-decoration-none text-white" aria-label="Open today's classes timetable">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="kpi-label">My Classes Today</div>
                    <div class="kpi-value">
                        {{ count($todayClasses) }}<span style="font-size:1rem;opacity:.7"> classes</span>
                    </div>
                    <div class="kpi-trend"><i class="bi bi-calendar-day me-1"></i>{{ now()->format('l') }}</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-people-fill"></i></div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="{{ route('teacher.attendance.mark') }}" class="kpi-card kpi-amber d-block text-decoration-none text-white" aria-label="Open attendance marking">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="kpi-label">Mark Attendance</div>
                    <div class="kpi-value" style="font-size:1.3rem">Today's Classes</div>
                    <div class="kpi-trend">
                        <span class="text-white opacity-75" style="font-size:.78rem">
                            <i class="bi bi-arrow-right-circle me-1"></i>Go mark now
                        </span>
                    </div>
                </div>
                <div class="kpi-icon"><i class="bi bi-check2-square"></i></div>
            </div>
        </a>
    </div>
</div>

{{-- Weekly Schedule --}}
<div class="card" style="box-shadow:var(--shadow-sm)">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-grid-3x3-gap text-primary"></i>
        <span class="fw-semibold">My Weekly Schedule</span>
    </div>
    <div class="card-body p-0">
        @php($hasScheduleEntries = collect($grid)->flatten(1)->filter()->isNotEmpty())
        @if($hasScheduleEntries)
        <div class="table-responsive">
            <table class="table table-bordered timetable-grid mb-0">
                <thead>
                    <tr>
                        <th style="min-width:80px">Slot</th>
                        @foreach(['Mon','Tue','Wed','Thu','Fri','Sat'] as $d)
                        <th class="day-header text-center">{{ $d }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                @foreach($slots as $slot)
                    @if($slot->is_break)
                    <tr class="break-row">
                        <td class="text-center fw-semibold small">{{ $slot->name }}</td>
                        <td colspan="6" class="text-center">
                            <i class="bi bi-cup-hot me-1 opacity-50"></i>
                            {{ $slot->start_time }} - {{ $slot->end_time }}
                        </td>
                    </tr>
                    @else
                    <tr>
                        <td class="text-center">
                            <div class="fw-semibold small">{{ $slot->name }}</div>
                            <div class="text-muted" style="font-size:.68rem">{{ $slot->start_time }}-{{ $slot->end_time }}</div>
                        </td>
                        @for($day=1;$day<=6;$day++)
                        <td class="p-1">
                            @if(isset($grid[$day][$slot->id]))
                            <div class="timetable-cell">
                                <div class="subj fw-semibold" style="font-size:.78rem;line-height:1.2">{{ $grid[$day][$slot->id]->subject?->name ?? 'Subject not assigned' }}</div>
                                <div class="tchr text-muted mt-1" style="font-size:.68rem">{{ $grid[$day][$slot->id]->course?->code ?? 'Course not assigned' }}</div>
                                <span class="room-tag badge bg-light text-secondary border mt-1" style="font-size:.62rem">{{ $grid[$day][$slot->id]->classroom?->room_number ?? $grid[$day][$slot->id]->classroom?->name ?? 'Room not assigned' }}</span>
                            </div>
                            @endif
                        </td>
                        @endfor
                    </tr>
                    @endif
                @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="empty-state py-5">
            <div class="empty-icon"><i class="bi bi-grid text-muted" style="font-size:3rem"></i></div>
            <h6 class="mt-3 text-muted">No published timetable for your profile yet</h6>
            <p class="text-muted small mb-2">Only published classes assigned to your teacher profile appear here. If you expected classes, check that your teacher profile and timetable allocation are published.</p>
            <a href="{{ route('teacher.profile') }}" class="btn btn-sm btn-outline-primary">Review teacher profile</a>
        </div>
        @endif
    </div>
</div>

@endsection
