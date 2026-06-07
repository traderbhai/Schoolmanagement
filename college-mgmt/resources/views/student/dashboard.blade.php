@extends('layouts.student')
@section('title','My Dashboard')
@section('page-title','Student Dashboard')
@section('content')

{{-- Welcome Banner --}}
<div class="card border-0 mb-4 overflow-hidden" style="background:linear-gradient(135deg,#4338ca,#2563eb);min-height:110px">
    <div class="card-body d-flex align-items-center justify-content-between py-4">
        <div class="text-white">
            <div class="opacity-75 small fw-semibold text-uppercase letter-spacing-1 mb-1">
                <i class="bi bi-sun me-1"></i>Good day
            </div>
            <h3 class="fw-bold mb-1" style="font-size:1.5rem">{{ $student->user->name }}</h3>
            <div class="d-flex gap-3 flex-wrap" style="font-size:.85rem">
                <span class="opacity-85"><i class="bi bi-card-text me-1 opacity-75"></i>{{ $student->enrollment_number }}</span>
                <span class="opacity-85"><i class="bi bi-bookmark me-1 opacity-75"></i>Semester {{ $student->current_semester }}</span>
                @if($currentSemester)
                <span class="opacity-85"><i class="bi bi-calendar3 me-1 opacity-75"></i>{{ $currentSemester->name }}</span>
                @endif
            </div>
        </div>
        <div class="d-none d-md-flex align-items-center justify-content-center rounded-circle opacity-20"
             style="width:90px;height:90px;background:rgba(255,255,255,.2);flex-shrink:0">
            <i class="bi bi-mortarboard-fill text-white" style="font-size:3rem"></i>
        </div>
    </div>
</div>

{{-- KPI Cards --}}
<div class="row g-3 mb-4">
    {{-- Attendance KPI --}}
    <div class="col-md-3">
        <div class="kpi-card {{ $attendanceOverall !== null ? ($attendanceOverall >= 75 ? 'kpi-green' : 'kpi-red') : 'kpi-green' }}">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="kpi-label">Attendance</div>
                    <div class="kpi-value">
                        @if($attendanceOverall !== null)
                            {{ $attendanceOverall }}%
                        @else
                            <span style="font-size:1.2rem">—</span>
                        @endif
                    </div>
                    <div class="kpi-trend">
                        <i class="bi bi-bar-chart-line me-1"></i>
                        <a href="{{ route('student.attendance') }}" class="text-white opacity-75 text-decoration-none" style="font-size:.78rem">See breakdown</a>
                    </div>
                </div>
                <div class="kpi-icon"><i class="bi bi-person-check-fill"></i></div>
            </div>
        </div>
    </div>

    {{-- SGPA KPI --}}
    <div class="col-md-3">
        <div class="kpi-card kpi-blue">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="kpi-label">Current SGPA</div>
                    <div class="kpi-value">
                        @if($sgpa !== null)
                            {{ number_format($sgpa, 2) }}
                        @else
                            <span style="font-size:1.2rem">—</span>
                        @endif
                    </div>
                    <div class="kpi-trend">
                        <i class="bi bi-graph-up me-1"></i>
                        <a href="{{ route('student.results') }}" class="text-white opacity-75 text-decoration-none" style="font-size:.78rem">See grades</a>
                    </div>
                </div>
                <div class="kpi-icon"><i class="bi bi-mortarboard-fill"></i></div>
            </div>
        </div>
    </div>

    {{-- CGPA KPI --}}
    <div class="col-md-3">
        <div class="kpi-card kpi-blue">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="kpi-label">CGPA (Overall)</div>
                    <div class="kpi-value">
                        @if(isset($cgpa) && $cgpa !== null)
                            {{ number_format($cgpa, 2) }}
                        @else
                            <span style="font-size:1.2rem">—</span>
                        @endif
                    </div>
                    <div class="kpi-trend">
                        <i class="bi bi-award me-1"></i>
                        <span class="text-white opacity-75" style="font-size:.78rem">Cumulative GPA</span>
                    </div>
                </div>
                <div class="kpi-icon"><i class="bi bi-award-fill"></i></div>
            </div>
        </div>
    </div>

    {{-- Fee Balance KPI --}}
    <div class="col-md-3">
        <div class="kpi-card {{ $balanceDue > 0 ? 'kpi-amber' : 'kpi-green' }}">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="kpi-label">Fee Balance Due</div>
                    <div class="kpi-value">₹{{ number_format($balanceDue) }}</div>
                    <div class="kpi-trend">
                        <i class="bi bi-receipt me-1"></i>
                        <a href="{{ route('student.fees') }}" class="text-white opacity-75 text-decoration-none" style="font-size:.78rem">See fee details</a>
                    </div>
                </div>
                <div class="kpi-icon"><i class="bi bi-cash-coin"></i></div>
            </div>
        </div>
    </div>
