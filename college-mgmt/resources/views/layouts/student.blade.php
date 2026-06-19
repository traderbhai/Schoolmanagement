<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>@yield('title', 'EduManage - Student')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/css/app.css" rel="stylesheet">
    @stack('styles')
</head>
<body>
@php
    $studentNavProfile = auth()->user()?->student;
    $studentNavHasIssuedTranscript = $studentNavProfile
        ? \App\Models\AcademicTranscript::where('student_id', $studentNavProfile->id)
            ->where('status', 'issued')
            ->whereNotNull('semester_data')
            ->exists()
        : false;
@endphp

{{-- ===== DESKTOP SIDEBAR ===== --}}
<div class="sidebar sidebar-desktop">
    <a class="sidebar-brand" href="{{ route('student.dashboard') }}">
        <span class="brand-icon"><i class="bi bi-mortarboard-fill"></i></span>
        <span>
            <div class="brand-text">EduManage</div>
            <div class="brand-sub">Student Portal</div>
        </span>
    </a>

    <div class="mt-2 pb-4 flex-grow-1">
        <div class="section-label">Main</div>
        <a href="{{ route('student.dashboard') }}" class="nav-link @if(request()->routeIs('student.dashboard')) active @endif">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="sidebar-divider"></div>

        <div class="section-label">Academics</div>
        <a href="{{ route('student.timetable') }}" class="nav-link @if(request()->routeIs('student.timetable')) active @endif">
            <i class="bi bi-calendar3 me-2"></i>My Timetable
        </a>
        <a href="{{ route('student.attendance') }}" class="nav-link @if(request()->routeIs('student.attendance*')) active @endif">
            <i class="bi bi-check2-square"></i> Attendance
        </a>
        <a href="{{ route('student.results') }}" class="nav-link @if(request()->routeIs('student.results')) active @endif">
            <i class="bi bi-award"></i> Results & Grades
        </a>
        <a href="{{ route('student.admit-cards.index') }}" class="nav-link @if(request()->routeIs('student.admit-cards.*')) active @endif">
            <i class="bi bi-card-checklist me-2"></i>Admit Cards
        </a>
        <a href="{{ route('student.subjects.index') }}" class="nav-link @if(request()->routeIs('student.subjects.*')) active @endif">
            <i class="bi bi-journal-text me-2"></i>Subject Registration
        </a>
        <a href="{{ route('student.calendar.index') }}" class="nav-link @if(request()->routeIs('student.calendar.*')) active @endif">
            <i class="bi bi-calendar-event me-2"></i>Academic Calendar
        </a>
        <a href="{{ route('student.leave.index') }}" class="nav-link @if(request()->routeIs('student.leave.*')) active @endif">
            <i class="bi bi-person-dash me-2"></i>Leave Applications
        </a>
        @if($studentNavHasIssuedTranscript)
            <a href="{{ route('student.transcript.download') }}" class="nav-link">
                <i class="bi bi-file-earmark-text me-2"></i>Official Transcript
            </a>
        @endif
        <a href="{{ route('student.exam-reg.index') }}" class="nav-link @if(request()->routeIs('student.exam-reg.*')) active @endif">
            <i class="bi bi-clipboard-check me-2"></i>Exam Registration
        </a>
        <a href="{{ route('student.appeals.index') }}" class="nav-link @if(request()->routeIs('student.appeals.*')) active @endif">
            <i class="bi bi-megaphone me-2"></i>Marks Appeals
        </a>

        <div class="sidebar-divider"></div>

        <div class="section-label">Course Content</div>
        <a href="{{ route('student.courses.index') }}" class="nav-link @if(request()->routeIs('student.courses.*')) active @endif">
            <i class="bi bi-book me-2"></i>My Courses
        </a>
        <a href="{{ route('student.assignments.index') }}" class="nav-link @if(request()->routeIs('student.assignments.*')) active @endif">
            <i class="bi bi-pencil-square me-2"></i>Assignments
        </a>
        <a href="{{ route('student.quizzes.index') }}" class="nav-link @if(request()->routeIs('student.quizzes.*')) active @endif">
            <i class="bi bi-patch-question me-2"></i>Quizzes
        </a>

        <div class="sidebar-divider"></div>

        <div class="section-label">Finance</div>
        <a href="{{ route('student.fees') }}" class="nav-link @if(request()->routeIs('student.fees')) active @endif">
            <i class="bi bi-cash-coin"></i> Fee Status
        </a>
        <a href="{{ route('student.fee-payment.index') }}" class="nav-link @if(request()->routeIs('student.fee-payment.*')) active @endif">
            <i class="bi bi-upload me-2"></i>Submit Payment
        </a>

        <div class="sidebar-divider"></div>

        <div class="section-label">Career</div>
        <a href="{{ route('student.placements') }}" class="nav-link @if(request()->routeIs('student.placements*')) active @endif">
            <i class="bi bi-briefcase"></i> Placements
        </a>
        <a href="{{ route('student.scholarships.index') }}" class="nav-link @if(request()->routeIs('student.scholarships.*')) active @endif">
            <i class="bi bi-award me-2"></i>Scholarships
        </a>
        <a href="{{ route('student.resume.index') }}" class="nav-link @if(request()->routeIs('student.resume.*')) active @endif">
            <i class="bi bi-person-vcard me-2"></i>My Resume
        </a>
        <a href="{{ route('student.career-events.index') }}" class="nav-link @if(request()->routeIs('student.career-events.*')) active @endif">
            <i class="bi bi-calendar-event me-2"></i>Career Events
        </a>
        <a href="{{ route('student.internships.index') }}" class="nav-link @if(request()->routeIs('student.internships.*')) active @endif">
            <i class="bi bi-building me-2"></i>My Internships
        </a>
        <a href="{{ route('student.alumni.index') }}" class="nav-link @if(request()->routeIs('student.alumni.*')) active @endif">
            <i class="bi bi-people me-2"></i>Alumni Network
        </a>

        <div class="sidebar-divider"></div>

        <div class="section-label">Support</div>
        <a href="{{ route('student.notices') }}" class="nav-link @if(request()->routeIs('student.notices*')) active @endif">
            <i class="bi bi-megaphone"></i> Notices
        </a>
        <a href="{{ route('student.library.index') }}" class="nav-link @if(request()->routeIs('student.library.*')) active @endif">
            <i class="bi bi-book-half me-2"></i>Library
        </a>
        <a href="{{ route('student.grievances.index') }}" class="nav-link @if(request()->routeIs('student.grievances*')) active @endif">
            <i class="bi bi-chat-square-text me-2"></i>Grievances
        </a>
        <a href="{{ route('student.mentor.index') }}" class="nav-link @if(request()->routeIs('student.mentor.*')) active @endif">
            <i class="bi bi-person-badge me-2"></i>My Mentor
        </a>
        <a href="{{ route('student.feedback.index') }}" class="nav-link @if(request()->routeIs('student.feedback.*')) active @endif">
            <i class="bi bi-star me-2"></i>Course Feedback
        </a>
        <a href="{{ route('student.condonation.index') }}" class="nav-link @if(request()->routeIs('student.condonation.*')) active @endif">
            <i class="bi bi-shield-check me-2"></i>Attendance Condonation
        </a>
        <a href="{{ route('student.documents.index') }}" class="nav-link @if(request()->routeIs('student.documents.*')) active @endif">
            <i class="bi bi-file-earmark-text me-2"></i>Document Requests
        </a>
        <a href="{{ route('student.transport.index') }}" class="nav-link @if(request()->routeIs('student.transport.*')) active @endif">
            <i class="bi bi-bus-front me-2"></i>Transport
        </a>
        <a href="{{ route('student.hostel.outpass') }}" class="nav-link @if(request()->routeIs('student.hostel.outpass')) active @endif">
            <i class="bi bi-door-open me-2"></i>Outpass Request
        </a>
        <a href="{{ route('student.hostel.complaints.index') }}" class="nav-link @if(request()->routeIs('student.hostel.complaints.*')) active @endif">
            <i class="bi bi-tools me-2"></i>Hostel Complaints
        </a>
        <a href="{{ route('student.library.index') }}" class="nav-link @if(request()->routeIs('student.library.*')) active @endif">
            <i class="bi bi-book me-2"></i>My Library
        </a>

        <div class="sidebar-divider"></div>

        <div class="section-label">Account</div>
        <a href="{{ route('student.summary.index') }}" class="nav-link @if(request()->routeIs('student.summary.*')) active @endif">
            <i class="bi bi-card-text me-2"></i>Academic Summary
        </a>
        <a href="{{ route('student.promotion.index') }}" class="nav-link @if(request()->routeIs('student.promotion.*')) active @endif">
            <i class="bi bi-arrow-up-circle me-2"></i>Promotion Status
        </a>
        <a href="{{ route('student.profile') }}" class="nav-link @if(request()->routeIs('student.profile')) active @endif">
            <i class="bi bi-person-circle"></i> My Profile
        </a>
        <a href="{{ route('student.notifications.edit') }}" class="nav-link @if(request()->routeIs('student.notifications.*')) active @endif">
            <i class="bi bi-bell me-2"></i>Notifications
        </a>
    </div>
