@extends('layouts.student')
@section('title', 'Dashboard')

@section('content')
<div class="container-fluid py-3 px-3 px-md-4">

    {{-- Low Attendance Banner --}}
    @if(count($lowAttendanceSubjects) > 0)
    <div class="alert alert-danger d-flex align-items-start gap-2 mb-3 py-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5 mt-1 flex-shrink-0"></i>
        <div>
            <strong>Low Attendance Alert</strong> - Below 75% in
            @foreach($lowAttendanceSubjects as $i => $sub)
                <strong>{{ $sub['subject'] }}</strong> ({{ $sub['pct'] }}%){{ $i < count($lowAttendanceSubjects)-1 ? ', ' : '.' }}
            @endforeach
            <a href="{{ route('student.attendance') }}" class="alert-link ms-1">View details -></a>
        </div>
    </div>
    @endif

    {{-- Fee Outstanding Banner --}}
    @if($feeOutstanding > 0)
    <div class="alert alert-warning d-flex align-items-start gap-2 mb-3 py-2">
        <i class="bi bi-credit-card-fill fs-5 mt-1 flex-shrink-0"></i>
        <div>
            <strong>Fee Outstanding: Rs. {{ number_format($feeOutstanding, 0) }}</strong>
            <a href="{{ route('student.fees') }}" class="alert-link ms-2">View details -></a>
        </div>
    </div>
    @endif

    {{-- Today's Priority --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between">
                <div>
                    <div class="text-muted small text-uppercase fw-semibold mb-1">Today's Priority</div>
                    @if(count($lowAttendanceSubjects) > 0)
                        <h5 class="fw-bold mb-1">Recover attendance in {{ count($lowAttendanceSubjects) }} subject{{ count($lowAttendanceSubjects) === 1 ? '' : 's' }}</h5>
                        <p class="text-muted mb-0">You are below the 75% requirement. Check the subject-wise sessions and plan make-up action with your mentor or faculty.</p>
                    @elseif($feeOutstanding > 0)
                        <h5 class="fw-bold mb-1">Clear pending fee balance</h5>
                        <p class="text-muted mb-0">Review your outstanding demand and submit payment proof early to avoid blocked exam registration or late penalties.</p>
                    @elseif($pendingAssignmentCount > 0)
                        <h5 class="fw-bold mb-1">Submit {{ $pendingAssignmentCount }} upcoming assignment{{ $pendingAssignmentCount === 1 ? '' : 's' }}</h5>
                        <p class="text-muted mb-0">You have coursework due in the next 7 days. Open assignments and submit before the due date.</p>
                    @elseif(count($todayClasses) > 0)
                        <h5 class="fw-bold mb-1">Attend today's scheduled classes</h5>
                        <p class="text-muted mb-0">{{ count($todayClasses) }} class{{ count($todayClasses) === 1 ? '' : 'es' }} scheduled today. Check rooms and faculty before you leave.</p>
                    @else
                        <h5 class="fw-bold mb-1">No urgent academic action due today</h5>
                        <p class="text-muted mb-0">Use the time to review course materials, check notices, or plan upcoming academic events.</p>
                    @endif
                </div>
                <div class="d-grid gap-2" style="min-width: 210px;">
                    @if(count($lowAttendanceSubjects) > 0)
                        <a href="{{ route('student.attendance') }}" class="btn btn-danger btn-sm"><i class="bi bi-clipboard2-check me-1"></i> Review Attendance</a>
                    @elseif($feeOutstanding > 0)
                        <a href="{{ route('student.fees') }}" class="btn btn-warning btn-sm"><i class="bi bi-credit-card me-1"></i> Review Fees</a>
                    @elseif($pendingAssignmentCount > 0)
                        <a href="{{ route('student.assignments.index', ['filter' => 'pending_next_7']) }}" class="btn btn-primary btn-sm"><i class="bi bi-pencil-square me-1"></i> Open Assignments</a>
                    @elseif(count($todayClasses) > 0)
                        <a href="{{ route('student.timetable') }}" class="btn btn-primary btn-sm"><i class="bi bi-calendar-week me-1"></i> View Timetable</a>
                    @else
                        <a href="{{ route('student.courses.index') }}" class="btn btn-primary btn-sm"><i class="bi bi-book me-1"></i> Review Courses</a>
                    @endif
                    <a href="{{ route('student.grievances.create') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-life-preserver me-1"></i> Need Help?</a>
                </div>
            </div>
        </div>
    </div>

    {{-- KPI Row --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3 text-center">
                    <div class="fs-2 fw-bold {{ is_null($attendanceOverall) ? 'text-muted' : ($attendanceOverall < 75 ? 'text-danger' : 'text-success') }}">
                        {{ is_null($attendanceOverall) ? '-' : $attendanceOverall . '%' }}
                    </div>
                    <div class="text-muted small mt-1">Attendance</div>
                    @if(!is_null($attendanceOverall))
                    <div class="progress mt-2" style="height:4px">
                        <div class="progress-bar {{ $attendanceOverall < 75 ? 'bg-danger' : 'bg-success' }}" style="width:{{ min(100,$attendanceOverall) }}%"></div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3 text-center">
                    <div class="fs-2 fw-bold text-primary">{{ $sgpa ?? '-' }}</div>
                    <div class="text-muted small mt-1">Current SGPA</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3 text-center">
                    <div class="fs-2 fw-bold text-info">{{ $cgpa ?? '-' }}</div>
                    <div class="text-muted small mt-1">Overall CGPA</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3 text-center">
                    <div class="fs-2 fw-bold {{ $feeOutstanding > 0 ? 'text-danger' : 'text-success' }}">
                        Rs. {{ number_format($feeOutstanding, 0) }}
                    </div>
                    <div class="text-muted small mt-1">Fee Outstanding</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        {{-- LEFT COLUMN --}}
        <div class="col-lg-8">

            {{-- Today's Classes --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <span class="fw-semibold"><i class="bi bi-calendar-check me-2"></i>Today's Classes - {{ now()->format('l, d M') }}</span>
                    <a href="{{ route('student.timetable') }}" class="btn btn-sm btn-link p-0">Full timetable -></a>
                </div>
                <div class="card-body p-0">
                    @if(count($todayClasses) > 0)
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 align-middle">
                            <thead class="table-light">
                                <tr><th>Time</th><th>Subject</th><th>Faculty</th><th>Room</th></tr>
                            </thead>
                            <tbody>
                                @foreach($slots as $slot)
                                    @if(isset($todayClasses[$slot->id]) && $todayClasses[$slot->id])
                                    @php $entry = $todayClasses[$slot->id]; @endphp
                                    <tr>
                                        <td class="text-muted small">{{ $slot->start_time ?? $slot->name }}</td>
                                        <td class="fw-semibold small">{{ $entry->subject->name ?? '-' }}</td>
                                        <td class="text-muted small">{{ $entry->teacher?->user?->name ?? '-' }}</td>
                                        <td class="text-muted small">{{ $entry->classroom?->name ?? $entry->classroom?->room_number ?? '-' }}</td>
                                    </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <p class="text-muted p-3 mb-0"><i class="bi bi-sun me-2"></i>No classes scheduled for today.</p>
                    @endif
                </div>
            </div>

            {{-- Upcoming Deadlines --}}
            @if($upcomingAssignments->isNotEmpty() || $upcomingExams->isNotEmpty())
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <span class="fw-semibold"><i class="bi bi-clock-history me-2 text-danger"></i>Upcoming Deadlines - Next 7 Days</span>
                    <a href="{{ route('student.assignments.index') }}" class="btn btn-sm btn-link p-0">All assignments -></a>
                </div>
                <div class="card-body p-0">
                    @foreach($upcomingAssignments as $assignment)
                    @php $sub = $assignment->submissions->first(); @endphp
                    <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                        <div>
                            <span class="badge bg-primary-subtle text-primary me-2">Assignment</span>
                            <span class="small fw-semibold">{{ $assignment->title }}</span>
                            <span class="text-muted small ms-2">{{ $assignment->subject->name }}</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            @if($sub && in_array($sub->status, ['submitted','graded']))
                                <span class="badge bg-success">Done</span>
                            @else
                                <span class="text-danger small">{{ $assignment->due_at->diffForHumans() }}</span>
                                <a href="{{ route('student.assignments.show', $assignment) }}" class="btn btn-sm btn-outline-primary py-0 px-2">Submit</a>
                            @endif
                        </div>
                    </div>
                    @endforeach
                    @foreach($upcomingExams->take(3) as $exam)
                    <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                        <div>
                            <span class="badge bg-warning-subtle text-warning me-2">Exam</span>
                            <span class="small fw-semibold">{{ $exam->name ?? ($exam->subject->name ?? 'Exam') }}</span>
                        </div>
                        <span class="text-warning small">{{ \Carbon\Carbon::parse($exam->exam_date)->format('d M') }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Upcoming Academic Events --}}
            @if($upcomingEvents->isNotEmpty())
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <span class="fw-semibold"><i class="bi bi-calendar-event me-2"></i>Coming Up - Next 30 Days</span>
                    <a href="{{ route('student.calendar.index') }}" class="btn btn-sm btn-link p-0">Full calendar -></a>
                </div>
                <div class="card-body p-0">
                    @foreach($upcomingEvents as $event)
                    @php $typeColors = ['holiday'=>'danger','exam'=>'warning','event'=>'success','workshop'=>'info','seminar'=>'info','other'=>'secondary']; $c = $typeColors[$event->type] ?? 'secondary'; @endphp
                    <div class="d-flex align-items-center gap-3 px-3 py-2 border-bottom">
                        <div class="text-center" style="min-width:42px">
                            <div class="fw-bold text-{{ $c }}" style="font-size:1.1rem;line-height:1">{{ $event->start_date?->format('d') ?? '-' }}</div>
                            <div class="text-muted" style="font-size:.7rem">{{ $event->start_date?->format('M') ?? '' }}</div>
                        </div>
                        <div>
                            <div class="small fw-semibold">{{ $event->title }}</div>
                            <span class="badge bg-{{ $c }}-subtle text-{{ $c }}" style="font-size:.7rem">{{ ucfirst($event->type) }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Notices --}}
            @if($notices->isNotEmpty())
            <div class="card border-0 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <span class="fw-semibold"><i class="bi bi-megaphone me-2"></i>Latest Notices</span>
                    <a href="{{ route('student.notices') }}" class="btn btn-sm btn-link p-0">All notices -></a>
                </div>
                <div class="card-body p-0">
                    @foreach($notices as $notice)
                    <a href="{{ route('student.notices.show', $notice) }}" class="text-decoration-none">
                        <div class="px-3 py-2 border-bottom">
                            <div class="small fw-semibold text-dark">{{ Str::limit($notice->title, 60) }}</div>
                            <div class="text-muted" style="font-size:.75rem">{{ $notice->published_at?->diffForHumans() ?? $notice->created_at->diffForHumans() }}</div>
                        </div>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- RIGHT COLUMN --}}
        <div class="col-lg-4">

            {{-- Student Info Card --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div style="width:50px;height:50px;border-radius:50%;background:var(--clr-primary,#4f46e5);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.3rem;flex-shrink:0;">
                            <i class="bi bi-person-fill"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">{{ auth()->user()->name }}</div>
                            <div class="text-muted small">{{ $student->enrollment_number ?? 'Enrollment pending' }}</div>
                        </div>
                    </div>
                    <table class="table table-sm mb-0" style="font-size:.85rem">
                        <tr><td class="text-muted border-0 ps-0 py-1">Program</td><td class="border-0 py-1">{{ $student->program->name ?? '-' }}</td></tr>
                        <tr><td class="text-muted border-0 ps-0 py-1">Batch</td><td class="border-0 py-1">{{ $student->batch->name ?? '-' }}</td></tr>
                        <tr><td class="text-muted border-0 ps-0 py-1">Term</td><td class="border-0 py-1">{{ $currentSemester->name ?? ($student->current_term ?? '-') }}</td></tr>
                        @if($student->mentor)
                        <tr><td class="text-muted border-0 ps-0 py-1">Mentor</td><td class="border-0 py-1">{{ $student->mentor->name }}</td></tr>
                        @endif
                    </table>
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header py-2 fw-semibold"><i class="bi bi-lightning me-2"></i>Quick Actions</div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('student.courses.index') }}" class="btn btn-outline-primary btn-sm text-start">
                            <i class="bi bi-book me-2"></i>My Courses & Materials
                        </a>
                        <a href="{{ route('student.assignments.index') }}" class="btn btn-outline-primary btn-sm text-start">
                            <i class="bi bi-pencil-square me-2"></i>Assignments
                            @if($upcomingAssignments->isNotEmpty())
                            <span class="badge bg-danger float-end">{{ $upcomingAssignments->count() }}</span>
                            @endif
                        </a>
                        <a href="{{ route('student.quizzes.index') }}" class="btn btn-outline-primary btn-sm text-start">
                            <i class="bi bi-patch-question me-2"></i>Quizzes
                        </a>
                        <a href="{{ route('student.leave.create') }}" class="btn btn-outline-secondary btn-sm text-start">
                            <i class="bi bi-person-dash me-2"></i>Apply for Leave
                            @if($pendingLeaves > 0)
                            <span class="badge bg-warning text-dark float-end">{{ $pendingLeaves }} pending</span>
                            @endif
                        </a>
                        <a href="{{ route('student.attendance') }}" class="btn btn-outline-secondary btn-sm text-start">
                            <i class="bi bi-check2-square me-2"></i>Check Attendance
                        </a>
                        <a href="{{ route('student.results') }}" class="btn btn-outline-secondary btn-sm text-start">
                            <i class="bi bi-award me-2"></i>My Results
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