</div>

{{-- Main Content Row --}}
<div class="row g-3">
    {{-- Timetable --}}
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-grid-3x3-gap text-primary"></i>
                <span class="fw-semibold">My Weekly Timetable</span>
                @if($currentSemester)
                <span class="badge bg-primary bg-opacity-10 text-primary ms-1">{{ $currentSemester->name }}</span>
                @endif
            </div>
            <div class="card-body p-0">
                @if(count($grid))
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
                                    {{ $slot->start_time }} – {{ $slot->end_time }}
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
                                        <div class="subj fw-semibold" style="font-size:.78rem;line-height:1.2">{{ $grid[$day][$slot->id]->subject->name }}</div>
                                        <div class="tchr text-muted mt-1" style="font-size:.68rem">{{ $grid[$day][$slot->id]->teacher->user->name }}</div>
                                        <span class="room-tag badge bg-light text-secondary border mt-1" style="font-size:.62rem">{{ $grid[$day][$slot->id]->classroom->room_number }}</span>
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
                    <div class="empty-icon"><i class="bi bi-grid text-muted"></i></div>
                    <h6 class="mt-3 text-muted">No Timetable Available</h6>
                    <p class="text-muted small mb-0">Timetable for this semester hasn't been configured yet.</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Notices --}}
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-megaphone text-warning"></i>
                <span class="fw-semibold">Notices</span>
                @if($notices->count() > 0)
                <span class="badge bg-warning text-dark ms-auto">{{ $notices->count() }}</span>
                @endif
            </div>
            @forelse($notices as $n)
            <div class="list-group list-group-flush">
                <div class="list-group-item py-3 px-3">
                    <div class="d-flex align-items-start gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 mt-1"
                             style="width:28px;height:28px;background:rgba(99,102,241,.1)">
                            <i class="bi bi-bell text-primary" style="font-size:.75rem"></i>
                        </div>
                        <div>
                            <div class="fw-semibold" style="font-size:.85rem;line-height:1.3">{{ $n->title }}</div>
                            <div class="text-muted mt-1" style="font-size:.72rem">
                                <i class="bi bi-calendar3 me-1"></i>{{ $n->publish_date->format('d M Y') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="empty-state py-4">
                <div class="empty-icon"><i class="bi bi-bell-slash text-muted" style="font-size:2rem"></i></div>
                <p class="text-muted small mb-0 mt-2">No notices at this time.</p>
            </div>
            @endforelse
        </div>
    </div>
</div>

{{-- Upcoming Exams --}}
@if(isset($upcomingExams) && count($upcomingExams) > 0)
<div class="row g-3 mt-1">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-calendar-event text-danger"></i>
                <span class="fw-semibold">Upcoming Exams</span>
                <span class="badge bg-danger bg-opacity-10 text-danger ms-1">{{ count($upcomingExams) }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Exam</th><th>Subject</th><th>Date</th><th>Type</th></tr>
                        </thead>
                        <tbody>
                        @foreach($upcomingExams as $exam)
                            <tr>
                                <td class="fw-semibold small">{{ $exam->name }}</td>
                                <td class="small text-muted">{{ $exam->subject?->name ?? '—' }}</td>
                                <td class="small">
                                    <span class="badge bg-warning text-dark">
                                        <i class="bi bi-calendar3 me-1"></i>{{ $exam->exam_date->format('d M Y') }}
                                    </span>
                                </td>
                                <td><span class="badge bg-secondary">{{ ucfirst($exam->type ?? 'exam') }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

@endsection