</div>

{{-- ===== MOBILE OFFCANVAS SIDEBAR ===== --}}
<div class="offcanvas offcanvas-start sidebar-mobile" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel" style="width:270px">
    <div class="offcanvas-header py-0" style="height:var(--topbar-height);background:rgba(0,0,0,.25);">
        <div class="d-flex align-items-center gap-2">
            <span style="width:30px;height:30px;background:var(--clr-primary);border-radius:7px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.95rem;"><i class="bi bi-mortarboard-fill"></i></span>
            <div>
                <div style="color:#f8fafc;font-weight:700;font-size:.9rem;line-height:1.2;" id="mobileSidebarLabel">EduManage</div>
                <div style="color:rgba(255,255,255,.45);font-size:.65rem;">Student Portal</div>
            </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0 pb-4">
        <div class="section-label">Main</div>
        <a href="{{ route('student.dashboard') }}" class="nav-link @if(request()->routeIs('student.dashboard')) active @endif"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Academics</div>
        <a href="{{ route('student.timetable') }}" class="nav-link @if(request()->routeIs('student.timetable')) active @endif"><i class="bi bi-calendar3 me-2"></i>My Timetable</a>
        <a href="{{ route('student.attendance') }}" class="nav-link @if(request()->routeIs('student.attendance*')) active @endif"><i class="bi bi-check2-square"></i> Attendance</a>
        <a href="{{ route('student.results') }}" class="nav-link @if(request()->routeIs('student.results')) active @endif"><i class="bi bi-award"></i> Results & Grades</a>
        <a href="{{ route('student.admit-cards.index') }}" class="nav-link @if(request()->routeIs('student.admit-cards.*')) active @endif"><i class="bi bi-card-checklist me-2"></i>Admit Cards</a>
        <a href="{{ route('student.subjects.index') }}" class="nav-link @if(request()->routeIs('student.subjects.*')) active @endif"><i class="bi bi-journal-text me-2"></i>Subject Registration</a>
        <a href="{{ route('student.calendar.index') }}" class="nav-link @if(request()->routeIs('student.calendar.*')) active @endif"><i class="bi bi-calendar-event me-2"></i>Academic Calendar</a>
        <a href="{{ route('student.leave.index') }}" class="nav-link @if(request()->routeIs('student.leave.*')) active @endif"><i class="bi bi-person-dash me-2"></i>Leave Applications</a>
        @if($studentNavHasIssuedTranscript)
            <a href="{{ route('student.transcript.download') }}" class="nav-link"><i class="bi bi-file-earmark-text me-2"></i>Official Transcript</a>
        @endif
        <a href="{{ route('student.exam-reg.index') }}" class="nav-link @if(request()->routeIs('student.exam-reg.*')) active @endif"><i class="bi bi-clipboard-check me-2"></i>Exam Registration</a>
        <a href="{{ route('student.appeals.index') }}" class="nav-link @if(request()->routeIs('student.appeals.*')) active @endif"><i class="bi bi-megaphone me-2"></i>Marks Appeals</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Course Content</div>
        <a href="{{ route('student.courses.index') }}" class="nav-link @if(request()->routeIs('student.courses.*')) active @endif"><i class="bi bi-book me-2"></i>My Courses</a>
        <a href="{{ route('student.assignments.index') }}" class="nav-link @if(request()->routeIs('student.assignments.*')) active @endif"><i class="bi bi-pencil-square me-2"></i>Assignments</a>
        <a href="{{ route('student.quizzes.index') }}" class="nav-link @if(request()->routeIs('student.quizzes.*')) active @endif"><i class="bi bi-patch-question me-2"></i>Quizzes</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Finance</div>
        <a href="{{ route('student.fees') }}" class="nav-link @if(request()->routeIs('student.fees')) active @endif"><i class="bi bi-cash-coin"></i> Fee Status</a>
        <a href="{{ route('student.fee-payment.index') }}" class="nav-link @if(request()->routeIs('student.fee-payment.*')) active @endif"><i class="bi bi-upload me-2"></i>Submit Payment</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Career</div>
        <a href="{{ route('student.placements') }}" class="nav-link @if(request()->routeIs('student.placements*')) active @endif"><i class="bi bi-briefcase"></i> Placements</a>
        <a href="{{ route('student.scholarships.index') }}" class="nav-link @if(request()->routeIs('student.scholarships.*')) active @endif"><i class="bi bi-award me-2"></i>Scholarships</a>
        <a href="{{ route('student.resume.index') }}" class="nav-link @if(request()->routeIs('student.resume.*')) active @endif"><i class="bi bi-person-vcard me-2"></i>My Resume</a>
        <a href="{{ route('student.career-events.index') }}" class="nav-link @if(request()->routeIs('student.career-events.*')) active @endif"><i class="bi bi-calendar-event me-2"></i>Career Events</a>
        <a href="{{ route('student.internships.index') }}" class="nav-link @if(request()->routeIs('student.internships.*')) active @endif"><i class="bi bi-building me-2"></i>My Internships</a>
        <a href="{{ route('student.alumni.index') }}" class="nav-link @if(request()->routeIs('student.alumni.*')) active @endif"><i class="bi bi-people me-2"></i>Alumni Network</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Support</div>
        <a href="{{ route('student.notices') }}" class="nav-link @if(request()->routeIs('student.notices*')) active @endif"><i class="bi bi-megaphone"></i> Notices</a>
        <a href="{{ route('student.library.index') }}" class="nav-link @if(request()->routeIs('student.library.*')) active @endif"><i class="bi bi-book-half me-2"></i>Library</a>
        <a href="{{ route('student.grievances.index') }}" class="nav-link @if(request()->routeIs('student.grievances*')) active @endif"><i class="bi bi-chat-square-text me-2"></i>Grievances</a>
        <a href="{{ route('student.mentor.index') }}" class="nav-link @if(request()->routeIs('student.mentor.*')) active @endif"><i class="bi bi-person-badge me-2"></i>My Mentor</a>
        <a href="{{ route('student.feedback.index') }}" class="nav-link @if(request()->routeIs('student.feedback.*')) active @endif"><i class="bi bi-star me-2"></i>Course Feedback</a>
        <a href="{{ route('student.condonation.index') }}" class="nav-link @if(request()->routeIs('student.condonation.*')) active @endif"><i class="bi bi-shield-check me-2"></i>Att. Condonation</a>
        <a href="{{ route('student.documents.index') }}" class="nav-link @if(request()->routeIs('student.documents.*')) active @endif"><i class="bi bi-file-earmark-text me-2"></i>Document Requests</a>
        <a href="{{ route('student.transport.index') }}" class="nav-link @if(request()->routeIs('student.transport.*')) active @endif"><i class="bi bi-bus-front me-2"></i>Transport</a>
        <a href="{{ route('student.hostel.outpass') }}" class="nav-link @if(request()->routeIs('student.hostel.outpass')) active @endif"><i class="bi bi-door-open me-2"></i>Outpass Request</a>
        <a href="{{ route('student.hostel.complaints.index') }}" class="nav-link @if(request()->routeIs('student.hostel.complaints.*')) active @endif"><i class="bi bi-tools me-2"></i>Hostel Complaints</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Account</div>
        <a href="{{ route('student.summary.index') }}" class="nav-link @if(request()->routeIs('student.summary.*')) active @endif"><i class="bi bi-card-text me-2"></i>Academic Summary</a>
        <a href="{{ route('student.promotion.index') }}" class="nav-link @if(request()->routeIs('student.promotion.*')) active @endif"><i class="bi bi-arrow-up-circle me-2"></i>Promotion Status</a>
        <a href="{{ route('student.profile') }}" class="nav-link @if(request()->routeIs('student.profile')) active @endif"><i class="bi bi-person-circle"></i> My Profile</a>
        <a href="{{ route('student.notifications.edit') }}" class="nav-link @if(request()->routeIs('student.notifications.*')) active @endif"><i class="bi bi-bell me-2"></i>Notifications</a>
    </div>
