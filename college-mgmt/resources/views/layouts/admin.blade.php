<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'EduManage — Admin')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/css/app.css" rel="stylesheet">
    @stack('styles')
</head>
<body>

{{-- ===== DESKTOP SIDEBAR ===== --}}
<div class="sidebar sidebar-desktop">
    <a class="sidebar-brand" href="{{ route('admin.dashboard') }}">
        <span class="brand-icon"><i class="bi bi-mortarboard-fill"></i></span>
        <span>
            <div class="brand-text">EduManage</div>
            <div class="brand-sub">Admin Portal</div>
        </span>
    </a>

    <div class="mt-2 pb-4 flex-grow-1">
        {{-- MAIN --}}
        <div class="section-label">Main</div>
        <a href="{{ route('admin.dashboard') }}" class="nav-link @if(request()->routeIs('admin.dashboard')) active @endif">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="sidebar-divider"></div>

        {{-- ACADEMIC SETUP --}}
        <div class="section-label">Academic Setup</div>
        <a href="{{ route('admin.academic-years.index') }}" class="nav-link @if(request()->routeIs('admin.academic-years.*')) active @endif">
            <i class="bi bi-calendar3"></i> Academic Years
        </a>
        <a href="{{ route('admin.semesters.index') }}" class="nav-link @if(request()->routeIs('admin.semesters.*')) active @endif">
            <i class="bi bi-calendar-range"></i> Semesters
        </a>
        <a href="{{ route('admin.departments.index') }}" class="nav-link @if(request()->routeIs('admin.departments.*')) active @endif">
            <i class="bi bi-building"></i> Departments
        </a>
        <a href="{{ route('admin.subjects.index') }}" class="nav-link @if(request()->routeIs('admin.subjects.*')) active @endif">
            <i class="bi bi-book"></i> Subjects
        </a>
        <a href="{{ route('admin.classrooms.index') }}" class="nav-link @if(request()->routeIs('admin.classrooms.*')) active @endif">
            <i class="bi bi-door-open"></i> Classrooms
        </a>

        <div class="sidebar-divider"></div>

        {{-- ACADEMIC STRUCTURE --}}
        <div class="section-label">Academic Structure</div>
        <a href="{{ route('admin.programs.index') }}" class="nav-link @if(request()->routeIs('admin.programs.*') || request()->routeIs('admin.admission-config.*')) active @endif">
            <i class="bi bi-mortarboard"></i> Programs
        </a>
        <a href="{{ route('admin.batches.index') }}" class="nav-link @if(request()->routeIs('admin.batches.*')) active @endif">
            <i class="bi bi-collection"></i> Batches
        </a>

        <div class="sidebar-divider"></div>

        {{-- TIMETABLE --}}
        <div class="section-label">Timetable</div>
        <a href="{{ route('admin.timetable-slots.index') }}" class="nav-link @if(request()->routeIs('admin.timetable-slots.*')) active @endif">
            <i class="bi bi-clock"></i> Time Slots
        </a>
        <a href="{{ route('admin.timetable.index') }}" class="nav-link @if(request()->routeIs('admin.timetable.index') || request()->routeIs('admin.timetable.create') || request()->routeIs('admin.timetable.edit')) active @endif">
            <i class="bi bi-grid-3x3-gap"></i> Weekly Timetable
        </a>
        <a href="{{ route('admin.timetable.teacher-view') }}" class="nav-link @if(request()->routeIs('admin.timetable.teacher-view')) active @endif">
            <i class="bi bi-person-lines-fill"></i> Teacher View
        </a>

        <div class="sidebar-divider"></div>

        {{-- PEOPLE --}}
        <div class="section-label">People</div>
        <a href="{{ route('admin.teachers.index') }}" class="nav-link @if(request()->routeIs('admin.teachers.*')) active @endif">
            <i class="bi bi-person-badge"></i> Teachers
        </a>
        <a href="{{ route('admin.students.index') }}" class="nav-link @if(request()->routeIs('admin.students.*')) active @endif">
            <i class="bi bi-people"></i> Students
        </a>
        <a href="{{ route('admin.applicants.index') }}" class="nav-link @if(request()->routeIs('admin.applicants.*')) active @endif">
            <i class="bi bi-person-lines-fill"></i> Applications
        </a>
        <a href="{{ route('admin.admissions.index') }}" class="nav-link @if(request()->routeIs('admin.admissions.*')) active @endif">
            <i class="bi bi-person-plus-fill"></i> Admissions
        </a>
        <a href="{{ route('admin.parents.index') }}" class="nav-link @if(request()->routeIs('admin.parents.*')) active @endif">
            <i class="bi bi-people-fill"></i> Parents
        </a>

        <div class="sidebar-divider"></div>

        {{-- ACADEMICS --}}
        <div class="section-label">Academics</div>
        <a href="{{ route('admin.leaves.index') }}" class="nav-link @if(request()->routeIs('admin.leaves.*')) active @endif">
            <i class="bi bi-calendar-x"></i> Leave Mgmt
        </a>
        <a href="{{ route('admin.faculty.workload') }}" class="nav-link @if(request()->routeIs('admin.faculty.*')) active @endif">
            <i class="bi bi-bar-chart"></i> Faculty Report
        </a>
        <a href="{{ route('admin.attendance.index') }}" class="nav-link @if(request()->routeIs('admin.attendance.*')) active @endif">
            <i class="bi bi-check2-square"></i> Attendance
        </a>
        <a href="{{ route('admin.exams.index') }}" class="nav-link @if(request()->routeIs('admin.exams.*')) active @endif">
            <i class="bi bi-file-earmark-text"></i> Exams &amp; Results
        </a>
        <a href="{{ route('admin.enrollments.index') }}" class="nav-link @if(request()->routeIs('admin.enrollments.*')) active @endif">
            <i class="bi bi-person-check"></i> Enrollments
        </a>
        <a href="{{ route('admin.results.index') }}" class="nav-link @if(request()->routeIs('admin.results.*')) active @endif">
            <i class="bi bi-award"></i> Grade Reports
        </a>

        <div class="sidebar-divider"></div>

        {{-- FINANCE --}}
        <div class="section-label">Finance</div>
        <a href="{{ route('admin.fees.index') }}" class="nav-link @if(request()->routeIs('admin.fees.index') || request()->routeIs('admin.fees.show') || request()->routeIs('admin.fees.create') || request()->routeIs('admin.fees.edit') || request()->routeIs('admin.fees.collect') || request()->routeIs('admin.fees.receipt')) active @endif">
            <i class="bi bi-cash-coin"></i> Fees
        </a>
        <a href="{{ route('admin.fees.report') }}" class="nav-link @if(request()->routeIs('admin.fees.report')) active @endif">
            <i class="bi bi-graph-up"></i> Fee Report
        </a>

        <div class="sidebar-divider"></div>

        {{-- COMMUNICATION --}}
        <div class="section-label">Communication</div>
        <a href="{{ route('admin.notices.index') }}" class="nav-link @if(request()->routeIs('admin.notices.*')) active @endif">
            <i class="bi bi-megaphone"></i> Notices
        </a>

        <div class="sidebar-divider"></div>

        <div class="sidebar-divider"></div>

        {{-- PLACEMENT --}}
        <div class="section-label">Placement</div>
        <a href="{{ route('admin.companies.index') }}" class="nav-link @if(request()->routeIs('admin.companies.*')) active @endif">
            <i class="bi bi-building"></i> Companies
        </a>
        <a href="{{ route('admin.placement-drives.index') }}" class="nav-link @if(request()->routeIs('admin.placement-drives.*')) active @endif">
            <i class="bi bi-briefcase"></i> Drives
        </a>

        <div class="sidebar-divider"></div>

        {{-- SETTINGS --}}
        <div class="section-label">System</div>
        <a href="{{ route('admin.settings') }}" class="nav-link @if(request()->routeIs('admin.settings*')) active @endif">
            <i class="bi bi-gear"></i> Settings
        </a>
        <a href="{{ route('admin.activity-log') }}" class="nav-link @if(request()->routeIs('admin.activity-log')) active @endif">
            <i class="bi bi-clock-history"></i> Activity Log
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
                <div style="color:rgba(255,255,255,.45);font-size:.65rem;">Admin Portal</div>
            </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0 pb-4">
        <div class="section-label">Main</div>
        <a href="{{ route('admin.dashboard') }}" class="nav-link @if(request()->routeIs('admin.dashboard')) active @endif"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Academic Setup</div>
        <a href="{{ route('admin.academic-years.index') }}" class="nav-link @if(request()->routeIs('admin.academic-years.*')) active @endif"><i class="bi bi-calendar3"></i> Academic Years</a>
        <a href="{{ route('admin.semesters.index') }}" class="nav-link @if(request()->routeIs('admin.semesters.*')) active @endif"><i class="bi bi-calendar-range"></i> Semesters</a>
        <a href="{{ route('admin.departments.index') }}" class="nav-link @if(request()->routeIs('admin.departments.*')) active @endif"><i class="bi bi-building"></i> Departments</a>
        <a href="{{ route('admin.subjects.index') }}" class="nav-link @if(request()->routeIs('admin.subjects.*')) active @endif"><i class="bi bi-book"></i> Subjects</a>
        <a href="{{ route('admin.classrooms.index') }}" class="nav-link @if(request()->routeIs('admin.classrooms.*')) active @endif"><i class="bi bi-door-open"></i> Classrooms</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Academic Structure</div>
        <a href="{{ route('admin.programs.index') }}" class="nav-link @if(request()->routeIs('admin.programs.*') || request()->routeIs('admin.admission-config.*')) active @endif"><i class="bi bi-mortarboard"></i> Programs</a>
        <a href="{{ route('admin.batches.index') }}" class="nav-link @if(request()->routeIs('admin.batches.*')) active @endif"><i class="bi bi-collection"></i> Batches</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Timetable</div>
        <a href="{{ route('admin.timetable-slots.index') }}" class="nav-link @if(request()->routeIs('admin.timetable-slots.*')) active @endif"><i class="bi bi-clock"></i> Time Slots</a>
        <a href="{{ route('admin.timetable.index') }}" class="nav-link @if(request()->routeIs('admin.timetable.index')||request()->routeIs('admin.timetable.create')||request()->routeIs('admin.timetable.edit')) active @endif"><i class="bi bi-grid-3x3-gap"></i> Weekly Timetable</a>
        <a href="{{ route('admin.timetable.teacher-view') }}" class="nav-link @if(request()->routeIs('admin.timetable.teacher-view')) active @endif"><i class="bi bi-person-lines-fill"></i> Teacher View</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">People</div>
        <a href="{{ route('admin.teachers.index') }}" class="nav-link @if(request()->routeIs('admin.teachers.*')) active @endif"><i class="bi bi-person-badge"></i> Teachers</a>
        <a href="{{ route('admin.students.index') }}" class="nav-link @if(request()->routeIs('admin.students.*')) active @endif"><i class="bi bi-people"></i> Students</a>
        <a href="{{ route('admin.applicants.index') }}" class="nav-link @if(request()->routeIs('admin.applicants.*')) active @endif"><i class="bi bi-person-lines-fill"></i> Applications</a>
        <a href="{{ route('admin.admissions.index') }}" class="nav-link @if(request()->routeIs('admin.admissions.*')) active @endif"><i class="bi bi-person-plus-fill"></i> Admissions</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Academics</div>
        <a href="{{ route('admin.leaves.index') }}" class="nav-link @if(request()->routeIs('admin.leaves.*')) active @endif"><i class="bi bi-calendar-x"></i> Leave Mgmt</a>
        <a href="{{ route('admin.faculty.workload') }}" class="nav-link @if(request()->routeIs('admin.faculty.*')) active @endif"><i class="bi bi-bar-chart"></i> Faculty Report</a>
        <a href="{{ route('admin.attendance.index') }}" class="nav-link @if(request()->routeIs('admin.attendance.*')) active @endif"><i class="bi bi-check2-square"></i> Attendance</a>
        <a href="{{ route('admin.exams.index') }}" class="nav-link @if(request()->routeIs('admin.exams.*')) active @endif"><i class="bi bi-file-earmark-text"></i> Exams &amp; Results</a>
        <a href="{{ route('admin.enrollments.index') }}" class="nav-link @if(request()->routeIs('admin.enrollments.*')) active @endif"><i class="bi bi-person-check"></i> Enrollments</a>
        <a href="{{ route('admin.results.index') }}" class="nav-link @if(request()->routeIs('admin.results.*')) active @endif"><i class="bi bi-award"></i> Grade Reports</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Finance</div>
        <a href="{{ route('admin.fees.index') }}" class="nav-link @if(request()->routeIs('admin.fees.index') || request()->routeIs('admin.fees.show') || request()->routeIs('admin.fees.create') || request()->routeIs('admin.fees.edit') || request()->routeIs('admin.fees.collect') || request()->routeIs('admin.fees.receipt')) active @endif"><i class="bi bi-cash-coin"></i> Fees</a>
        <a href="{{ route('admin.fees.report') }}" class="nav-link @if(request()->routeIs('admin.fees.report')) active @endif"><i class="bi bi-graph-up"></i> Fee Report</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">Communication</div>
        <a href="{{ route('admin.notices.index') }}" class="nav-link @if(request()->routeIs('admin.notices.*')) active @endif"><i class="bi bi-megaphone"></i> Notices</a>
        <div class="sidebar-divider"></div>
        <div class="sidebar-divider"></div>
        <div class="section-label">Placement</div>
        <a href="{{ route('admin.companies.index') }}" class="nav-link @if(request()->routeIs('admin.companies.*')) active @endif"><i class="bi bi-building"></i> Companies</a>
        <a href="{{ route('admin.placement-drives.index') }}" class="nav-link @if(request()->routeIs('admin.placement-drives.*')) active @endif"><i class="bi bi-briefcase"></i> Drives</a>
        <div class="sidebar-divider"></div>
        <div class="section-label">System</div>
        <a href="{{ route('admin.settings') }}" class="nav-link @if(request()->routeIs('admin.settings*')) active @endif"><i class="bi bi-gear"></i> Settings</a>
        <a href="{{ route('admin.activity-log') }}" class="nav-link @if(request()->routeIs('admin.activity-log')) active @endif"><i class="bi bi-clock-history"></i> Activity Log</a>
    </div>
