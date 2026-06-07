<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>@yield('title', 'EduManage — Student')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/css/app.css" rel="stylesheet">
    @stack('styles')
</head>
<body>

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
        <a href="{{ route('student.transcript.download') }}" class="nav-link">
            <i class="bi bi-file-earmark-text me-2"></i>Official Transcript
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

        <div class="sidebar-divider"></div>

        <div class="section-label">Career</div>
        <a href="{{ route('student.placements') }}" class="nav-link @if(request()->routeIs('student.placements*')) active @endif">
            <i class="bi bi-briefcase"></i> Placements
        </a>

        <div class="sidebar-divider"></div>

        <div class="section-label">Communication</div>
        <a href="{{ route('student.notices') }}" class="nav-link @if(request()->routeIs('student.notices*')) active @endif">
            <i class="bi bi-megaphone"></i> Notices
        </a>
        <a href="{{ route('student.grievances.index') }}" class="nav-link @if(request()->routeIs('student.grievances*')) active @endif">
            <i class="bi bi-chat-square-text me-2"></i>Grievances
        </a>

        <div class="sidebar-divider"></div>

        <div class="section-label">Account</div>
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
        <a href="{{ route('student.transcript.download') }}" class="nav-link"><i class="bi bi-file-earmark-text me-2"></i>Official Transcript</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Course Content</div>
        <a href="{{ route('student.courses.index') }}" class="nav-link @if(request()->routeIs('student.courses.*')) active @endif"><i class="bi bi-book me-2"></i>My Courses</a>
        <a href="{{ route('student.assignments.index') }}" class="nav-link @if(request()->routeIs('student.assignments.*')) active @endif"><i class="bi bi-pencil-square me-2"></i>Assignments</a>
        <a href="{{ route('student.quizzes.index') }}" class="nav-link @if(request()->routeIs('student.quizzes.*')) active @endif"><i class="bi bi-patch-question me-2"></i>Quizzes</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Finance</div>
        <a href="{{ route('student.fees') }}" class="nav-link @if(request()->routeIs('student.fees')) active @endif"><i class="bi bi-cash-coin"></i> Fee Status</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Career</div>
        <a href="{{ route('student.placements') }}" class="nav-link @if(request()->routeIs('student.placements*')) active @endif"><i class="bi bi-briefcase"></i> Placements</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Communication</div>
        <a href="{{ route('student.notices') }}" class="nav-link @if(request()->routeIs('student.notices*')) active @endif"><i class="bi bi-megaphone"></i> Notices</a>
        <a href="{{ route('student.grievances.index') }}" class="nav-link @if(request()->routeIs('student.grievances*')) active @endif"><i class="bi bi-chat-square-text me-2"></i>Grievances</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Account</div>
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
                <h6>@yield('page-title', 'Dashboard')</h6>
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
@stack('scripts')
</body>
</html>