</div>

{{-- ===== MAIN CONTENT ===== --}}
<div class="main-content">

    {{-- TOPBAR --}}
    <div class="topbar">
        <div class="topbar-left">
            <button class="btn btn-sm sidebar-mobile-toggle d-lg-none" type="button"
                    style="width:36px;height:36px;padding:0;border:1px solid var(--clr-border);border-radius:var(--radius-sm);background:transparent;color:var(--clr-text-muted);display:flex;align-items:center;justify-content:center;"
                    data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar"
                    aria-controls="mobileSidebar" aria-label="Open navigation menu">
                <i class="bi bi-list fs-5"></i>
            </button>

            <div class="page-heading">
                <h6>@yield('page-title', trim($__env->yieldContent('title', 'Dashboard')))</h6>
                @hasSection('breadcrumb')
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        @yield('breadcrumb')
                    </ol>
                </nav>
                @endif
            </div>
        </div>

        <div class="topbar-right">
            <span class="text-muted small d-none d-md-inline me-2">
                {{ Auth::user()->name }}
            </span>
            <a href="{{ route('notifications.index') }}" class="notif-btn text-decoration-none me-1" title="Notifications">
                <i class="bi bi-bell" style="font-size:1rem"></i>
                <span id="studentNotifBadge" class="notif-badge" style="display:none;font-size:.6rem;width:auto;height:auto;padding:1px 4px;border-radius:8px;background:#ef4444;color:#fff;position:absolute;top:4px;right:4px;"></span>
            </a>
            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-secondary" style="font-size:.75rem;padding:3px 10px;">
                    <i class="bi bi-box-arrow-right me-1"></i>Logout
                </button>
            </form>
        </div>
    </div>

    {{-- ALERTS --}}
    <div class="px-3 pt-3">
        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif
        @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif
        @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif
    </div>

    <div class="content-area">
        @yield('content')
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Notification badge polling (every 60s)
(function () {
    var badge = document.getElementById('studentNotifBadge');
    if (!badge) return;
    function updateCount() {
        fetch('{{ route('notifications.unread-count') }}', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.unread_count > 0) {
                    badge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
                    badge.style.display = '';
                } else {
                    badge.style.display = 'none';
                }
            }).catch(function() {});
    }
    updateCount();
    setInterval(updateCount, 60000);
})();
</script>
@stack('scripts')
</body>
</html>