</div>

{{-- ===== MAIN CONTENT ===== --}}
<div class="main-content">

    {{-- TOPBAR --}}
    <div class="topbar">
        <div class="topbar-left">
            {{-- Mobile hamburger --}}
            <button class="btn btn-sm sidebar-mobile-toggle d-lg-none" type="button"
                    style="width:36px;height:36px;padding:0;border:1px solid var(--clr-border);border-radius:var(--radius-sm);background:transparent;color:var(--clr-text-muted);display:flex;align-items:center;justify-content:center;"
                    data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar"
                    aria-controls="mobileSidebar" aria-label="Open navigation menu">
                <i class="bi bi-list fs-5"></i>
            </button>

            {{-- Page heading --}}
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
            {{-- Global search --}}
            <form method="GET" action="{{ route('admin.search') }}" class="topbar-search d-none d-md-flex">
                <i class="bi bi-search search-icon"></i>
                <input type="search" name="q" placeholder="Search students, teachers..." value="{{ request('q') }}" aria-label="Global search">
            </form>

            {{-- Dark mode toggle --}}
            <button class="theme-btn" id="themeToggle" aria-label="Toggle dark mode" title="Toggle dark mode">
                <i class="bi bi-moon-fill" id="themeIcon"></i>
            </button>

            {{-- Notification bell --}}
            <button class="notif-btn" aria-label="Notifications" title="Notifications">
                <i class="bi bi-bell"></i>
                <span class="notif-badge"></span>
            </button>

            {{-- User avatar dropdown --}}
            <div class="dropdown">
                <button class="user-avatar dropdown-toggle" style="border:none;" data-bs-toggle="dropdown" aria-expanded="false" aria-label="User menu">
                    {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width:200px;font-size:.84rem;border-color:var(--clr-border);">
                    <li>
                        <div class="px-3 py-2">
                            <div class="fw-600" style="font-weight:600;color:var(--clr-text);">{{ auth()->user()->name }}</div>
                            <div style="font-size:.75rem;color:var(--clr-text-muted);">{{ auth()->user()->email }}</div>
                        </div>
                    </li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i>View Profile</a></li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="bi bi-box-arrow-right me-2"></i>Logout
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    {{-- PAGE BODY --}}
    <div class="page-body">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show js-auto-dismiss" role="alert" aria-live="polite">
                <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close alert"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert" aria-live="polite">
                <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close alert"></button>
            </div>
        @endif
        @if(session('info'))
            <div class="alert alert-info alert-dismissible fade show js-auto-dismiss" role="alert" aria-live="polite">
                <i class="bi bi-info-circle me-2"></i>{{ session('info') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close alert"></button>
            </div>
        @endif
        @if(session('warning'))
            <div class="alert alert-warning alert-dismissible fade show" role="alert" aria-live="polite">
                <i class="bi bi-exclamation-circle me-2"></i>{{ session('warning') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close alert"></button>
            </div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert" aria-live="polite">
                <i class="bi bi-exclamation-triangle me-2"></i>
                @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close alert"></button>
            </div>
        @endif

        @yield('content')
    </div>
</div>

{{-- ===== DELETE CONFIRMATION MODAL ===== --}}
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:400px">
        <div class="modal-content">
            <div class="modal-header pb-0">
                <div style="width:52px;height:52px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#dc2626;font-size:1.4rem;">
                    <i class="bi bi-trash3"></i>
                </div>
            </div>
            <div class="modal-body pt-2">
                <h6 class="fw-700 mb-1" id="deleteModalLabel" style="font-size:1rem;font-weight:700;">Delete Record</h6>
                <p class="mb-0" style="font-size:.85rem;color:var(--clr-text-muted);">
                    Are you sure you want to delete <strong id="deleteModalName"></strong>? This action cannot be undone.
                </p>
            </div>
            <div class="modal-footer pt-0 gap-2">
                <button type="button" class="btn btn-sm" style="border:1px solid var(--clr-border);background:transparent;color:var(--clr-text);" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger">
                        <i class="bi bi-trash3 me-1"></i>Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ── Dark mode ──────────────────────────────────────────────
(function () {
    var saved = localStorage.getItem('edumTheme') || 'light';
    document.documentElement.setAttribute('data-theme', saved);
    updateThemeIcon(saved);

    document.getElementById('themeToggle').addEventListener('click', function () {
        var current = document.documentElement.getAttribute('data-theme');
        var next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('edumTheme', next);
        updateThemeIcon(next);
    });

    function updateThemeIcon(theme) {
        var icon = document.getElementById('themeIcon');
        if (!icon) return;
        icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
    }
})();

// ── Auto-dismiss success/info alerts ──────────────────────
(function () {
    setTimeout(function () {
        document.querySelectorAll('.js-auto-dismiss').forEach(function (el) {
            var alert = bootstrap.Alert.getOrCreateInstance(el);
            alert.close();
        });
    }, 4000);
})();

// ── Delete modal ───────────────────────────────────────────
(function () {
    var deleteModal = document.getElementById('deleteModal');
    if (!deleteModal) return;

    deleteModal.addEventListener('show.bs.modal', function (event) {
        var trigger = event.relatedTarget;
        if (!trigger) return;
        var action = trigger.getAttribute('data-action') || '#';
        var name   = trigger.getAttribute('data-name') || 'this record';

        document.getElementById('deleteForm').setAttribute('action', action);
        document.getElementById('deleteModalName').textContent = name;
    });

    // Support data-confirm-delete programmatic triggers
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-confirm-delete="true"]');
        if (!btn) return;
        e.preventDefault();
        var action = btn.getAttribute('data-action') || '#';
        var name   = btn.getAttribute('data-name') || 'this record';
        document.getElementById('deleteForm').setAttribute('action', action);
        document.getElementById('deleteModalName').textContent = name;
        var bsModal = new bootstrap.Modal(deleteModal);
        bsModal.show();
    });
})();
</script>

@stack('scripts')
</body>
</html>
